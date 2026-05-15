<?php

// AppealLetterService — generates the body of an appeal letter for a
// claim denial. Picks the right per-category template (based on
// denial_category), resolves merge fields from BrandingResolver +
// claim + payer, returns the rendered text.
//
// Templates live under resources/views/denials/letters/. Each
// category has its own short Blade file that fills in three slots
// in the base template: opening, argument, requested-action. The
// base template is the same across categories — header, claim
// reference block, signature block, footer.
//
// The output is plain text (NOT HTML). PDFs (Phase 2) render from a
// separate Blade template that styles this same content; .docx
// (Phase 4) does the same. The point of plain-text output: the
// LetterModal in V2 lets operators edit the body before saving, and
// editing plain text in a textarea is far simpler than editing HTML.

namespace App\Services;

use App\Models\ClaimDenial;
use Illuminate\Support\Facades\View;

class AppealLetterService
{
    /**
     * Per-category template stub. Maps denial_category to a Blade
     * file under resources/views/denials/letters/categories/. If
     * the file doesn't exist, falls back to 'unknown.blade.php'.
     */
    private const CATEGORY_TEMPLATES = [
        'auto-appealable' => 'auto-appealable',
        'authorization'   => 'authorization',
        'coding-fix'      => 'coding-fix',
        'documentation'   => 'documentation',
        'eligibility'     => 'eligibility',
        'timely_filing'   => 'timely-filing',
        'medical_necessity' => 'medical-necessity',
        'duplicate'       => 'duplicate',
        'other'           => 'unknown',
        'unknown'         => 'unknown',
    ];

    /**
     * Render the appeal letter body for the given denial. Returns
     * plain text suitable for storing in claim_denials.letter_text.
     *
     * The caller (typically the controller for
     * POST /rcm/denials/{id}/generate-letter) is responsible for
     * saving the output to the denial record.
     */
    public function render(ClaimDenial $denial): string
    {
        // Eager-load relations the template needs so we don't make
        // N+1 queries inside the Blade view.
        $denial->loadMissing(['claim.billingClient']);
        $claim = $denial->claim;
        if (!$claim) {
            // Defensive — denial without a claim shouldn't exist, but
            // returning a clear error string beats a 500 if it does.
            return '[ERROR] Denial #' . $denial->id . ' has no linked claim. Cannot generate letter.';
        }

        $billingClient = $claim->billingClient;
        $agency = $claim->billingClient?->agency ?? $claim->agency ?? null;
        // BrandingResolver returns the array shape we need. Falls
        // back through billing-client → agency → platform defaults.
        $brand = $billingClient
            ? \App\Services\BrandingResolver::forBillingClient($billingClient)
            : ($agency ? \App\Services\BrandingResolver::forAgency($agency) : $this->defaultBrand());

        $category = $denial->denial_category ?: 'unknown';
        $categorySlug = self::CATEGORY_TEMPLATES[$category] ?? 'unknown';
        $categoryViewPath = "denials.letters.categories.{$categorySlug}";

        $today = now()->toDateString();

        // Common context every template gets.
        $context = [
            'denial'         => $denial,
            'claim'          => $claim,
            'brand'          => $brand,
            'patient_name'   => $claim->patient_name ?: '[Patient Name]',
            'patient_dob'    => $claim->patient_dob,
            'patient_id'     => $claim->patient_member_id ?: '[Member ID]',
            'claim_number'   => $claim->claim_number ?: ('#' . $claim->id),
            'payer_icn'      => $claim->payer_icn,
            'payer_name'     => $claim->payer_name ?: '[Payer]',
            'service_date'   => $claim->date_of_service,
            'denial_date'    => $denial->denial_date,
            'denial_code'    => $denial->denial_code ?: '[Code]',
            'denial_reason'  => $denial->denial_reason ?: 'reasons stated in the remittance',
            'denied_amount'  => number_format((float) $denial->denied_amount, 2),
            'today'          => $today,
            'appeal_level'   => $denial->appeal_level ?: 1,
        ];

        // Render the per-category argument paragraph. If the
        // template file doesn't exist, fall back to unknown.
        $argument = View::exists($categoryViewPath)
            ? trim(View::make($categoryViewPath, $context)->render())
            : trim(View::make('denials.letters.categories.unknown', $context)->render());

        // Render the base template, passing in the resolved argument
        // as a slot.
        $context['argument'] = $argument;
        return trim(View::make('denials.letters.base', $context)->render());
    }

    private function defaultBrand(): array
    {
        return [
            'name'           => 'Credentik',
            'email'          => null,
            'phone'          => null,
            'address_street' => null,
            'address_city'   => null,
            'address_state'  => null,
            'address_zip'    => null,
        ];
    }
}
