<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// One-shot migration runner — gated by a token. Set MIGRATION_TOKEN in
// Railway env vars; hit /run-migrations?token=… to run pending migrations
// from a browser when the Railway shell isn't readily available.
Route::get('/run-migrations', function (\Illuminate\Http\Request $request) {
    $expected = env('MIGRATION_TOKEN');
    if (!$expected || $request->query('token') !== $expected) {
        return response()->json(['error' => 'Forbidden — provide ?token=<MIGRATION_TOKEN>'], 403);
    }

    Artisan::call('migrate', ['--force' => true]);
    return response()->json([
        'success' => true,
        'output'  => Artisan::output(),
    ]);
});
