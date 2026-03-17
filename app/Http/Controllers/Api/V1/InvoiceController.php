<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceReminder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::where('agency_id', $request->user()->agency_id)
            ->with(['organization:id,name', 'items', 'payments']);

        if ($type = $request->input('type')) $query->where('type', $type);
        if ($status = $request->input('status')) $query->where('status', $status);

        $invoices = $query->orderByDesc('created_at')->paginate(50);
        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('agency_id', $request->user()->agency_id)
            ->with(['organization:id,name,email', 'items.serviceCatalog', 'payments', 'creator:id,first_name,last_name'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $invoice]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|in:invoice,estimate',
            'organization_id' => 'nullable|exists:organizations,id',
            'client_name' => 'nullable|string|max:200',
            'client_email' => 'nullable|email',
            'client_address' => 'nullable|string',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.service_catalog_id' => 'nullable|exists:service_catalog,id',
        ]);

        // Generate invoice number
        $prefix = $request->input('type', 'invoice') === 'estimate' ? 'EST' : 'INV';
        $count = Invoice::where('agency_id', $request->user()->agency_id)->count() + 1;
        $number = $prefix . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'agency_id' => $request->user()->agency_id,
            'invoice_number' => $number,
            'type' => $request->input('type', 'invoice'),
            'status' => 'draft',
            'organization_id' => $request->organization_id,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
            'client_address' => $request->client_address,
            'issue_date' => $request->issue_date,
            'due_date' => $request->due_date,
            'tax_rate' => $request->input('tax_rate', 0),
            'discount_amount' => $request->input('discount_amount', 0),
            'notes' => $request->notes,
            'terms' => $request->terms,
            'created_by' => $request->user()->id,
        ]);

        foreach ($request->items as $i => $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_catalog_id' => $item['service_catalog_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => round($item['quantity'] * $item['unit_price'], 2),
                'sort_order' => $i,
            ]);
        }

        $invoice->recalculate();
        $invoice->load(['items', 'payments']);

        return response()->json(['success' => true, 'data' => $invoice], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $request->validate([
            'status' => 'sometimes|string|in:draft,sent,paid,overdue,cancelled,partial',
            'client_name' => 'sometimes|string|max:255',
            'client_email' => 'sometimes|nullable|email|max:200',
            'client_address' => 'sometimes|nullable|string|max:500',
            'issue_date' => 'sometimes|nullable|date',
            'due_date' => 'sometimes|nullable|date',
            'tax_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'discount_amount' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
            'terms' => 'sometimes|nullable|string',
            'items' => 'sometimes|array',
            'items.*.description' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|numeric|min:0',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.service_catalog_id' => 'nullable|integer',
        ]);
        $invoice->update($request->only([
            'status', 'client_name', 'client_email', 'client_address',
            'issue_date', 'due_date', 'tax_rate', 'discount_amount', 'notes', 'terms',
        ]));

        if ($request->has('items')) {
            $invoice->items()->delete();
            foreach ($request->items as $i => $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service_catalog_id' => $item['service_catalog_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => round($item['quantity'] * $item['unit_price'], 2),
                    'sort_order' => $i,
                ]);
            }
        }

        $invoice->recalculate();
        $invoice->load(['items', 'payments']);

        return response()->json(['success' => true, 'data' => $invoice]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $invoice->delete();
        return response()->json(['success' => true]);
    }

    // Record a payment
    public function addPayment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $invoice = Invoice::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        Payment::create([
            'agency_id' => $request->user()->agency_id,
            'invoice_id' => $invoice->id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'payment_date' => $request->payment_date,
            'notes' => $request->notes,
            'recorded_by' => $request->user()->id,
        ]);

        $invoice->recalculate();
        $invoice->load(['items', 'payments']);

        return response()->json(['success' => true, 'data' => $invoice]);
    }

    // Billing dashboard stats
    public function stats(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agency_id;

        $totalRevenue = Invoice::where('agency_id', $agencyId)->where('type', 'invoice')->sum('paid_amount');
        $outstanding = Invoice::where('agency_id', $agencyId)->where('type', 'invoice')
            ->whereNotIn('status', ['paid', 'cancelled'])->sum('balance_due');
        $overdue = Invoice::where('agency_id', $agencyId)->where('type', 'invoice')
            ->where('status', '!=', 'paid')->where('due_date', '<', now())->sum('balance_due');
        $draftCount = Invoice::where('agency_id', $agencyId)->where('status', 'draft')->count();

        return response()->json([
            'success' => true,
            'data' => compact('totalRevenue', 'outstanding', 'overdue', 'draftCount'),
        ]);
    }

    // Service catalog CRUD
    public function services(Request $request): JsonResponse
    {
        $services = ServiceCatalog::where(function ($q) use ($request) {
            $q->where('agency_id', $request->user()->agency_id)->orWhereNull('agency_id');
        })->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $services]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'default_price' => 'required|numeric|min:0',
        ]);

        $service = ServiceCatalog::create([
            'agency_id' => $request->user()->agency_id,
            ...$request->only('name', 'code', 'description', 'category', 'default_price'),
        ]);

        return response()->json(['success' => true, 'data' => $service], 201);
    }

    public function updateService(Request $request, int $id): JsonResponse
    {
        $service = ServiceCatalog::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|nullable|string|max:50',
            'description' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string|max:100',
            'default_price' => 'sometimes|nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);
        $service->update($request->only('name', 'code', 'description', 'category', 'default_price', 'is_active'));
        return response()->json(['success' => true, 'data' => $service]);
    }

    public function sendReminder(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        if (!$invoice->client_email) {
            return response()->json(['success' => false, 'message' => 'No client email on this invoice'], 422);
        }

        Mail::to($invoice->client_email)->send(new InvoiceReminder($invoice));

        return response()->json(['success' => true, 'message' => 'Reminder sent']);
    }
}
