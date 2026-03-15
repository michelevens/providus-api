<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\LicensingBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Faq::where(function ($q) use ($request) {
            $q->where('agency_id', $request->user()->agency_id)->orWhereNull('agency_id');
        })->where('is_published', true);

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'ilike', "%{$search}%")
                  ->orWhere('answer', 'ilike', "%{$search}%");
            });
        }

        return response()->json(['success' => true, 'data' => $query->orderBy('sort_order')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'nullable|string',
        ]);

        $faq = Faq::create([
            'agency_id' => $request->user()->agency_id,
            ...$request->only('question', 'answer', 'category', 'sort_order', 'is_published'),
        ]);

        return response()->json(['success' => true, 'data' => $faq], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $faq = Faq::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $faq->update($request->only('question', 'answer', 'category', 'sort_order', 'is_published'));
        return response()->json(['success' => true, 'data' => $faq]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        Faq::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function helpful(Request $request, int $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $faq->increment('helpful_count');
        return response()->json(['success' => true]);
    }

    // Licensing boards (global reference)
    public function licensingBoards(Request $request): JsonResponse
    {
        $query = LicensingBoard::query();
        if ($state = $request->input('state')) $query->where('state', $state);
        if ($type = $request->input('board_type')) $query->where('board_type', $type);
        return response()->json(['success' => true, 'data' => $query->orderBy('state')->orderBy('board_name')->get()]);
    }
}
