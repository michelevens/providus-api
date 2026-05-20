<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * staff_organization_assignments — many-to-many pivot between users
 * (role=staff) and organizations they're scoped to.
 *
 * Semantics:
 *   - A staff user with ZERO rows here = sees ALL orgs in the agency
 *     (backward compatible — existing staff keep current behavior).
 *   - A staff user with 1+ rows here = sees ONLY data tied to those
 *     orgs. Applies to providers, applications, claims, denials,
 *     payments, etc. — everything that resolves to an organization.
 *
 * agency_id is denormalized onto the pivot so the TenantScope can
 * still gate the relation at the agency level (defense-in-depth: a
 * staff user from agency A can't be assigned to agency B's orgs even
 * if a controller forgets to check).
 *
 * NOT applied to: agency-role+ users (they always see all), or
 * organization/provider-role users (their scope comes from their own
 * organization_id / provider_id columns).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_organization_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One row per (user, org) pair — no dupes.
            $table->unique(['user_id', 'organization_id'], 'staff_org_assignment_unique');
            $table->index(['agency_id', 'user_id']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_organization_assignments');
    }
};
