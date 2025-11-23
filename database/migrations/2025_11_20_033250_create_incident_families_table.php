<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_incident_families_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->onDelete('cascade');
            $table->integer('family_number');
            $table->integer('family_size');
            $table->string('evacuation_center')->nullable();
            $table->string('alternative_location')->nullable();
            $table->enum('assistance_given', ['F', 'NFI'])->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index('incident_id');
            $table->index('family_number');
        });

        Schema::create('incident_family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('incident_families')->onDelete('cascade');
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->enum('position_in_family', [
                'Head (Father)',
                'Head (Mother)', 
                'Head (Solo Parent)',
                'Head (Single)',
                'Head (Child)',
                'Member'
            ]);
            $table->enum('sex_gender_identity', [
                'Male',
                'Female',
                'LGBTQIA+ / Other (self-identified)',
                'Prefer not to say'
            ]);
            $table->integer('age');
            $table->enum('category', [
                'Infant (0-6 mos)',
                'Toddlers (7 mos- 2 y/o)',
                'Preschooler (3-5 y/o)',
                'School Age (6-12 y/o)',
                'Teen Age (13-17 y/o)',
                'Adult (18-59 y/o)',
                'Elderly (60 and above)'
            ]);
            $table->enum('civil_status', [
                'Single',
                'Married',
                'Widowed',
                'Separated',
                'Live-In/Cohabiting'
            ]);
            $table->enum('ethnicity', [
                'CHRISTIAN',
                'SUBANEN (IPs)',
                'MORO'
            ]);
            $table->json('vulnerable_groups')->nullable();
            $table->enum('casualty', ['Dead', 'Injured/ill', 'Missing'])->nullable();
            $table->enum('displaced', ['Y', 'N'])->default('N');
            $table->string('pwd_type')->nullable();
            $table->timestamps();
            
            $table->index('family_id');
            $table->index('last_name');
            $table->index('position_in_family');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_family_members');
        Schema::dropIfExists('incident_families');
    }
};