<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_configs', function (Blueprint $table) {
            $table->jsonb('waves')->nullable()->after('elig_monthly_limit');
        });
    }

    public function down(): void
    {
        Schema::table('agency_configs', function (Blueprint $table) {
            $table->dropColumn('waves');
        });
    }
};
