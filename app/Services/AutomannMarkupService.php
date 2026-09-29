<?php

namespace App\Services;

use App\Support\SpreadsheetReader;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class AutomannMarkupService
{
    /**
     * Same brackets as price update/update_prices.py
     * cost (Dist. Price List) → multiplier for web price.
     */
    public function multiplierForCost(float $cost): float
    {
        if ($cost <= 0) {
            return 1.0;
        }
        if ($cost <= 1.00) {
            return 5.00; // +400%
        }
        if ($cost < 8.00) {
            return 2.00; // +100%
        }
        if ($cost <= 40.00) {
            return 1.45; // +45%
        }
        if ($cost <= 200.00) {
            return 1.40; // +40%
        }
        if ($cost <= 700.00) {
            return 1.35; // +35%
        }
        if ($cost <= 1200.00) {
            return 1.30; // +30%
        }
        if ($cost <= 3300.00) {
            return 1.25; // +25%
        }
        if ($cost <= 7780.00) {
            return 1.20; // +20%
        }

        return 1.15; // +15% (incl. 7780.01–23000+)
    }

    public function markup(float $cost): float
    {
        $multiplied = $cost * $this->multiplierForCost($cost);

        return (float) BigDecimal::of((string) $multiplied)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{rows: array<int, array<string, string>>, stats: array<string, int>}
     */
    public function transformRows(array $rows, string $costColumn = 'dist_price_list'): array
    {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'skipped_empty' => 0,
            'skipped_zero' => 0,
            'skipped_no_sku' => 0,
        ];

        $out = [];
        foreach ($rows as $row) {
            $stats['processed']++;

            // Prefer Dist. Price List; fall back to cost / list aliases already normalized by SpreadsheetReader
            $costRaw = $row[$costColumn]
                ?? $row['cost']
                ?? $row['dist__price_list']
                ?? $row['list_price']
                ?? '';
            $costRaw = trim((string) $costRaw);
            $costRaw = str_replace(['$', ','], '', $costRaw);

            if ($costRaw === '' || strtoupper($costRaw) === 'NULL') {
                $stats['skipped_empty']++;
                $row['updated_price'] = $row['updated_price'] ?? $row['price'] ?? '';
                $row['markup_multiplier'] = '';
                $out[] = $row;
                continue;
            }

            $cost = (float) $costRaw;
            if ($cost <= 0) {
                $stats['skipped_zero']++;
                $row['updated_price'] = number_format($cost, 2, '.', '');
                $row['price'] = $row['updated_price'];
                $row['markup_multiplier'] = '1.00';
                $out[] = $row;
                continue;
            }

            $mult = $this->multiplierForCost($cost);
            $web = $this->markup($cost);

            // Keep original cost before overwriting list columns
            $row['original_cost'] = number_format($cost, 2, '.', '');
            $row['cost'] = $row['original_cost'];

            // Mirror Python: apply same multiplier to list + net when present
            $row['dist_price_list'] = number_format($web, 2, '.', '');
            if (($row['dist_net_price'] ?? '') !== '') {
                $netRaw = str_replace(['$', ','], '', (string) $row['dist_net_price']);
                if ($netRaw !== '' && strtoupper($netRaw) !== 'NULL' && is_numeric($netRaw)) {
                    $row['dist_net_price'] = number_format((float) $netRaw * $mult, 2, '.', '');
                }
            }

            $row['updated_price'] = number_format($web, 2, '.', '');
            $row['price'] = $row['updated_price'];
            $row['markup_multiplier'] = number_format($mult, 2, '.', '');

            if (SpreadsheetReader::normalizeSku($row['sku'] ?? '') === '') {
                $stats['skipped_no_sku']++;
            }

            $stats['updated']++;
            $out[] = $row;
        }

        return ['rows' => $out, 'stats' => $stats];
    }

    public function tiersExplanation(): array
    {
        return [
            ['bracket' => '≤ $1.00', 'increase' => '400%', 'multiplier' => '×5.00'],
            ['bracket' => '$1.01 – $7.99', 'increase' => '100%', 'multiplier' => '×2.00'],
            ['bracket' => '$8.00 – $40.00', 'increase' => '45%', 'multiplier' => '×1.45'],
            ['bracket' => '$40.01 – $200.00', 'increase' => '40%', 'multiplier' => '×1.40'],
            ['bracket' => '$200.01 – $700.00', 'increase' => '35%', 'multiplier' => '×1.35'],
            ['bracket' => '$700.01 – $1,200.00', 'increase' => '30%', 'multiplier' => '×1.30'],
            ['bracket' => '$1,200.01 – $3,300.00', 'increase' => '25%', 'multiplier' => '×1.25'],
            ['bracket' => '$3,300.01 – $7,780.00', 'increase' => '20%', 'multiplier' => '×1.20'],
            ['bracket' => '$7,780.01+', 'increase' => '15%', 'multiplier' => '×1.15'],
        ];
    }
}
