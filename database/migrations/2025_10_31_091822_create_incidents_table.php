<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_incidents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->enum('incident_type', ['Flood', 'Landslide', 'Fire', 'Earthquake', 'Vehicular']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location');
            $table->string('barangay'); // ADD THIS
            $table->string('purok')->nullable(); // ADD THIS
            $table->dateTime('incident_date');
            $table->enum('severity', ['Low', 'Medium', 'High', 'Critical']);
            $table->enum('status', ['Reported', 'Investigating', 'Resolved'])->default('Reported');
            $table->integer('affected_families')->default(0);
            $table->integer('affected_individuals')->default(0);
            $table->json('casualties')->nullable();
            $table->text('response_actions')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('reported_by');
            $table->index('incident_type');
            $table->index('severity');
            $table->index('status');
            $table->index('barangay'); // ADD THIS
            $table->index('purok'); // ADD THIS
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};