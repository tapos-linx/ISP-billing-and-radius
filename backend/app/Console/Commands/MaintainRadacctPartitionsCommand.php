<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaintainRadacctPartitionsCommand extends Command
{
    protected $signature = 'radacct:maintain-partitions {--ahead=6 : Months ahead to pre-create} {--retention=24 : Retention months}';

    protected $description = 'Automatically create future monthly radacct partitions and prune expired partitions';

    public function handle(): int
    {
        $monthsAhead = (int) $this->option('ahead');
        $retentionMonths = (int) $this->option('retention');

        $this->info("Executing radacct partition maintenance (ahead: {$monthsAhead}m, retention: {$retentionMonths}m)...");

        try {
            DB::unprepared("
                CREATE OR REPLACE FUNCTION manage_radacct_monthly_partitions(
                    months_ahead INT DEFAULT 6,
                    retention_months INT DEFAULT 24
                )
                RETURNS TABLE(partition_name TEXT, start_date TIMESTAMPTZ, end_date TIMESTAMPTZ, status TEXT)
                LANGUAGE plpgsql
                AS \$\$
                DECLARE
                    curr_date DATE := date_trunc('month', CURRENT_DATE)::DATE;
                    target_date DATE;
                    drop_cutoff DATE;
                    p_name TEXT;
                    start_ts TIMESTAMPTZ;
                    end_ts TIMESTAMPTZ;
                    part_date DATE;
                    table_exists BOOLEAN;
                    r RECORD;
                BEGIN
                    FOR i IN 0..months_ahead LOOP
                        target_date := (curr_date + (i || ' month')::INTERVAL)::DATE;
                        p_name := 'radacct_y' || to_char(target_date, 'YYYY') || 'm' || to_char(target_date, 'MM');
                        start_ts := target_date::TIMESTAMPTZ;
                        end_ts := (target_date + INTERVAL '1 month')::TIMESTAMPTZ;

                        SELECT EXISTS (
                            SELECT 1 FROM pg_class c
                            JOIN pg_namespace n ON n.oid = c.relnamespace
                            WHERE c.relname = p_name AND n.nspname = 'public'
                        ) INTO table_exists;

                        IF NOT table_exists THEN
                            BEGIN
                                EXECUTE format('CREATE TABLE IF NOT EXISTS %I PARTITION OF radacct FOR VALUES FROM (%L) TO (%L);', p_name, start_ts, end_ts);
                                partition_name := p_name; start_date := start_ts; end_date := end_ts; status := 'CREATED';
                                RETURN NEXT;
                            EXCEPTION WHEN OTHERS THEN
                                NULL;
                            END;
                        ELSE
                            partition_name := p_name; start_date := start_ts; end_date := end_ts; status := 'EXISTS';
                            RETURN NEXT;
                        END IF;
                    END LOOP;

                    drop_cutoff := (curr_date - (retention_months || ' month')::INTERVAL)::DATE;

                    FOR r IN
                        SELECT c.relname::TEXT AS child_table
                        FROM pg_inherits i
                        JOIN pg_class c ON c.oid = i.inhrelid
                        JOIN pg_class parent ON i.inhparent = parent.oid
                        JOIN pg_namespace n ON n.oid = c.relnamespace
                        WHERE parent.relname = 'radacct'
                          AND n.nspname = 'public'
                          AND c.relname ~ '^radacct_y[0-9]{4}m[0-9]{2}\$'
                    LOOP
                        part_date := make_date(
                            substring(r.child_table from 10 for 4)::INT,
                            substring(r.child_table from 15 for 2)::INT,
                            1
                        );

                        IF part_date < drop_cutoff THEN
                            EXECUTE format('ALTER TABLE radacct DETACH PARTITION %I;', r.child_table);
                            EXECUTE format('DROP TABLE IF EXISTS %I;', r.child_table);
                            partition_name := r.child_table;
                            start_date := part_date::TIMESTAMPTZ;
                            end_date := (part_date + INTERVAL '1 month')::TIMESTAMPTZ;
                            status := 'DETACHED_EXPIRED';
                            RETURN NEXT;
                        END IF;
                    END LOOP;

                    RETURN;
                END;
                \$\$;
            ");

            $rows = DB::select('SELECT * FROM manage_radacct_monthly_partitions(?, ?);', [$monthsAhead, $retentionMonths]);

            $created = 0;
            $existing = 0;
            $detached = 0;

            foreach ($rows as $row) {
                if ($row->status === 'CREATED') {
                    $created++;
                    $this->line("  [CREATED] Partition: {$row->partition_name} ({$row->start_date} -> {$row->end_date})");
                } elseif ($row->status === 'EXISTS') {
                    $existing++;
                } elseif ($row->status === 'DETACHED_EXPIRED') {
                    $detached++;
                    $this->warn("  [DETACHED] Expired partition: {$row->partition_name}");
                }
            }

            $this->info("Completed partition maintenance: {$created} created, {$existing} verified, {$detached} detached.");
            Log::info('radacct partition maintenance completed', ['created' => $created, 'existing' => $existing, 'detached' => $detached]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to maintain radacct partitions: '.$e->getMessage());
            Log::error('Partition maintenance failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
