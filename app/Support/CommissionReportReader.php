<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Reads a commission/conversion report exported from an affiliate dashboard
 * (CSV in any of the usual delimiters and encodings) and guesses which
 * column holds which field. Column names differ per platform and change
 * over time, so detection is by synonyms and the user can correct it.
 */
class CommissionReportReader
{
    public const FIELDS = ['order_id', 'item_ref', 'product_name', 'sub_id', 'status', 'order_value', 'commission', 'ordered_at'];

    public const REQUIRED_FIELDS = ['order_id', 'commission'];

    /** Normalized header (lowercase letters and digits only) synonyms, best match first. */
    protected const SYNONYMS = [
        'order_id' => ['idpesanan', 'idpemesanan', 'nopesanan', 'nomorpesanan', 'orderid', 'ordersn', 'ordernumber', 'idorder', 'conversionid', 'idkonversi', 'transactionid'],
        'item_ref' => ['itemid', 'iditem', 'idproduk', 'productid', 'idbarang', 'skuid'],
        'product_name' => ['namaitem', 'namaproduk', 'namabarang', 'itemname', 'productname', 'produk', 'item'],
        'sub_id' => ['subid1', 'subid', 'subid01', 'affsub', 'affsub1'],
        'status' => ['statuspesanan', 'orderstatus', 'statuskonversi', 'conversionstatus', 'statuskomisi', 'status'],
        'order_value' => ['nilaipembelianrp', 'nilaipembelian', 'hargarp', 'totalhargarp', 'nilaipesanan', 'ordervalue', 'purchasevalue', 'totalpembayaran', 'salesamount', 'gmv', 'harga'],
        'commission' => ['komisibersihaffiliaterp', 'komisibersihrp', 'komisibersih', 'totalkomisirp', 'totalkomisi', 'estimasikomisirp', 'estimasikomisi', 'komisiaffiliaterp', 'komisiaffiliate', 'komisirp', 'komisi', 'netcommission', 'estimatedcommission', 'commission', 'payout'],
        'ordered_at' => ['waktupesanan', 'waktupemesanan', 'tanggalpesanan', 'tanggalpemesanan', 'orderdate', 'ordertime', 'purchasetime', 'conversiontime', 'tanggal', 'date', 'waktu'],
    ];

    /** Fields whose header can be found by a keyword when no synonym matches exactly. */
    protected const KEYWORDS = [
        'commission' => ['komisibersih', 'netcommission', 'komisi', 'commission'],
        'order_id' => ['idpesanan', 'orderid', 'nopesanan'],
        'sub_id' => ['subid'],
    ];

    protected const DATE_FORMATS = [
        'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
        'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y', 'Y/m/d H:i:s', 'Y/m/d H:i', 'Y/m/d',
    ];

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, mapping: array<string, ?int>}
     */
    public function read(string $path, int $maxRows): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\r\n|\n|\r/', $content);
        $delimiter = $this->detectDelimiter(array_slice($lines, 0, 10));

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $records = [];
        while (($record = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            if ($record === [null] || count(array_filter($record, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $records[] = array_map(fn ($v) => trim((string) $v), $record);

            // Header search window + data rows
            if (count($records) > $maxRows + 10) {
                break;
            }
        }
        fclose($stream);

        // Some exports start with title or summary lines: the header is the first row we recognize
        $headerIndex = 0;
        $mapping = [];
        foreach (array_slice($records, 0, 10) as $index => $candidate) {
            $mapping = $this->detectMapping($candidate);
            if ($mapping['order_id'] !== null || $mapping['commission'] !== null) {
                $headerIndex = $index;
                break;
            }
        }

        $headers = $records[$headerIndex] ?? [];
        $mapping = $this->detectMapping($headers);

        return [
            'headers' => $headers,
            'rows' => array_slice($records, $headerIndex + 1, $maxRows),
            'mapping' => $mapping,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, ?int> Field => column index.
     */
    public function detectMapping(array $headers): array
    {
        $normalized = array_map(fn ($h) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $h)), $headers);
        $mapping = array_fill_keys(self::FIELDS, null);
        $taken = [];

        foreach (self::SYNONYMS as $field => $synonyms) {
            foreach ($synonyms as $synonym) {
                $index = array_search($synonym, $normalized, true);
                if ($index !== false && ! in_array($index, $taken, true)) {
                    $mapping[$field] = $index;
                    $taken[] = $index;
                    break;
                }
            }
        }

        foreach (self::KEYWORDS as $field => $keywords) {
            if ($mapping[$field] !== null) {
                continue;
            }
            foreach ($keywords as $keyword) {
                foreach ($normalized as $index => $header) {
                    if (! in_array($index, $taken, true) && str_contains($header, $keyword)) {
                        $mapping[$field] = $index;
                        $taken[] = $index;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Parse a money amount written as "Rp 12.500", "12,500", "1.234,56" or "1234.56".
     */
    public static function parseAmount(?string $raw): float
    {
        $value = preg_replace('/[^\d,.\-]/', '', (string) $raw);

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            // Both present: the one that comes last is the decimal separator
            [$decimal, $thousands] = $lastComma > $lastDot ? [',', '.'] : ['.', ','];
            $value = str_replace([$thousands, $decimal], ['', '.'], $value);
        } elseif ($lastComma !== false) {
            $value = preg_match('/^-?\d{1,3}(,\d{3})+$/', $value) ? str_replace(',', '', $value) : str_replace(',', '.', $value);
        } elseif ($lastDot !== false && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
            // Rupiah has no cents, so "12.500" is twelve thousand five hundred
            $value = str_replace('.', '', $value);
        }

        return round((float) $value, 2);
    }

    /**
     * Map a platform's status wording to pending / completed / cancelled.
     */
    public static function normalizeStatus(?string $raw): string
    {
        $status = mb_strtolower((string) $raw);

        if (preg_match('/batal|cancel|tolak|reject|invalid|gagal|fail|refund|retur|kembali|void/', $status)) {
            return 'cancelled';
        }

        if (preg_match('/selesai|complete|approved|disetujui|valid|berhasil|paid|dibayar|settled|confirmed|terkonfirmasi/', $status)) {
            return 'completed';
        }

        return 'pending';
    }

    public static function parseDate(?string $raw): ?CarbonImmutable
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($date !== false && $date->format($format) === $raw) {
                return CarbonImmutable::instance($date);
            }
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string>  $sampleLines
     */
    protected function detectDelimiter(array $sampleLines): string
    {
        $scores = [];
        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $scores[$delimiter] = array_sum(array_map(fn ($line) => substr_count((string) $line, $delimiter), $sampleLines));
        }
        arsort($scores);

        return (string) array_key_first($scores);
    }
}
