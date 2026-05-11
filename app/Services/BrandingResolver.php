<?php

// Resolves the right brand to use on a customer-facing artifact.
//
// Precedence chain:
//   1. BillingClient.branding fields (set by agency operator per-practice)
//   2. Agency.branding fields (set in /settings/branding)
//   3. Credentik platform defaults (cyan, no logo, "Credentik" name)
//
// Returns a plain object/array with a stable shape so Blade templates and
// the V2 frontend can render without knowing which level fired. Always
// returns SOMETHING — never null — even on missing inputs.
//
// Usage:
//   $brand = BrandingResolver::forStatement($statement);
//   $brand = BrandingResolver::forInvoice($invoice);
//   $brand = BrandingResolver::forPaymentLink($paymentLink);
//   $brand = BrandingResolver::forBillingClient($billingClient);
//
// Each entry-point figures out the right BillingClient + Agency context
// and calls the private resolver. Returning the same shape from all of
// them means templates don't branch.

namespace App\Services;

use App\Models\Agency;
use App\Models\BillingClient;
use App\Models\Invoice;
use App\Models\PatientStatement;
use App\Models\PaymentLink;

class BrandingResolver
{
    private const CREDENTIK_DEFAULT_PRIMARY = '#0891b2';
    private const CREDENTIK_DEFAULT_ACCENT = '#06b6d4';

    /** @return array{primary_color:string,accent_color:string,logo_url:?string,name:string,email:?string,phone:?string,address_street:?string,address_city:?string,address_state:?string,address_zip:?string,email_footer:?string,source:string} */
    public static function forStatement(PatientStatement $statement): array
    {
        $billingClient = $statement->billing_client_id
            ? BillingClient::find($statement->billing_client_id)
            : null;
        $agency = Agency::find($statement->agency_id);
        return self::resolve($billingClient, $agency);
    }

    /** @return array<string,mixed> */
    public static function forInvoice(Invoice $invoice): array
    {
        // Invoices are agency-to-practice billing — the AGENCY is the
        // sender, so the agency brand is correct. We intentionally do
        // NOT walk down to BillingClient here even though the invoice
        // is tied to one. The caller (practice) needs to see who they
        // owe money to — that's the agency.
        $agency = Agency::find($invoice->agency_id);
        return self::resolve(null, $agency);
    }

    /** @return array<string,mixed> */
    public static function forPaymentLink(PaymentLink $link): array
    {
        $billingClient = $link->billing_client_id
            ? BillingClient::find($link->billing_client_id)
            : null;
        $agency = Agency::find($link->agency_id);
        return self::resolve($billingClient, $agency);
    }

    /** @return array<string,mixed> */
    public static function forBillingClient(BillingClient $billingClient): array
    {
        $agency = Agency::find($billingClient->agency_id);
        return self::resolve($billingClient, $agency);
    }

    /** @return array<string,mixed> */
    public static function forAgency(Agency $agency): array
    {
        return self::resolve(null, $agency);
    }

    /** @return array<string,mixed> */
    private static function resolve(?BillingClient $bc, ?Agency $agency): array
    {
        // Walk the precedence chain field-by-field. A practice might
        // set ONE field (e.g. logo) and inherit the rest from the
        // agency — that's by design.
        $primary = $bc?->primary_color
            ?: $agency?->primary_color
            ?: self::CREDENTIK_DEFAULT_PRIMARY;

        $accent = $bc?->accent_color
            ?: $agency?->accent_color
            ?: self::CREDENTIK_DEFAULT_ACCENT;

        $logo = $bc?->logo_url ?: $agency?->logo_url;

        $name = $bc?->display_name
            ?: $bc?->organization_name
            ?: $agency?->company_display_name
            ?: $agency?->name
            ?: 'Credentik';

        $email = $bc?->public_email ?: $agency?->email;
        $phone = $bc?->public_phone ?: $agency?->phone;
        $addressStreet = $bc?->address_street ?: $agency?->address_street;
        $addressCity = $bc?->address_city ?: $agency?->address_city;
        $addressState = $bc?->address_state ?: $agency?->address_state;
        $addressZip = $bc?->address_zip ?: $agency?->address_zip;
        $emailFooter = $bc?->email_footer ?: $agency?->email_footer;

        // 'source' lets templates/callers tell which level fired the
        // brand — useful for debug + for the V2 preview UI ("you're
        // seeing the practice override" vs "you're seeing the agency
        // brand").
        $source = $bc && $bc->primary_color ? 'billing_client'
            : ($agency && $agency->primary_color ? 'agency' : 'default');

        return [
            'primary_color' => $primary,
            'accent_color' => $accent,
            'logo_url' => $logo,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address_street' => $addressStreet,
            'address_city' => $addressCity,
            'address_state' => $addressState,
            'address_zip' => $addressZip,
            'email_footer' => $emailFooter,
            'source' => $source,
        ];
    }

    /**
     * Convert the array into an Agency-shaped stdClass for Blade
     * templates that still type-hint $agency. Keeps the layout from
     * needing to know about the override.
     */
    public static function asAgencyObject(array $brand): object
    {
        return (object) [
            'name' => $brand['name'],
            'primary_color' => $brand['primary_color'],
            'accent_color' => $brand['accent_color'],
            'logo_url' => $brand['logo_url'],
            'email' => $brand['email'],
            'phone' => $brand['phone'],
            'address_street' => $brand['address_street'],
            'address_city' => $brand['address_city'],
            'address_state' => $brand['address_state'],
            'address_zip' => $brand['address_zip'],
            'email_footer' => $brand['email_footer'],
        ];
    }
}
