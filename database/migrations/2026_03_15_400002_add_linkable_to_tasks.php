<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('linkable_type', 30)->nullable()->after('linked_application_id');
            $table->unsignedBigInteger('linkable_id')->nullable()->after('linkable_type');
            $table->index(['linkable_type', 'linkable_id']);
        });

        // Migrate existing linked_application_id data to polymorphic columns
        DB::table('tasks')
            ->whereNotNull('linked_application_id')
            ->update([
                'linkable_type' => 'application',
                'linkable_id'   => DB::raw('linked_application_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['linkable_type', 'linkable_id']);
            $table->dropColumn(['linkable_type', 'linkable_id']);
        });
    }
};
