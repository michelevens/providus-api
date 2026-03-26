<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            if (!Schema::hasColumn('agencies', 'company_display_name')) {
                $table->string('company_display_name')->nullable();
            }
            if (!Schema::hasColumn('agencies', 'email_footer')) {
                $table->text('email_footer')->nullable();
            }
            if (!Schema::hasColumn('agencies', 'custom_domain')) {
                $table->string('custom_domain')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['company_display_name', 'email_footer', 'custom_domain']);
        });
    }
};
