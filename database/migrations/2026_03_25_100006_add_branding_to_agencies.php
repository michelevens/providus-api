<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('company_display_name')->nullable()->after('name');
            $table->text('email_footer')->nullable()->after('accent_color');
            $table->string('custom_domain')->nullable()->after('email_footer');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['company_display_name', 'email_footer', 'custom_domain']);
        });
    }
};
