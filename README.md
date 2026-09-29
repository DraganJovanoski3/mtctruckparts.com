# MTC Truck Parts (Laravel rebuild)

Custom ecommerce foundation for [mtctruckparts.com](https://mtctruckparts.com/).

## Stack

- Laravel 12 + Filament admin
- Blade storefront (part # search, categories, cart, checkout shell)
- SKU-first catalog + cross references
- Bulk % pricing via chunked queue jobs (50k+ ready)
- SQLite locally (switch `.env` to MySQL for production / cPanel)

## Quick start (XAMPP)

```bash
cd c:\xampp\htdocs\mtctruckparts.com
php artisan migrate --seed
php artisan serve
```

In another terminal (needed for bulk price applies):

```bash
php artisan queue:work
```

- Store: http://127.0.0.1:8000
- Admin: http://127.0.0.1:8000/admin
- Login: `admin@mtctruckparts.com` / `password`

Or use Apache: `http://localhost/mtctruckparts.com/public`

## What’s in this MVP

- Catalog: brands, categories, products, variants/SKU, cross refs
- Admin CRUD + bulk % price rules (preview + queued apply)
- **Bulk uploads (WP-plugin parity):** price, stock, product create/update, content/SEO by SKU (CSV/XLSX)
- Export SKU + price by brand
- Import history + skipped-row reports
- Storefront: home, shop/filters, PDP + JSON-LD, cart, order create
- Checkout creates orders; Authorize.net capture is next
- Finale sync deferred (stock_source already supports `finale`)

## Admin bulk tools

Open **Admin → Bulk tools**

| Tool | Like your WP plugin | Columns / usage |
|------|---------------------|-----------------|
| **Automann price update** | sku-price-update-xlsx + `update_prices.py` | Upload Automann CSV/XLSX/**XML** → Dist. Price List × tier markup → queue catalog price update by SKU |
| Bulk uploads → Price | sku-price-update-xlsx | Direct `sku`/`Automann Part Number` + `price`/`Updated Price` (no markup) |
| **Images by SKU** | update images from sku | filename=SKU upload, or `sku,image_url` sheet, missing-image export |

Empty cells = skip (never wipe existing data).

**50k workflow:** upload returns instantly → `PrepareImportRunJob` converts sheet to JSONL → ~50 chunk jobs (1,000 rows each) with cached SKU map. Run:

```bash
php artisan queue:work --tries=3 --timeout=300
```

PHP/cPanel tip for 50k XLSX: set `upload_max_filesize=64M`, `post_max_size=64M`, `max_execution_time=300` on the worker (not the web request).

### Automann tiers (cost → web)

| Cost | Markup |
|------|--------|
| ≤ $1 | ×5.00 (+400%) |
| < $8 | ×2.00 (+100%) |
| ≤ $40 | ×1.45 (+45%) |
| ≤ $200 | ×1.40 (+40%) |
| ≤ $700 | ×1.35 (+35%) |
| ≤ $1200 | ×1.30 (+30%) |
| ≤ $3300 | ×1.25 (+25%) |
| ≤ $7780 | ×1.20 (+20%) |
| > $7780 | ×1.15 (+15%) |

## Next milestones

1. Authorize.net Accept.js
2. CSV stock import by SKU
3. WooCommerce MySQL read-only importer + 301 redirects
4. Meilisearch for part # at 50k–500k scale
5. Redis + Supervisor on VPS (not shared hosting)
