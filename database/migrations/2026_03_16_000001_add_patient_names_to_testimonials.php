<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            if (!Schema::hasColumn('testimonials', 'patient_first_name')) {
                $table->string('patient_first_name', 100)->nullable()->after('patient_email');
            }
            if (!Schema::hasColumn('testimonials', 'patient_last_name')) {
                $table->string('patient_last_name', 100)->nullable()->after('patient_first_name');
            }
        });

        // Migrate existing patient_name data to split fields
        \Illuminate\Support\Facades\DB::table('testimonials')
            ->whereNotNull('patient_name')
            ->whereNull('patient_first_name')
            ->eachById(function ($row) {
                $parts = explode(' ', $row->patient_name, 2);
                \Illuminate\Support\Facades\DB::table('testimonials')
                    ->where('id', $row->id)
                    ->update([
                        'patient_first_name' => $parts[0] ?? '',
                        'patient_last_name' => $parts[1] ?? '',
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn(['patient_first_name', 'patient_last_name']);
        });
    }
};
