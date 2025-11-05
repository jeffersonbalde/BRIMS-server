<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_unarchive_history_to_incidents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->json('unarchive_history')->nullable()->after('archive_reason');
            $table->text('unarchive_reason')->nullable()->after('unarchive_history');
            $table->timestamp('unarchived_at')->nullable()->after('unarchive_reason');
            $table->foreignId('unarchived_by')->nullable()->constrained('users')->onDelete('set null')->after('unarchived_at');
            
            $table->index('unarchived_at');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['unarchive_history', 'unarchive_reason', 'unarchived_at', 'unarchived_by']);
        });
    }
};