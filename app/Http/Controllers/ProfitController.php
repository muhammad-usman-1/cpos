<?php

namespace App\Http\Controllers;

use App\Services\AccountsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profit summary: trading profit, stitching income and tailor payments (PAY04). The full reports module (3.11) builds on this.
 */
class ProfitController extends Controller
{
    public function __construct(private AccountsService $accounts) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $filters['from'] ?? today()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();

        return view('profit.index', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->accounts->profitSummary($from, $to),
        ]);
    }
}
