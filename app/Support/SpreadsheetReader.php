<?php

namespace App\Support;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use SimpleXMLElement;

class SpreadsheetReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public static function read(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'xml') {
            return self::readSpreadsheetMl($path);
        }

        $reader = $ext === 'csv' || $ext === 'txt' ? new CsvReader : new XlsxReader;
        $reader->open($path);

        $headers = [];
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rowIndex = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $values = [];
                foreach ($row->getCells() as $cell) {
                    $values[] = trim((string) $cell->getValue());
                }

                if ($rowIndex === 0) {
                    $headers = array_map(fn ($h) => self::normalizeHeader($h), $values);
                    $rowIndex++;
                    continue;
                }

                if (self::rowIsEmpty($values)) {
                    $rowIndex++;
                    continue;
                }

                $assoc = [];
                foreach ($headers as $i => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $assoc[$header] = $values[$i] ?? '';
                }
                $rows[] = $assoc;
                $rowIndex++;
            }
            break; // first sheet only
        }

        $reader->close();

        return compact('headers', 'rows');
    }

    /**
     * Excel 2003 SpreadsheetML (.xml) — common Automann / legacy export.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public static function readSpreadsheetMl(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return ['headers' => [], 'rows' => []];
        }

        // Strip BOM / Excel processing instruction noise that can break SimpleXML
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        if ($xml === false) {
            throw new \RuntimeException('Could not parse XML spreadsheet. Use CSV or XLSX, or a valid Excel 2003 XML export.');
        }

        $namespaces = $xml->getNamespaces(true);
        $ss = $namespaces['ss'] ?? 'urn:schemas-microsoft-com:office:spreadsheet';

        $worksheet = $xml->children($ss)->Worksheet[0] ?? $xml->Worksheet[0] ?? null;
        if (! $worksheet) {
            // Sometimes default namespace is the ss one
            $worksheet = $xml->children($ss)->Worksheet[0] ?? null;
        }
        if (! $worksheet) {
            throw new \RuntimeException('XML file has no Worksheet.');
        }

        $table = $worksheet->children($ss)->Table ?? $worksheet->Table ?? null;
        if (! $table) {
            throw new \RuntimeException('XML worksheet has no Table.');
        }

        $headers = [];
        $rows = [];
        $rowIndex = 0;

        foreach ($table->children($ss)->Row ?? $table->Row ?? [] as $rowEl) {
            $values = self::spreadsheetMlRowValues($rowEl, $ss);

            if ($rowIndex === 0) {
                $headers = array_map(fn ($h) => self::normalizeHeader($h), $values);
                $rowIndex++;
                continue;
            }

            if (self::rowIsEmpty($values)) {
                $rowIndex++;
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $values[$i] ?? '';
            }
            $rows[] = $assoc;
            $rowIndex++;
        }

        return compact('headers', 'rows');
    }

    /**
     * @return array<int, string>
     */
    protected static function spreadsheetMlRowValues(SimpleXMLElement $rowEl, string $ssNs): array
    {
        $values = [];
        $index = 0;

        foreach ($rowEl->children($ssNs)->Cell ?? $rowEl->Cell ?? [] as $cell) {
            $attrs = $cell->attributes($ssNs);
            if ($attrs && isset($attrs['Index'])) {
                $target = ((int) $attrs['Index']) - 1;
                while ($index < $target) {
                    $values[$index] = '';
                    $index++;
                }
            }

            $data = $cell->children($ssNs)->Data ?? $cell->Data ?? null;
            $values[$index] = $data !== null ? trim((string) $data) : '';
            $index++;
        }

        return $values;
    }

    public static function normalizeHeader(string $header): string
    {
        $h = strtolower(trim($header));
        $h = str_replace([' ', '-', '.'], '_', $h);
        $h = preg_replace('/_+/', '_', $h) ?? $h;

        $aliases = [
            'automann_part_number' => 'sku',
            'part_number' => 'sku',
            'upc' => 'sku',
            'product_sku' => 'sku',
            'updated_price' => 'price',
            'regular_price' => 'price',
            'new_price' => 'price',
            'dist_net_price' => 'dist_net_price',
            'dist__net_price' => 'dist_net_price',
            'net_price' => 'dist_net_price',
            'dist_price_list' => 'dist_price_list',
            'dist__price_list' => 'dist_price_list',
            'list_price' => 'dist_price_list',
            'cost' => 'cost',
            'original_cost' => 'cost',
            'post_title' => 'title',
            'name' => 'title',
            'full_description' => 'description',
            'seo_title' => 'meta_title',
            'focus_keyword' => 'focus_keyphrase',
            'product_tags' => 'tags',
            'product_categories' => 'categories',
            'main_category' => 'main_category',
            'sub_category' => 'sub_category',
            'sub_sub_category' => 'sub_sub_category',
            'brands' => 'brand',
            'qty' => 'qty',
            'quantity' => 'qty',
            'stock' => 'qty',
            'stock_qty' => 'qty',
            'stock_status' => 'stock_status',
            'sale_price' => 'sale_price',
            'image_url' => 'image_url',
            'image' => 'image_url',
            'photo' => 'image_url',
            'photo_url' => 'image_url',
            'url' => 'image_url',
            'imageurl' => 'image_url',
        ];

        return $aliases[$h] ?? $h;
    }

    public static function normalizeSku(?string $sku): string
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return '';
        }
        // Excel often turns 0123 into 123.0
        if (preg_match('/^\d+\.0$/', $sku)) {
            $sku = substr($sku, 0, -2);
        }

        return $sku;
    }

    protected static function rowIsEmpty(array $values): bool
    {
        foreach ($values as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }
}
