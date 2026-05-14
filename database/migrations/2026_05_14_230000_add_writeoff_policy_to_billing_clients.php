<?php

// Per-billing-client write-off policy.
//
// Until today, write-off approval was a hardcoded $500 platform-wide
// threshold gated by user role. Two problems with that:
//   1. It treated the AGENCY as the approver. But the money belongs to
//      the ORG (the practice we're billing for) — they should have
//      visibility and control.
//   2. It wasn't configurable per-org. Different practices have very
//      different tolerance for write-offs.
//
// The new model: the billing_client (the org's record on the agency's
// books) carries a policy JSON describing what gets auto-approved,
// what needs org email approval, and what escalates to the agency
// owner if the org doesn't respond.
//
// Shape (all keys optional; missing keys fall back to platform default):
// {
//   "auto_approve": {
//     "categories": ["contractual"],          // always auto-approved
//     "max_amount_per_category": {
//       "small_balance": 25.00,               // small_balance auto under $25
//       "contractual":   null                 // null = no cap
//     },
//     "max_amount_overall": null              // optional ceiling regardless of category
//   },
//   "requires_org_approval": {
//     "categories": ["charity", "bad_debt", "timely_filing", "admin_error", "other"],
//     "min_amount": 0,                        // anything in those categories above this
//     "contact_email": "<override or null>"   // null = use billing_client.contact_email
//   },
//   "fallback_to_owner_after_days": 5,        // null = never fall back
//   "configured_by": "org" | "agency",
//   "configured_at": "<iso>"
// }
//
// configured_by tracks who set the policy — the UI later shows orgs a
// read-only badge when the agency configured it on their behalf (and
// vice versa). configured_at is just for the audit trail.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            $table->jsonb('writeoff_policy_json')->nullable()->after('email_footer');
        });
    }

    public function down(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            $table->dropColumn('writeoff_policy_json');
        });
    }
};
