<?php
// app/Models/Incident.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'reported_by',
        'incident_type',
        'title',
        'description',
        'location',
        'incident_date',
        'severity',
        'status',
        'affected_families',
        'affected_individuals',
        'casualties',
        'response_actions',
        'admin_notes',
        'archive_reason',
        'archived_by',
        'archived_at',
        'unarchive_history',
        'unarchive_reason',
        'unarchived_at',
        'unarchived_by',
        'deactivated_at',      // Add this
        'deactivated_by',      // Add this
        'deactivation_reason', // Add this
        'reactivated_at',      // Add this
        'reactivated_by',      // Add this
        'last_login_at',       // Add this
    ];

    protected $casts = [
        'incident_date' => 'datetime',
        'casualties' => 'array',
        'archived_at' => 'datetime',
        'unarchived_at' => 'datetime',
        'unarchive_history' => 'array',
        'deactivated_at' => 'datetime',  // Add this
        'reactivated_at' => 'datetime',  // Add this
        'last_login_at' => 'datetime',   // Add this
    ];

    public function unarchiver()
    {
        return $this->belongsTo(User::class, 'unarchived_by');
    }

    // Add these accessors
    protected $appends = [
        'can_barangay_edit',
        'can_barangay_delete',
        'has_population_data',
        'has_infrastructure_status',
        'completeness_score'
    ];

    /**
     * Relationship: User who deactivated this account
     */
    public function deactivatedBy()
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Relationship: User who reactivated this account
     */
    public function reactivatedBy()
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }


    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function archiver()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function getBarangayNameAttribute()
    {
        return $this->reporter->barangay_name;
    }

    public function getMunicipalityAttribute()
    {
        return $this->reporter->municipality;
    }

    public function getCanBarangayEditAttribute()
    {
        if ($this->reported_by === null) return false;
        $oneHourAgo = now()->subHour();
        return $this->created_at->greaterThan($oneHourAgo);
    }


    public function getCanBarangayDeleteAttribute()
    {
        if ($this->reported_by === null) return false;
        $oneHourAgo = now()->subHour();
        return $this->created_at->greaterThan($oneHourAgo);
    }

    public function getHasPopulationDataAttribute()
    {
        return $this->populationData !== null;
    }

    public function getHasInfrastructureStatusAttribute()
    {
        return $this->infrastructureStatus !== null;
    }


    // Scope for active incidents (non-archived)
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'Archived');
    }

    // Scope for archived incidents
    public function scopeArchived($query)
    {
        return $query->where('status', 'Archived');
    }

    // Scope for non-archived with specific status
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function toArray()
    {
        $array = parent::toArray();

        $array['can_barangay_edit'] = $this->can_barangay_edit;
        $array['can_barangay_delete'] = $this->can_barangay_delete;

        return $array;
    }

    // Add these relationships to your existing Incident model

    /**
     * Get the population data associated with the incident.
     */
    public function populationData()
    {
        return $this->hasOne(PopulationData::class);
    }

    /**
     * Get the infrastructure status associated with the incident.
     */
    public function infrastructureStatus()
    {
        return $this->hasOne(InfrastructureStatus::class);
    }

    // Add these methods to your Incident model



    /**
     * Calculate data completeness score (0-100)
     */
    public function getCompletenessScoreAttribute(): int
    {
        $score = 40; // Base score for basic incident data

        // +30 for population data
        if ($this->populationData) {
            $score += 30;
        }

        // +30 for infrastructure status
        if ($this->infrastructureStatus) {
            $score += 30;
        }

        return min($score, 100);
    }

    /**
     * Check if user can modify population data
     */
    public function canModifyPopulationData($user): bool
    {
        if ($user->role === 'admin') return true;
        if ($user->role === 'barangay' && $this->reported_by === $user->id) return true;
        return false;
    }

    /**
     * Check if user can modify infrastructure status
     */
    public function canModifyInfrastructureStatus($user): bool
    {
        if ($user->role === 'admin') return true;
        if ($user->role === 'barangay' && $this->reported_by === $user->id) return true;
        return false;
    }
}
