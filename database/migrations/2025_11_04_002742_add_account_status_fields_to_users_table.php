<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add the new columns for account status management
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->onDelete('set null');
            $table->text('deactivation_reason')->nullable()->after('deactivated_by');
            $table->timestamp('reactivated_at')->nullable()->after('deactivation_reason');
            $table->foreignId('reactivated_by')->nullable()->after('reactivated_at')->constrained('users')->onDelete('set null');
            $table->timestamp('last_login_at')->nullable()->after('reactivated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['deactivated_by']);
            $table->dropForeign(['reactivated_by']);
            
            // Then drop the columns
            $table->dropColumn([
                'deactivated_at',
                'deactivated_by',
                'deactivation_reason',
                'reactivated_at',
                'reactivated_by',
                'last_login_at'
            ]);
        });
    }
};