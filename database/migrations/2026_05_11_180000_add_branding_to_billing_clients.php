<?php

// Per-practice branding override.
//
// Today every patient-facing artifact (statement emails, payment-link
// landing, print headers) uses the AGENCY's brand. But agencies typically
// want to be INVISIBLE to patients — patients of "Acme Pediatrics" know
// Acme, not the billing company that handles Acme's RCM.
//
// Adding optional branding fields on billing_clients (the agency's view
// of a client practice). When any of these are set, BrandingResolver
// surfaces the practice's brand instead of the agency's on artifacts
// scoped to that billing_client.
//
// Fallback chain: BillingClient.branding -> Agency.branding -> Credentik
// default. Fields are nullable on purpose so a half-configured practice
// degrades gracefully (e.g. logo set but no accent color → use practice
// logo over agency colors).
//
// Visible columns deliberately mirror the Agency model's branding so
// resolver code is symmetric.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            // Identity overrides — what the patient sees
            $table->string('display_name')->nullable()->after('organization_name');

            // Brand colors (hex). Empty = inherit from agency.
            $table->string('primary_color', 7)->nullable()->after('display_name');
            $table->string('accent_color', 7)->nullable()->after('primary_color');

            // Logo — stored as URL (S3/CDN) or data: URI for small files.
            // Matches Agency.logo_url to keep template code symmetric.
            $table->text('logo_url')->nullable()->after('accent_color');

            // Customer contact surfaces used in email footers + pay landing
            $table->string('public_email')->nullable()->after('logo_url');
            $table->string('public_phone', 30)->nullable()->after('public_email');
            $table->string('address_street')->nullable()->after('public_phone');
            $table->string('address_city', 100)->nullable()->after('address_street');
            $table->string('address_state', 2)->nullable()->after('address_city');
            $table->string('address_zip', 12)->nullable()->after('address_state');

            // Custom footer line in branded emails — e.g. legal disclaimer,
            // tax ID, NPI for billing transparency. Free text.
            $table->text('email_footer')->nullable()->after('address_zip');
        });
    }

    public function down(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'primary_color',
                'accent_color',
                'logo_url',
                'public_email',
                'public_phone',
                'address_street',
                'address_city',
                'address_state',
                'address_zip',
                'email_footer',
            ]);
        });
    }
};
