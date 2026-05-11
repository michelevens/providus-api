<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Operator-only CRM notes on a tenant. Things like "considering
// downgrading", "renewal call scheduled for Q3", "billing dispute
// in progress" — context that lives outside the normal tenant
// data flows and shouldn't be visible to the tenant themselves.
//
// NOT scoped via BelongsToAgency — these are written by superadmins
// and read only via /admin/* endpoints. agency_id is the SUBJECT
// of the note, not the tenant who owns it.

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agency_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agency_id')->index();
            $table->unsignedBigInteger('author_user_id')->nullable();
            $table->string('author_email')->nullable();
            $table->text('body');
            // Free-text tag (e.g. "renewal", "billing", "support") so
            // the operator can filter at a glance. Indexed for filter.
            $table->string('tag', 40)->nullable()->index();
            // Pinned notes float to the top of the agency's note list.
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_notes');
    }
};
