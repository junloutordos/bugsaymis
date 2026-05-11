<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkDevice extends Model
{
    protected $table = 'network_devices';

    protected $fillable = [
        'mac_address',
        'ip_address',
        'hostname',
        'vendor',
        'device_type',
        'connection_type',
        'vlan_id',
        'vlan_name',
        'ap_name',
        'ssid',
        'signal_dbm',
        'is_online',
        'first_seen_at',
        'last_seen_at',
        'label',
        'notes',
        'data_source',
        'ruijie_cloud_id',
        'last_source',
    ];

    protected $casts = [
        'is_online'     => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
        'vlan_id'       => 'integer',
        'signal_dbm'    => 'integer',
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->label ?? $this->hostname ?? $this->ip_address ?? $this->mac_address;
    }

    public function getLastSeenHumansAttribute(): ?string
    {
        return $this->last_seen_at?->diffForHumans();
    }

    // Mark all devices as offline — called before each SNMP sync
    public static function markAllOffline(): void
    {
        static::where('last_source', 'snmp')->update(['is_online' => false]);
    }
}
