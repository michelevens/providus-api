<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contract::where('agency_id', $request->user()->agency_id)
            ->with(['organization:id,name', 'provider:id,first_name,last_name,credentials', 'items']);

        if ($status = $request->input('status')) $query->where('status', $status);
        if ($orgId = $request->input('organization_id')) $query->where('organization_id', $orgId);

        $contracts = $query->orderByDesc('created_at')->paginate(50);
        return response()->json(['success' => true, 'data' => $contracts]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contract = Contract::where('agency_id', $request->user()->agency_id)
            ->with(['organization:id,name,email,phone', 'provider:id,first_name,last_name,credentials,email', 'items.serviceCatalog', 'creator:id,first_name,last_name'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $contract]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'organization_id' => 'nullable|exists:organizations,id',
            'provider_id' => 'nullable|exists:providers,id',
            'client_name' => 'nullable|string|max:200',
            'client_email' => 'nullable|email',
            'client_address' => 'nullable|string',
            'effective_date' => 'required|date',
            'expiration_date' => 'nullable|date|after_or_equal:effective_date',
            'auto_renew' => 'nullable|boolean',
            'renewal_terms' => 'nullable|string',
            'billing_frequency' => 'nullable|in:one_time,monthly,quarterly,annually',
            'payment_terms' => 'nullable|string|max:100',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'terms_and_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.service_catalog_id' => 'nullable|integer',
            'items.*.frequency' => 'nullable|string',
        ]);

        $count = Contract::withTrashed()->where('agency_id', $request->user()->agency_id)->count() + 1;
        $number = 'CTR-' . str_pad($count, 5, '0', STR_PAD_LEFT);

        $contract = Contract::create([
            'agency_id' => $request->user()->agency_id,
            'contract_number' => $number,
            'status' => 'draft',
            'token' => Contract::generateToken(),
            'organization_id' => $request->organization_id,
            'provider_id' => $request->provider_id,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
            'client_address' => $request->client_address,
            'title' => $request->title,
            'description' => $request->description,
            'effective_date' => $request->effective_date,
            'expiration_date' => $request->expiration_date,
            'auto_renew' => $request->boolean('auto_renew'),
            'renewal_terms' => $request->renewal_terms,
            'billing_frequency' => $request->billing_frequency,
            'payment_terms' => $request->payment_terms,
            'tax_rate' => $request->input('tax_rate', 0),
            'discount_amount' => $request->input('discount_amount', 0),
            'terms_and_conditions' => $request->terms_and_conditions,
            'notes' => $request->notes,
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->items as $i => $item) {
            ContractItem::create([
                'contract_id' => $contract->id,
                'service_catalog_id' => $item['service_catalog_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'frequency' => $item['frequency'] ?? null,
                'sort_order' => $i,
            ]);
        }

        $contract->recalculate();
        $contract->load('items');

        return response()->json(['success' => true, 'data' => $contract], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contract = Contract::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:draft,sent,viewed,accepted,active,expired,terminated',
            'client_name' => 'sometimes|nullable|string|max:200',
            'client_email' => 'sometimes|nullable|email',
            'client_address' => 'sometimes|nullable|string',
            'effective_date' => 'sometimes|date',
            'expiration_date' => 'sometimes|nullable|date',
            'auto_renew' => 'sometimes|boolean',
            'billing_frequency' => 'sometimes|nullable|string',
            'payment_terms' => 'sometimes|nullable|string',
            'tax_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'discount_amount' => 'sometimes|nullable|numeric|min:0',
            'terms_and_conditions' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'accepted_at' => 'sometimes|nullable|date',
            'accepted_by_name' => 'sometimes|nullable|string|max:200',
            'accepted_by_email' => 'sometimes|nullable|email|max:200',
            'accepted_by_title' => 'sometimes|nullable|string|max:200',
            'items' => 'sometimes|array',
            'items.*.description' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|numeric|min:0',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

        $contract->update($request->only([
            'title', 'status', 'client_name', 'client_email', 'client_address',
            'effective_date', 'expiration_date', 'auto_renew', 'renewal_terms',
            'billing_frequency', 'payment_terms', 'tax_rate', 'discount_amount',
            'terms_and_conditions', 'notes', 'description',
            'accepted_at', 'accepted_by_name', 'accepted_by_email', 'accepted_by_title',
        ]));

        if ($request->has('items')) {
            $contract->items()->delete();
            foreach ($request->items as $i => $item) {
                ContractItem::create([
                    'contract_id' => $contract->id,
                    'service_catalog_id' => $item['service_catalog_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'frequency' => $item['frequency'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        }

        $contract->recalculate();
        $contract->load('items');

        return response()->json(['success' => true, 'data' => $contract]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $contract = Contract::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $contract->delete();
        return response()->json(['success' => true, 'message' => 'Contract deleted']);
    }

    public function stats(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agency_id;
        $now = Carbon::now();

        $active = Contract::where('agency_id', $agencyId)->where('status', 'active')->count();
        $draft = Contract::where('agency_id', $agencyId)->where('status', 'draft')->count();
        $sent = Contract::where('agency_id', $agencyId)->where('status', 'sent')->count();
        $expiringSoon = Contract::where('agency_id', $agencyId)
            ->where('status', 'active')
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [$now, $now->copy()->addDays(30)])
            ->count();
        $totalValue = Contract::where('agency_id', $agencyId)
            ->whereIn('status', ['active', 'accepted'])
            ->sum('total');

        return response()->json(['success' => true, 'data' => [
            'active' => $active,
            'draft' => $draft,
            'sent' => $sent,
            'expiring_soon' => $expiringSoon,
            'total_value' => $totalValue,
        ]]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $contract = Contract::where('agency_id', $request->user()->agency_id)
            ->with('items')
            ->findOrFail($id);

        if (!$contract->client_email) {
            return response()->json(['success' => false, 'message' => 'No client email set.'], 422);
        }

        $contract->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $agency = $request->user()->agency;
        $viewUrl = config('app.frontend_url', env('FRONTEND_URL')) . '/#contract/' . $contract->token;

        try {
            Mail::send([], [], function ($message) use ($contract, $agency, $viewUrl) {
                $message->to($contract->client_email, $contract->client_name)
                    ->subject("Contract from {$agency->name}: {$contract->title}")
                    ->html("
                        <div style='font-family:sans-serif;max-width:600px;margin:0 auto;'>
                            <h2 style='color:#1e293b;'>{$agency->name}</h2>
                            <p>You have received a new contract agreement.</p>
                            <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin:16px 0;'>
                                <p style='margin:0 0 8px;'><strong>Contract:</strong> {$contract->contract_number}</p>
                                <p style='margin:0 0 8px;'><strong>Title:</strong> {$contract->title}</p>
                                <p style='margin:0 0 8px;'><strong>Total:</strong> \${$contract->total}</p>
                                <p style='margin:0;'><strong>Effective:</strong> {$contract->effective_date->format('M d, Y')}</p>
                            </div>
                            <a href='{$viewUrl}' style='display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;'>View & Accept Contract</a>
                            <p style='color:#94a3b8;font-size:12px;margin-top:24px;'>This email was sent by {$agency->name} via Credentik.</p>
                        </div>
                    ");
            });
        } catch (\Exception $e) {
            Log::warning("Contract email failed: " . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => [
            'message' => 'Contract sent',
            'view_url' => $viewUrl,
        ]]);
    }

    public function terminate(Request $request, int $id): JsonResponse
    {
        $contract = Contract::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $contract->update([
            'status' => 'terminated',
            'terminated_at' => now(),
            'terminated_reason' => $request->input('reason', ''),
        ]);

        return response()->json(['success' => true, 'message' => 'Contract terminated']);
    }

    public function generateInvoice(Request $request, int $id): JsonResponse
    {
        $contract = Contract::where('agency_id', $request->user()->agency_id)
            ->with('items')
            ->findOrFail($id);

        $count = Invoice::where('agency_id', $request->user()->agency_id)->count() + 1;
        $number = 'INV-' . str_pad($count, 5, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'agency_id' => $request->user()->agency_id,
            'invoice_number' => $number,
            'type' => 'invoice',
            'status' => 'draft',
            'organization_id' => $contract->organization_id,
            'client_name' => $contract->client_name,
            'client_email' => $contract->client_email,
            'client_address' => $contract->client_address,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'tax_rate' => $contract->tax_rate,
            'discount_amount' => $contract->discount_amount,
            'notes' => "Generated from contract {$contract->contract_number}",
            'created_by' => $request->user()->id,
        ]);

        foreach ($contract->items as $i => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_catalog_id' => $item->service_catalog_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
                'sort_order' => $i,
            ]);
        }

        $invoice->recalculate();
        $invoice->load('items');

        return response()->json(['success' => true, 'data' => $invoice], 201);
    }

    // ── Public (token-based, no auth) ──

    public function showByToken(string $token): JsonResponse
    {
        $contract = Contract::withoutGlobalScopes()
            ->with(['items.serviceCatalog:id,name,code', 'agency:id,name,email,phone,address_street,address_city,address_state,address_zip,logo_url'])
            ->where('token', $token)
            ->firstOrFail();

        if (!$contract->viewed_at) {
            $contract->update(['viewed_at' => now(), 'status' => 'viewed']);

            // Notify agency that client viewed the contract
            Notification::create([
                'agency_id' => $contract->agency_id,
                'type' => 'contract_viewed',
                'title' => 'Contract Viewed',
                'body' => ($contract->client_name ?: 'Client') . ' viewed contract ' . $contract->contract_number,
                'icon' => 'eye',
                'link' => '/contracts/' . $contract->id,
                'linkable_type' => Contract::class,
                'linkable_id' => $contract->id,
            ]);
        }

        return response()->json(['success' => true, 'data' => $contract]);
    }

    public function acceptByToken(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'email' => 'required|email|max:200',
            'title' => 'nullable|string|max:200',
        ]);

        $contract = Contract::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        if ($contract->accepted_at) {
            return response()->json(['success' => false, 'message' => 'Contract already accepted.'], 422);
        }

        $contract->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by_name' => $request->name,
            'accepted_by_email' => $request->email,
            'accepted_by_title' => $request->input('title', ''),
            'accepted_ip' => $request->ip(),
        ]);

        // Notify agency that contract was accepted
        Notification::create([
            'agency_id' => $contract->agency_id,
            'type' => 'contract_accepted',
            'title' => 'Contract Accepted',
            'body' => $request->name . ($request->input('title') ? ' (' . $request->input('title') . ')' : '') . ' accepted contract ' . $contract->contract_number,
            'icon' => 'check-circle',
            'link' => '/contracts/' . $contract->id,
            'linkable_type' => Contract::class,
            'linkable_id' => $contract->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Contract accepted successfully.']);
    }
}
