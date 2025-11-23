<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_assistance_remarks_to_families_and_members.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_families', function (Blueprint $table) {
            // Remove the old remarks field
            $table->dropColumn('remarks');
        });

        Schema::table('incident_families', function (Blueprint $table) {
            // Add new checkbox-style remarks fields
            $table->boolean('assistance_received')->default(false);
            $table->boolean('food_assistance')->default(false);
            $table->boolean('non_food_assistance')->default(false);
            $table->boolean('shelter_assistance')->default(false);
            $table->boolean('medical_assistance')->default(false);
            $table->text('other_remarks')->nullable();
        });

        Schema::table('incident_family_members', function (Blueprint $table) {
            // Add new checkbox-style remarks fields for members
            $table->boolean('assistance_received')->default(false);
            $table->boolean('food_assistance')->default(false);
            $table->boolean('non_food_assistance')->default(false);
            $table->boolean('medical_attention')->default(false);
            $table->boolean('psychological_support')->default(false);
            $table->text('other_remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incident_families', function (Blueprint $table) {
            $table->dropColumn([
                'assistance_received',
                'food_assistance',
                'non_food_assistance',
                'shelter_assistance',
                'medical_assistance',
                'other_remarks'
            ]);
        });

        Schema::table('incident_families', function (Blueprint $table) {
            $table->text('remarks')->nullable();
        });

        Schema::table('incident_family_members', function (Blueprint $table) {
            $table->dropColumn([
                'assistance_received',
                'food_assistance',
                'non_food_assistance',
                'medical_attention',
                'psychological_support',
                'other_remarks'
            ]);
        });
    }
};