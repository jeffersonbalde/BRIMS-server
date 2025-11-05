<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_archive_fields_to_incidents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('status', ['Reported', 'Investigating', 'Resolved', 'Archived'])->default('Reported')->change();
            $table->string('archive_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Add indexes for better performance
            $table->index(['status', 'archived_at']);
            $table->index('archive_reason');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('status', ['Reported', 'Investigating', 'Resolved'])->default('Reported')->change();
            $table->dropColumn(['archive_reason', 'archived_at', 'archived_by']);
        });
    }
};