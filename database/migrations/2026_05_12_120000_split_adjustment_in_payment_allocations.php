<?php

// Split the single `adjustment_amount` column into two: the
// contractual CO-45 write-down (a normal, expected fee-schedule
// adjustment) and the true denied amount (CO-50, CO-29, anything that
// represents lost revenue and not just a contracted discount).
//
// Why: today both CO-45 and CO-50 flow into the same bucket, so
// dashboards report "denied amount" that includes contractual writes
// the agency was never going to collect. Operators can't tell at a
// glance which payers are actually denying vs which are just paying
// at the contracted rate.
//
// The existing adjustment_amount column is kept for backward compat —
// it sums the new fields, so any old caller still sees the same total.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->decimal('contractual_amount', 10, 2)->default(0)->after('adjustment_amount');
            $table->decimal('denied_amount', 10, 2)->default(0)->after('contractual_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropColumn(['contractual_amount', 'denied_amount']);
        });
    }
};
