<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('infrastructure_status')) {
            Schema::rename('infrastructure_status', 'infrastructure_statuses');
        }
    }

    public function down()
    {
        if (Schema::hasTable('infrastructure_statuses')) {
            Schema::rename('infrastructure_statuses', 'infrastructure_status');
        }
    }
};