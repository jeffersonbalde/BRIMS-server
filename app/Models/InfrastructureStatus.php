<?php
// app/Models/InfrastructureStatus.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfrastructureStatus extends Model
{
    use HasFactory;

    // Specify the table name since it doesn't follow Laravel's naming convention
    protected $table = 'infrastructure_statuses';

    protected $fillable = [
        'incident_id',
        'roads_bridges_status',
        'roads_reported_not_passable',
        'roads_reported_passable',
        'roads_remarks',
        'power_outage_time',
        'power_restored_time',
        'power_remarks',
        'communication_interruption_time',
        'communication_restored_time',
        'communication_remarks',
    ];

    protected $casts = [
        'roads_reported_not_passable' => 'datetime',
        'roads_reported_passable' => 'datetime',
        'power_outage_time' => 'datetime',
        'power_restored_time' => 'datetime',
        'communication_interruption_time' => 'datetime',
        'communication_restored_time' => 'datetime',
    ];

    /**
     * Get the incident that owns the infrastructure status.
     */
    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }
}