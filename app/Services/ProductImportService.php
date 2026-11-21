<?php

namespace App\Services;

use App\Models\BarcodeLabel;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Initial stock loading from a CSV file (STK13): one product per row with the same fields as the product form.
 * Excel users save the sheet as "CSV UTF-8" first.
 */
class ProductImportService
{
    /**
     * @var list<string>
     */
    public const COLUMNS = ['barcode', 'name', 'quantity', 'cost_price', 'sale_price', 'min_stock', 'notes'];

    public function __construct(private ProductService $products) {}

    /**
     * @return array{products: int, errors: list<string>}
     */
    public function import(UploadedFile $file): array
    {
        $result = ['products' => 0, 'errors' => []];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            $result['errors'][] = __('The file could not be read.');

            return $result;
        }

        $header = fgetcsv($handle, escape: '');

        if ($header === false) {
            fclose($handle);
            $result['errors'][] = __('The file is empty.');

            return $result;
        }

        $header = array_map(fn ($column) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), $header);

        if (! in_array('name', $header, true)) {
            fclose($handle);
            $result['errors'][] = __('The file needs at least a "name" column.');

            return $result;
        }

        $line = 1;

        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $line++;

            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
            $row = array_map(fn ($value) => trim((string) $value), $row);

            try {
                DB::transaction(fn () => $this->importRow($row));
                $result['products']++;
            } catch (\Throwable $exception) {
                $result['errors'][] = __('Row :line: :message', ['line' => $line, 'message' => $exception->getMessage()]);
            }
        }

        fclose($handle);

        return $result;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(array $row): void
    {
        if (($row['name'] ?? '') === '') {
            throw new \InvalidArgumentException(__('Name is required.'));
        }

        $barcode = ($row['barcode'] ?? '') !== '' ? $row['barcode'] : null;

        if ($barcode && (ProductVariant::withTrashed()->where('sku', $barcode)->exists() || BarcodeLabel::query()->where('code', $barcode)->exists())) {
            throw new \InvalidArgumentException(__('Barcode / code :code is already used by another product.', ['code' => $barcode]));
        }

        $quantity = $this->number($row['quantity'] ?? '');

        if ($quantity < 0 || floor($quantity) != $quantity) {
            throw new \InvalidArgumentException(__('Quantity must be a whole number, 0 or more.'));
        }

        $this->products->save(new Product, [
            'barcode' => $barcode,
            'name' => $row['name'],
            'quantity' => $quantity,
            'cost_price' => $this->number($row['cost_price'] ?? ''),
            'sale_price' => $this->number($row['sale_price'] ?? ''),
            'min_stock' => $this->number($row['min_stock'] ?? ''),
            'description' => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
            'is_active' => true,
        ]);
    }

    private function number(string $value): float
    {
        if ($value === '') {
            return 0;
        }

        $clean = str_replace(',', '', $value);

        if (! is_numeric($clean)) {
            throw new \InvalidArgumentException(__('":value" is not a number.', ['value' => $value]));
        }

        return (float) $clean;
    }
}
