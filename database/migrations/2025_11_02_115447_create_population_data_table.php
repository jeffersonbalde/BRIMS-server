<?php
// database/migrations/2024_01_01_000000_create_population_data_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('population_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->onDelete('cascade');
            
            // Displacement and Assistance
            $table->integer('displaced_families')->default(0);
            $table->integer('displaced_persons')->default(0);
            $table->integer('families_requiring_assistance')->default(0);
            $table->integer('families_assisted')->default(0);
            
            // Gender Distribution
            $table->integer('male_count')->default(0);
            $table->integer('female_count')->default(0);
            $table->integer('lgbtqia_count')->default(0);
            
            // Civil Status
            $table->integer('single_count')->default(0);
            $table->integer('married_count')->default(0);
            $table->integer('widowed_count')->default(0);
            $table->integer('separated_count')->default(0);
            $table->integer('live_in_count')->default(0);
            
            // Special Groups
            $table->integer('pwd_count')->default(0);
            $table->integer('pregnant_count')->default(0);
            $table->integer('elderly_count')->default(0);
            $table->integer('lactating_mother_count')->default(0);
            $table->integer('solo_parent_count')->default(0);
            $table->integer('indigenous_people_count')->default(0);
            $table->integer('lgbtqia_persons_count')->default(0);
            $table->integer('child_headed_household_count')->default(0);
            $table->integer('gbv_victims_count')->default(0);
            $table->integer('four_ps_beneficiaries_count')->default(0);
            $table->integer('single_headed_family_count')->default(0);
            
            // Age Distribution
            $table->integer('infant_count')->default(0);
            $table->integer('toddler_count')->default(0);
            $table->integer('preschooler_count')->default(0);
            $table->integer('school_age_count')->default(0);
            $table->integer('teen_age_count')->default(0);
            $table->integer('adult_count')->default(0);
            $table->integer('elderly_age_count')->default(0);
            
            // Religion
            $table->integer('christian_count')->default(0);
            $table->integer('subanen_ip_count')->default(0);
            $table->integer('moro_count')->default(0);
            
            $table->timestamps();
            
            // One-to-one relationship with incidents
            $table->unique('incident_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('population_data');
    }
};