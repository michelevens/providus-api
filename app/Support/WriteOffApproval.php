<?php

namespace App\Support;

use App\Models\User;

/**
 * Write-off approval gate.
 *
 * Any time a denial, claim, or statement transitions to "written_off",
 * we check the dollar amount. Above the threshold, only agency-owner
 * roles can complete the write-off. Staff billers can request it, but
 * the agency owner must explicitly approve.
 *
 * Without this, a junior biller can quietly write off a $5,000 denial
 * with no second sign-off — a compliance gap most auditors flag.
 *
 * Threshold is configurable per-agency in the future via a config
 * column; today it's a single platform-wide value.
 */
class WriteOffApproval
{
    public const THRESHOLD_USD = 500.0;

    /** Roles allowed to approve write-offs above the threshold. */
    private const APPROVER_ROLES = ['agency', 'owner', 'superadmin'];

    /** Returns true if this user can write off this dollar amount. */
    public static function canApprove(User $user, float $amount): bool
    {
        if ($amount <= self::THRESHOLD_USD) {
            return true; // Below threshold — any staff member can write it off
        }
        return in_array($user->role, self::APPROVER_ROLES, true);
    }

    /** Builds a 403 message for the user when they can't approve. */
    public static function rejectionMessage(float $amount): string
    {
        return sprintf(
            'Write-offs over $%s require agency-owner approval. This amount is $%s. Ask an owner to complete it.',
            number_format(self::THRESHOLD_USD, 0),
            number_format($amount, 2),
        );
    }
}
