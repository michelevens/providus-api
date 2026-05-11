<?php
// Stripe event idempotency log.
//
// Why this exists: Stripe retries every webhook delivery for up to 3 days
// on any non-2xx response. The PaymentLink handler has a status-machine
// guard (pending → paid only), but the subscription handlers
// (customer.subscription.updated / .deleted, invoice.payment_failed
// / .succeeded) call `$agency->update(...)` unconditionally. A late retry
// of `customer.subscription.deleted` for an agency that has since
// re-subscribed would overwrite live state with stale "canceled". Plus a
// retry after a partial failure (e.g. DB hiccup between the agency
// update and the trial-end fetch) re-runs the whole side-effect.
//
// Simplest fix: record every Stripe event_id we've already processed and
// short-circuit on second sight. Tiny table, unique key on event_id.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_event_log', function (Blueprint $table) {
            $table->id();
            // Stripe event ids are prefixed `evt_` + 24 alphanumerics; 80 is
            // a generous cap that survives any future format change.
            $table->string('event_id', 80)->unique();
            $table->string('event_type', 80)->nullable();
            $table->timestamp('processed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_event_log');
    }
};
