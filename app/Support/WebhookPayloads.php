<?php

namespace App\Support;

use App\Models\Claim;
use App\Models\ClaimPayment;

/**
 * Canonical webhook payload shapes for claim and payment events. Centralized
 * so all dispatch sites emit identical schemas — receivers can rely on the
 * field set being stable per event type.
 */
class WebhookPayloads
{
    public static function claim(Claim $claim, array $extras = []): array
    {
        return array_merge([
            'claim_id'          => $claim->id,
            'claim_number'      => $claim->claim_number,
            'billing_client_id' => $claim->billing_client_id,
            'provider_id'       => $claim->provider_id,
            'provider_name'     => $claim->provider_name,
            'patient_name'      => $claim->patient_name,
            'payer_name'        => $claim->payer_name,
            'date_of_service'   => $claim->date_of_service ? (string) $claim->date_of_service : null,
            'total_charges'     => $claim->total_charges,
            'total_paid'        => $claim->total_paid,
            'balance'           => $claim->balance,
            'status'            => $claim->status,
            'denial_reason'     => $claim->denial_reason,
        ], $extras);
    }

    public static function payment(ClaimPayment $payment): array
    {
        return [
            'payment_id'        => $payment->id,
            'billing_client_id' => $payment->billing_client_id,
            'payer_name'        => $payment->payer_name,
            'payment_type'      => $payment->payment_type,
            'check_number'      => $payment->check_number,
            'payment_date'      => $payment->payment_date ? (string) $payment->payment_date : null,
            'total_amount'      => $payment->total_amount,
            'remaining_amount'  => $payment->remaining_amount,
            'status'            => $payment->status,
        ];
    }
}
