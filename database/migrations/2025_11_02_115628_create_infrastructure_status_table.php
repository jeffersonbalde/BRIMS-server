<?php
// database/migrations/2024_01_01_000001_create_infrastructure_statuses_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('infrastructure_statuses', function (Blueprint $table) { // Changed to plural
            $table->id();
            $table->foreignId('incident_id')->constrained()->onDelete('cascade');
            
            // Roads and Bridges
            $table->enum('roads_bridges_status', ['PASSABLE', 'NOT_PASSABLE'])->default('PASSABLE');
            $table->timestamp('roads_reported_not_passable')->nullable();
            $table->timestamp('roads_reported_passable')->nullable();
            $table->text('roads_remarks')->nullable();
            
            // Power Supply
            $table->timestamp('power_outage_time')->nullable();
            $table->timestamp('power_restored_time')->nullable();
            $table->text('power_remarks')->nullable();
            
            // Communication Lines
            $table->timestamp('communication_interruption_time')->nullable();
            $table->timestamp('communication_restored_time')->nullable();
            $table->text('communication_remarks')->nullable();
            
            $table->timestamps();
            
            // One-to-one relationship with incidents
            $table->unique('incident_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('infrastructure_statuses'); // Changed to plural
    }
};