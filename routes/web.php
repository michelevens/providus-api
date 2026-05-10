<?php

use Illuminate\Support\Facades\Artisan;
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
        return response()->json(['error' => 'Forbidden — provide ?token=<MIGRATION_TOKEN>'], 403);
    }

    Artisan::call('migrate', ['--force' => true]);
    return response()->json([
        'success' => true,
        'output'  => Artisan::output(),
    ]);
})->middleware('throttle:5,1');
