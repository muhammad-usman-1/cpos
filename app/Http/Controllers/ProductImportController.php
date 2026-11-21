<?php

namespace App\Http\Controllers;

use App\Services\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function __construct(private ProductImportService $importer) {}

    public function create(): View
    {
        return view('products.import', ['columns' => ProductImportService::COLUMNS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $result = $this->importer->import($request->file('file'));

        return back()->with('import_result', $result)->with(
            $result['errors'] === [] ? 'success' : 'error',
            __('Imported :products products. :errors rows had errors.', ['products' => $result['products'], 'errors' => count($result['errors'])]),
        );
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ProductImportService::COLUMNS, escape: '');
            fputcsv($out, ['1001', 'Wash and wear gents suit', '12', '2800', '3900', '3', 'White'], escape: '');
            fputcsv($out, ['', 'Lawn printed 3 piece', '10', '2500', '3500', '2', 'Code is made automatically when barcode is empty'], escape: '');
            fclose($out);
        }, 'product-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}
