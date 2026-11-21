<?php

namespace App\Http\Controllers;

use App\Models\StitchingOrder;
use App\Models\TailorAssignment;
use App\Models\User;
use App\Services\StitchingOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pieces given to and received from tailors (ORD06, ORD11).
 */
class TailorAssignmentController extends Controller
{
    public function __construct(private StitchingOrderService $orders) {}

    public function store(Request $request, StitchingOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'tailor_id' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'pieces_given' => ['required', 'integer', 'min:1', 'max:100'],
            'rate_per_piece' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $tailor = User::query()->findOrFail($validated['tailor_id']);
        $this->orders->assign($order, $tailor, (int) $validated['pieces_given'], isset($validated['rate_per_piece']) ? (float) $validated['rate_per_piece'] : null, $validated['notes'] ?? null);

        return back()->with('success', __('Given to :name.', ['name' => $tailor->name]));
    }

    public function receive(Request $request, TailorAssignment $assignment): RedirectResponse
    {
        $validated = $request->validate(['pieces' => ['required', 'integer', 'min:1']]);

        $this->orders->receivePieces($assignment, (int) $validated['pieces']);

        return back()->with('success', __('Pieces received.'));
    }
}
