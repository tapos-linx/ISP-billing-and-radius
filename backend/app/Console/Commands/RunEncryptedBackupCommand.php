<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RunEncryptedBackupCommand extends Command
{
    protected $signature = 'ops:backup-database {--dry-run} {--passphrase=}';

    protected $description = 'Execute automated AES-256-CBC encrypted PostgreSQL database backup with SHA-256 checksum audit';

    public function handle(): int
    {
        $runUuid = (string) Str::uuid();
        $startTime = now();
        $tenantId = 1;

        $this->info("Starting Encrypted Backup Run: {$runUuid}");

        DB::table('backup_runs')->insert([
            'tenant_id' => $tenantId,
            'run_uuid' => $runUuid,
            'status' => 'running',
            'started_at' => $startTime,
            'created_at' => $startTime,
            'updated_at' => $startTime,
        ]);

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $fileName = "backup_carrier_isp_{$runUuid}.sql.gz.enc";
        $filePath = "{$backupDir}/{$fileName}";

        try {
            $passphrase = (string) ($this->option('passphrase') ?: config('app.key', 'BackupMasterSecretKey2026!'));

            if ($this->option('dry-run') || config('app.testing')) {
                // Emulated database dump payload for testing
                $mockDump = "-- CARRIER ISP CITUS/POSTGRESQL DUMP\n-- RUN: {$runUuid}\n-- DATE: {$startTime}\n";
                $iv = openssl_random_pseudo_bytes(16);
                $encrypted = openssl_encrypt($mockDump, 'aes-256-cbc', substr(hash('sha256', $passphrase, true), 0, 32), OPENSSL_RAW_DATA, $iv);
                $finalData = $iv.($encrypted !== false ? $encrypted : '');
                file_put_contents($filePath, $finalData);
            } else {
                $dbHost = config('database.connections.pgsql.host', '127.0.0.1');
                $dbPort = config('database.connections.pgsql.port', '5432');
                $dbName = config('database.connections.pgsql.database', 'isp_bss_oss');
                $dbUser = config('database.connections.pgsql.username', 'isp_admin');

                $cmd = sprintf(
                    'pg_dump -h %s -p %s -U %s -d %s | gzip -9 | openssl enc -aes-256-cbc -pbkdf2 -pass pass:%s -out %s',
                    escapeshellarg((string) $dbHost),
                    escapeshellarg((string) $dbPort),
                    escapeshellarg((string) $dbUser),
                    escapeshellarg((string) $dbName),
                    escapeshellarg($passphrase),
                    escapeshellarg($filePath)
                );
                exec($cmd, $output, $retCode);
                if ($retCode !== 0) {
                    throw new \RuntimeException("pg_dump backup pipeline failed with exit code {$retCode}");
                }
            }

            $bytesSize = file_exists($filePath) ? filesize($filePath) : 0;
            $checksum = file_exists($filePath) ? hash_file('sha256', $filePath) : '';

            DB::table('backup_runs')->where('run_uuid', $runUuid)->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'checksum_sha256' => $checksum,
                'bytes_size' => $bytesSize,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("Backup completed successfully! Size: {$bytesSize} bytes, SHA-256: {$checksum}");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            DB::table('backup_runs')->where('run_uuid', $runUuid)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->error("Backup run failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
