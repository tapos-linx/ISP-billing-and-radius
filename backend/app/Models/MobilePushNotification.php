<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Release 2.2: Mobile Push Notification Log & Audit Trail
 */
class MobilePushNotification extends Model
{
    use HasFactory;

    protected $table = 'mobile_push_notifications';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'subscriber_id',
        'tenant_id',
        'device_uuid',
        'channel',
        'title',
        'body',
        'data_payload',
        'status',
        'fcm_message_id',
        'error_message',
        'sent_at',
        'created_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'data_payload' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id', 'subscriber_id');
    }
}
