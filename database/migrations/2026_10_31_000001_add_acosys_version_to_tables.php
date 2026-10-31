<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('acosys_connections', 'acosys_version')) {
            Schema::table('acosys_connections', function (Blueprint $table) {
                $table->string('acosys_version', 10)->default('v4')->after('label');
            });
        }

        if (!Schema::hasColumn('modules', 'acosys_version')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->string('acosys_version', 10)->default('v4')->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('acosys_connections', 'acosys_version')) {
            Schema::table('acosys_connections', function (Blueprint $table) {
                $table->dropColumn('acosys_version');
            });
        }

        if (Schema::hasColumn('modules', 'acosys_version')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->dropColumn('acosys_version');
            });
        }
    }
};
