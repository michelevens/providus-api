<?php

// End-to-end denial workflow test. Runs through every phase 1–5
// endpoint against a freshly-seeded test denial, then cleans up
// (deletes denial row + R2 attachments) so production state is
// unchanged.
//
// Run on prod (Railway): railway ssh "php /app/scripts/test_denial_flow.php"
//
// Each step prints a one-line status. Stops on the first failure.

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Claim;
use App\Models\ClaimDenial;
use App\Models\User;
use App\Services\AppealLetterService;
use App\Http\Controllers\Api\V1\RcmController;
use App\Http\Controllers\Api\V1\DenialAttachmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

function step(string $label, $cb) {
    echo "── " . str_pad($label, 50, ".") . " ";
    try {
        $result = $cb();
        echo "OK";
        if (is_string($result) && $result !== '') echo " ($result)";
        echo PHP_EOL;
        return true;
    } catch (\Throwable $e) {
        echo "FAIL: " . $e->getMessage() . PHP_EOL;
        echo "       at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
        exit(1);
    }
}

// ── 0. Pick a real user + real claim for context ──
$user = User::where('agency_id', 1)->where('role', 'agency_owner')->first()
    ?? User::where('agency_id', 1)->first();
if (!$user) { echo "no user found in agency 1\n"; exit(1); }
echo "Acting as user #{$user->id} ({$user->email}, role={$user->role})\n";
Auth::loginUsingId($user->id);

$claim = Claim::find(3635);
if (!$claim) { echo "claim 3635 not found\n"; exit(1); }
echo "Using claim #{$claim->id} ({$claim->claim_number}, {$claim->patient_name}, {$claim->payer_name})\n";
echo PHP_EOL;

// Helper to build a Request that already has $user as ->user(). The
// controller methods call $request->user()->effectiveAgencyId($request)
// so we replicate that here.
function reqAs($user, array $payload = [], array $query = []): Request {
    $r = Request::create('/', 'POST', $payload + $query);
    $r->setUserResolver(fn() => $user);
    foreach ($query as $k => $v) $r->query->set($k, $v);
    return $r;
}

$rcm = new RcmController();
$att = new DenialAttachmentController();

// ── 1. Seed a fresh test denial ──
$denial = step("Seed test denial against claim #{$claim->id}", function () use ($claim, $user) {
    return ClaimDenial::create([
        'agency_id' => $claim->agency_id,
        'claim_id' => $claim->id,
        'denial_category' => 'medical-necessity',
        'denial_date' => now()->subDays(3)->toDateString(),
        'denial_code' => 'CO-50',
        'reason_code' => 'CO-50',
        'denial_reason' => 'Services not medically necessary per payer guidelines',
        'denied_amount' => 1234.56,
        'status' => 'new',
        'appeal_level' => 1,
        'appeal_deadline' => now()->addDays(60)->toDateString(),
    ]);
}) ? $denial = ClaimDenial::where('agency_id', $claim->agency_id)
       ->where('denial_code', 'CO-50')
       ->where('denied_amount', 1234.56)
       ->orderByDesc('id')
       ->first() : null;
echo "       denial id = #{$denial->id}\n";

// ── 2. Triage ──
step('POST /triage', function () use ($rcm, $denial, $user) {
    $r = reqAs($user, ['denial_category' => 'medical-necessity', 'priority' => 'high']);
    $res = $rcm->triageDenial($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    return "status=" . ($body['data']['status'] ?? '?') . " category=" . ($body['data']['denial_category'] ?? '?');
});

// ── 3. Generate letter (server picks Blade template) ──
step('POST /generate-letter', function () use ($rcm, $denial, $user) {
    $r = reqAs($user);
    $res = $rcm->generateLetter($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    $len = strlen($body['data']['letter_text'] ?? '');
    return "letter_text length = {$len} chars";
});

// ── 4. Edit the letter (operator tweaks) ──
step('POST /draft-letter (operator edits)', function () use ($rcm, $denial, $user) {
    $fresh = ClaimDenial::find($denial->id);
    $edited = $fresh->letter_text . "\n\n[EDIT: Operator added a sentence at " . now() . "]";
    $r = reqAs($user, ['letter_text' => $edited]);
    $res = $rcm->draftLetter($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    $saved = ClaimDenial::find($denial->id);
    if (!str_contains($saved->letter_text, '[EDIT:')) {
        throw new \RuntimeException('edit was not persisted');
    }
    return "saved (" . strlen($saved->letter_text) . " chars)";
});

// ── 5. Render PDF ──
step('GET /pdf (DomPDF)', function () use ($rcm, $denial, $user) {
    $r = reqAs($user);
    $res = $rcm->denialPdf($r, $denial->id);
    $body = $res->getContent();
    if (substr($body, 0, 5) !== '%PDF-') throw new \RuntimeException('not a PDF');
    return "PDF " . strlen($body) . " bytes";
});

// ── 6. Render DOCX (PhpWord) ──
step('GET /docx (PhpWord)', function () use ($rcm, $denial, $user) {
    $r = reqAs($user);
    $res = $rcm->denialDocx($r, $denial->id);
    $body = $res->getContent();
    // .docx is a zip file (PK signature)
    if (substr($body, 0, 2) !== 'PK') throw new \RuntimeException('not a DOCX zip');
    return "DOCX " . strlen($body) . " bytes";
});

// ── 7. Upload attachments (multipart → R2) ──
$tmpA = sys_get_temp_dir() . '/test-clinical-notes.pdf';
$tmpB = sys_get_temp_dir() . '/test-eob.pdf';
file_put_contents($tmpA, "%PDF-1.4\nfake clinical notes content for test\n");
file_put_contents($tmpB, "%PDF-1.4\nfake EOB content for test\n");

step('POST /attachments (upload 2 files → R2)', function () use ($att, $denial, $user, $tmpA, $tmpB) {
    $a = new UploadedFile($tmpA, 'clinical-notes.pdf', 'application/pdf', null, true);
    $b = new UploadedFile($tmpB, 'eob.pdf', 'application/pdf', null, true);
    $r = reqAs($user);
    $r->files->set('files', [$a, $b]);
    $r->request->set('labels', ['Clinical notes', 'Original EOB']);
    $res = $att->store($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    $count = count($body['data']);
    return "{$count} attachments now stored";
});

// ── 8. List attachments (signed URLs) ──
$attKeys = [];
step('GET /attachments (signed URLs)', function () use ($att, $denial, $user, &$attKeys) {
    $r = reqAs($user);
    $res = $att->index($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    foreach ($body['data'] as $a) {
        $attKeys[] = $a['key'];
        if (empty($a['signed_url'])) throw new \RuntimeException('missing signed_url for ' . $a['label']);
    }
    return count($body['data']) . " items with signed URLs";
});

// ── 8b. Actually fetch the signed URL to confirm R2 hands the bytes back ──
step('GET signed R2 URL (real fetch)', function () use ($att, $denial, $user) {
    $r = reqAs($user);
    $res = $att->index($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    $url = $body['data'][0]['signed_url'];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $bytes = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) throw new \RuntimeException("R2 returned HTTP {$code}");
    return "R2 returned " . strlen($bytes) . " bytes";
});

// ── 9. Mark sent ──
step('POST /mark-sent (method=portal)', function () use ($rcm, $denial, $user) {
    $r = reqAs($user, ['method' => 'portal']);
    $res = $rcm->markLetterSent($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    return "status=" . $body['data']['status'] . " method=" . $body['data']['letter_sent_method'];
});

// ── 10. Record response ──
step('POST /record-response (overturned)', function () use ($rcm, $denial, $user) {
    $r = reqAs($user, [
        'outcome' => 'overturned',
        'response_text' => 'Payer overturned on review. Will reprocess at allowed amount.',
    ]);
    $res = $rcm->recordResponse($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    return "outcome=" . ($body['data']['payer_response_outcome'] ?? '?');
});

// ── 11. Resolve as recovered ──
step('POST /resolve (recovered $987.65)', function () use ($rcm, $denial, $user) {
    $r = reqAs($user, [
        'outcome' => 'recovered',
        'recovered_amount' => 987.65,
        'resolution_notes' => 'Test resolution',
    ]);
    $res = $rcm->resolveDenial($r, $denial->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    return "final status=" . $body['data']['status'] . " recovered=" . $body['data']['recovered_amount'];
});

// ── 12. Read it back in the recovery report ──
step('GET /recovery-report (last 30 days)', function () use ($rcm, $user) {
    $r = reqAs($user, [], ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()]);
    $res = $rcm->denialRecoveryReport($r);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    $t = $body['data']['totals'];
    return sprintf(
        "totals: %d denials, $%s denied, $%s recovered, rate=%s%%",
        $t['count_total'], $t['denied_amount_total'], $t['recovered_amount'], $t['recovery_rate_pct']
    );
});

// ── 13. Test escalation path (separate from resolution above) ──
// Spawn a level-2 denial to verify the escalation endpoint still works
// even though we've resolved the original. Escalation is allowed from
// any non-terminal state per controller; let's seed another row.
$denial2 = step('Seed second test denial for escalation', function () use ($claim) {
    return ClaimDenial::create([
        'agency_id' => $claim->agency_id,
        'claim_id' => $claim->id,
        'denial_category' => 'timely_filing',
        'denial_date' => now()->subDays(2)->toDateString(),
        'denial_code' => 'CO-29',
        'denial_reason' => 'Timely filing exceeded',
        'denied_amount' => 500.00,
        'status' => 'letter_sent',
        'appeal_level' => 1,
    ]);
}) ? ClaimDenial::where('agency_id', $claim->agency_id)
       ->where('denial_code', 'CO-29')
       ->where('denied_amount', 500.00)
       ->orderByDesc('id')
       ->first() : null;
echo "       second denial id = #{$denial2->id}\n";

step('POST /escalate (level 1 → 2)', function () use ($rcm, $denial2, $user) {
    $r = reqAs($user, ['notes' => 'Test escalation to L2']);
    $res = $rcm->escalateDenial($r, $denial2->id);
    $body = json_decode($res->getContent(), true);
    if (!$body['success']) throw new \RuntimeException(json_encode($body));
    return "L2 row id=" . $body['data']['id'] . " level=" . $body['data']['appeal_level'];
});

// ── 14. CLEANUP ──
echo PHP_EOL . "── Cleanup ──" . PHP_EOL;
// Delete R2 objects
foreach ($attKeys as $key) {
    Storage::disk('r2')->delete($key);
    echo "       R2 delete: {$key}\n";
}
// Delete denial rows (and the escalation chain)
$d2 = ClaimDenial::find($denial2->id);
foreach (ClaimDenial::where('parent_denial_id', $d2->id)->get() as $child) {
    echo "       delete escalation child #{$child->id}\n";
    $child->delete();
}
echo "       delete denial #{$d2->id}\n";
$d2->delete();
echo "       delete denial #{$denial->id}\n";
$d = ClaimDenial::find($denial->id);
$d->delete();
@unlink($tmpA); @unlink($tmpB);

echo PHP_EOL . "ALL TESTS PASSED" . PHP_EOL;
