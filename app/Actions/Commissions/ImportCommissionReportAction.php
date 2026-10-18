<?php

namespace App\Actions\Commissions;

use App\Models\CommissionImport;
use App\Models\Conversion;
use App\Support\CommissionReportReader;
use App\Support\ProductRef;
use Illuminate\Support\Facades\DB;

/**
 * Stores the rows of a commission report as conversions and matches them to links.
 * Re-importing a newer export of the same period updates status and amounts in place.
 */
class ImportCommissionReportAction
{
    public const MAX_ROWS = 20000;

    public function __construct(
        protected MatchConversionsAction $matchConversions,
    ) {}

    /**
     * @param  list<list<string>>  $rows  Data rows from CommissionReportReader::read().
     * @param  array<string, ?int>  $mapping  Field => column index.
     */
    public function execute(int $userId, string $platform, string $filename, array $rows, array $mapping): CommissionImport
    {
        $value = fn (array $row, string $field) => isset($mapping[$field]) && $mapping[$field] !== null
            ? trim((string) ($row[$mapping[$field]] ?? ''))
            : '';

        $now = now();
        $records = [];

        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $orderId = mb_substr($value($row, 'order_id'), 0, 100);
            if ($orderId === '') {
                continue;
            }

            $itemRef = preg_replace('/\D/', '', $value($row, 'item_ref')) ?: null;
            $productName = mb_substr($value($row, 'product_name'), 0, 255) ?: null;

            // Order + item identifies a row; without an item column, the product name stands in
            $itemKey = $itemRef ?? ($productName ? 'n:' . md5($productName) : '');
            $key = $orderId . '|' . $itemKey;

            $commission = CommissionReportReader::parseAmount($value($row, 'commission'));
            $orderValue = CommissionReportReader::parseAmount($value($row, 'order_value'));

            if (isset($records[$key])) {
                // Same item listed twice (e.g. one row per unit): add up
                $records[$key]['commission'] += $commission;
                $records[$key]['order_value'] += $orderValue;
                continue;
            }

            $records[$key] = [
                'user_id' => $userId,
                'link_id' => null,
                'platform' => $platform,
                'order_id' => $orderId,
                'item_key' => mb_substr($itemKey, 0, 100),
                'item_ref' => $itemRef ? mb_substr($itemRef, 0, 64) : null,
                'product_name' => $productName,
                'sub_id' => ProductRef::normalizeSubId($value($row, 'sub_id')),
                'status' => CommissionReportReader::normalizeStatus($value($row, 'status')),
                'order_value' => $orderValue,
                'commission' => $commission,
                'ordered_at' => CommissionReportReader::parseDate($value($row, 'ordered_at'))?->format('Y-m-d H:i:s'),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return DB::transaction(function () use ($userId, $platform, $filename, $rows, $records) {
            $import = CommissionImport::create([
                'user_id' => $userId,
                'platform' => $platform,
                'filename' => mb_substr($filename, 0, 255),
                'rows_total' => min(count($rows), self::MAX_ROWS),
                'rows_imported' => count($records),
            ]);

            foreach (array_chunk(array_values($records), 500) as $chunk) {
                $chunk = array_map(fn ($record) => $record + ['commission_import_id' => $import->id], $chunk);

                // link_id is left out of the update so a manual assignment survives re-imports
                Conversion::upsert(
                    $chunk,
                    ['user_id', 'platform', 'order_id', 'item_key'],
                    ['commission_import_id', 'item_ref', 'product_name', 'sub_id', 'status', 'order_value', 'commission', 'ordered_at', 'updated_at'],
                );
            }

            $this->matchConversions->execute($userId);

            $import->update([
                'rows_matched' => Conversion::where('commission_import_id', $import->id)->whereNotNull('link_id')->count(),
            ]);

            return $import;
        });
    }
}
