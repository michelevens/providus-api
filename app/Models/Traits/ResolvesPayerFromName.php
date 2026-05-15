<?php

// Stamps payer_id on a model whenever payer_name is set/changed and
// payer_id isn't already pinned. Lives behind PayerResolver — see
// app/Services/PayerResolver.php for the matching algorithm.
//
// Fires on:
//   - creating: if payer_name is present and payer_id is null,
//     resolve and stamp.
//   - updating: only if payer_name changed since last save. An
//     explicit payer_id provided by the caller wins (we never
//     overwrite a deliberately-set FK).
//
// Why a trait: Claim and ChargeEntry have identical needs here.
// Trait naming convention (bootXxx) auto-registers the events when
// the trait is `use`d.
//
// Safety:
//   - Soft-fails: if resolve() throws or returns null on a non-empty
//     name we leave payer_id alone rather than blocking the save.
//   - Skips when payer_id is explicitly set — callers importing
//     pre-mapped clearinghouse data can pin the FK themselves.

namespace App\Models\Traits;

use App\Services\PayerResolver;

trait ResolvesPayerFromName
{
    public static function bootResolvesPayerFromName(): void
    {
        static::creating(function ($model) {
            self::resolvePayer($model);
        });

        static::updating(function ($model) {
            // Only re-resolve if the name actually changed. Otherwise
            // a status update would needlessly run the resolver.
            if (!$model->isDirty('payer_name')) {
                return;
            }
            // Never overwrite an FK the caller just set in the same
            // save() — they win. If payer_id is dirty *and* set,
            // honour it.
            $payerIdDirty = $model->isDirty('payer_id');
            $payerIdSet   = $model->payer_id !== null;
            if ($payerIdDirty && $payerIdSet) {
                return;
            }
            self::resolvePayer($model);
        });
    }

    private static function resolvePayer($model): void
    {
        if ($model->payer_id !== null) {
            return;
        }
        $name = $model->payer_name ?? null;
        if (!$name || trim($name) === '') {
            return;
        }
        try {
            $id = PayerResolver::resolve($name);
            if ($id !== null) {
                $model->payer_id = $id;
            }
        } catch (\Throwable $e) {
            // Swallow: a resolver failure shouldn't block claim
            // creation. The unmapped row stays without a payer_id
            // and the backfill command will retry it later.
            report($e);
        }
    }
}
