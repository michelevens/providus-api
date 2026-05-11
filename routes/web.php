<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// One-shot migration runner — gated by a token. Set MIGRATION_TOKEN in
// Railway env vars; hit /run-migrations?token=… to run pending migrations
// from a browser when the Railway shell isn't readily available.
//
// Hardening (from security review 2026-05-10):
//   - hash_equals does constant-time comparison so a network attacker
//     can't time-attack the token char-by-char
//   - throttle:5,1 rate-limits to 5 attempts per minute per IP — combined
//     with token entropy, this is unbrute-forceable in practice
//   - Token MUST be at least 32 random bytes (i.e. set via
//     `bin2hex(random_bytes(32))` and copied to Railway env)
Route::get('/run-migrations', function (\Illuminate\Http\Request $request) {
    $expected = env('MIGRATION_TOKEN');
    $provided = (string) $request->query('token', '');
    // hash_equals requires both strings to be non-empty; short-circuit when
    // env is unset so we don't accidentally allow empty-token access.
    if (!$expected || $provided === '' || !hash_equals((string) $expected, $provided)) {
        // Log failed attempts so we can spot brute-force in production logs.
        // No token value in the log — only the fact that an attempt happened.
        Log::warning('run-migrations: forbidden attempt', [
            'ip'        => $request->ip(),
            'userAgent' => substr((string) $request->userAgent(), 0, 200),
        ]);
        return response()->json(['error' => 'Forbidden — provide ?token=<MIGRATION_TOKEN>'], 403);
    }

    // Audit trail: every successful invocation is logged with the requester
    // IP and the list of pending migrations that will run. This is the
    // primary defense against a CI / repo-compromise scenario where an
    // attacker pushes a malicious migration — they'd still need the token,
    // but if they have it, we at least have a record of when the SQL ran.
    // The `--pretend` dry-run shows what `migrate` is about to execute.
    Artisan::call('migrate', ['--force' => true, '--pretend' => true]);
    $pendingPlan = Artisan::output();
    Log::warning('run-migrations: invocation authorized', [
        'ip'        => $request->ip(),
        'userAgent' => substr((string) $request->userAgent(), 0, 200),
        'plan'      => $pendingPlan,
    ]);

    Artisan::call('migrate', ['--force' => true]);
    $output = Artisan::output();
    Log::info('run-migrations: migrate completed', ['output' => $output]);

    return response()->json([
        'success' => true,
        'output'  => $output,
    ]);
})->middleware('throttle:5,1');
