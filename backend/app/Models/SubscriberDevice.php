<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Release 2.2: Mobile Device Registration for Push & Biometric Authentication
 */
class SubscriberDevice extends Model
{
    use HasFactory;

    protected $table = 'subscriber_devices';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $fillable = [
        'subscriber_id',
        'tenant_id',
        'device_uuid',
        'platform',
        'fcm_token',
        'apns_token',
        'device_name',
        'app_version',
        'biometric_public_key',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id', 'subscriber_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(MobilePushNotification::class, 'device_uuid', 'device_uuid');
    }
}
