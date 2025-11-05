<?php
// app/Http/Resources/IncidentResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
public function toArray($request)
    {
        return [
            'id' => $this->id,
            'reported_by' => $this->reported_by,
            'incident_type' => $this->incident_type,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'incident_date' => $this->incident_date,
            'severity' => $this->severity,
            'status' => $this->status,
            'affected_families' => $this->affected_families,
            'affected_individuals' => $this->affected_individuals,
            'casualties' => $this->casualties,
            'admin_notes' => $this->admin_notes,
            'response_actions' => $this->response_actions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Computed attributes
            'can_barangay_edit' => $this->can_barangay_edit,
            'can_barangay_delete' => $this->can_barangay_delete,
            'has_population_data' => $this->has_population_data,
            'has_infrastructure_status' => $this->has_infrastructure_status,
            'completeness_score' => $this->completeness_score,
            
            // Relationships
            'reporter' => $this->whenLoaded('reporter', function () {
                return [
                    'id' => $this->reporter->id,
                    'name' => $this->reporter->name,
                    'email' => $this->reporter->email,
                    'barangay' => $this->reporter->barangay,
                    'barangay_name' => $this->reporter->barangay_name,
                ];
            }),
            'population_data' => $this->whenLoaded('populationData'),
            'infrastructure_status' => $this->whenLoaded('infrastructureStatus'),
        ];
    }
}

// app/Http/Resources/PopulationDataResource.php
class PopulationDataResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'displaced_families' => $this->displaced_families,
            'displaced_persons' => $this->displaced_persons,
            'families_requiring_assistance' => $this->families_requiring_assistance,
            'families_assisted' => $this->families_assisted,
            'male_count' => $this->male_count,
            'female_count' => $this->female_count,
            'lgbtqia_count' => $this->lgbtqia_count,
            // ... include all population data fields
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

// app/Http/Resources/InfrastructureStatusResource.php
class InfrastructureStatusResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'roads_bridges_status' => $this->roads_bridges_status,
            'roads_reported_not_passable' => $this->roads_reported_not_passable,
            'roads_reported_passable' => $this->roads_reported_passable,
            'roads_remarks' => $this->roads_remarks,
            'power_outage_time' => $this->power_outage_time,
            'power_restored_time' => $this->power_restored_time,
            'power_remarks' => $this->power_remarks,
            'communication_interruption_time' => $this->communication_interruption_time,
            'communication_restored_time' => $this->communication_restored_time,
            'communication_remarks' => $this->communication_remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}