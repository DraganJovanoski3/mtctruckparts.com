<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CrossReference;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@mtctruckparts.com'],
            [
                'name' => 'MTC Admin',
                'password' => 'password',
            ]
        );

        $brands = collect(['SKF', 'Automann', 'Gabriel', 'Freightliner', 'Haldex', 'Bendix'])
            ->mapWithKeys(fn ($name) => [
                $name => Brand::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'is_active' => true]
                ),
            ]);

        $categoryTree = [
            'Brakes, Suspension and Steering' => ['Brake', 'Steering', 'Suspension'],
            'Engine and Drivetrain' => ['Engine', 'Transmission', 'Exhaust'],
            'Electrical and Lighting' => ['Electrical', 'Lighting'],
            'Body and Cabin' => ['Body', 'Cabin and Interior'],
            'Tire and Wheel' => ['Tire', 'Wheel'],
        ];

        $categories = collect();
        $sort = 0;
        foreach ($categoryTree as $parentName => $children) {
            $parent = Category::updateOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'parent_id' => null,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );
            $categories->put($parentName, $parent);
            foreach ($children as $childName) {
                $child = Category::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]
                );
                $categories->put($childName, $child);
            }
        }

        $samples = [
            ['name' => '1643-C30 Hub Cap Oil Fill (6-Hole Composite Trailer Axle Hubcap)', 'part' => '1643-C30', 'sku' => 'SKF-1643-C30', 'brand' => 'SKF', 'cat' => 'Wheel', 'price' => 13.26, 'refs' => ['1643', '180.CR1643-C30']],
            ['name' => 'SKF SET 406 Tapered Roller Wheel Bearing Set', 'part' => 'SET406', 'sku' => 'SKF-SET406', 'brand' => 'SKF', 'cat' => 'Wheel', 'price' => 26.75, 'refs' => ['BR3782', 'BR3720', '182.SKF406']],
            ['name' => 'MTC-9780 Heavy Duty Air Springs', 'part' => 'MTC-9780', 'sku' => 'MTC-9780', 'brand' => 'Automann', 'cat' => 'Suspension', 'price' => 58.00, 'refs' => ['9780']],
            ['name' => 'MTC-8708 Air Spring | Heavy Duty Truck Suspension Air Bag', 'part' => 'MTC-8708', 'sku' => 'MTC-8708', 'brand' => 'Automann', 'cat' => 'Suspension', 'price' => 62.00, 'refs' => ['1R13-177', '64638', '566.ER8708']],
            ['name' => '4707 Brake Shoe Kit – 16.5" x 7" Heavy Duty Q Plus Kit', 'part' => '4707', 'sku' => 'MTC-4707', 'brand' => 'Bendix', 'cat' => 'Brake', 'price' => 46.13, 'refs' => ['MK4707Q', 'ABPMK4707Q23PREM']],
            ['name' => 'A77608 Gabriel Shock Absorber', 'part' => 'A77608', 'sku' => 'GAB-A77608', 'brand' => 'Gabriel', 'cat' => 'Suspension', 'price' => 62.73, 'refs' => ['A77608']],
            ['name' => 'A85724 Gabriel Shock Absorber', 'part' => 'A85724', 'sku' => 'GAB-A85724', 'brand' => 'Gabriel', 'cat' => 'Suspension', 'price' => 57.67, 'refs' => ['A85724']],
            ['name' => '100.A1369.16 Attaching Kit for Splined Rotor ADB22X', 'part' => '100.A1369.16', 'sku' => 'AUT-100A136916', 'brand' => 'Automann', 'cat' => 'Brake', 'price' => 64.97, 'refs' => ['100.A1369.16']],
            ['name' => '561.59264 Hump Hose Reducer Kenworth', 'part' => '561.59264', 'sku' => 'AUT-56159264', 'brand' => 'Automann', 'cat' => 'Engine', 'price' => 36.34, 'refs' => ['561.59264']],
            ['name' => '840.TH908947AM Thermostat Heavy Duty Cummins', 'part' => '840.TH908947AM', 'sku' => 'AUT-840TH908947', 'brand' => 'Automann', 'cat' => 'Engine', 'price' => 24.00, 'refs' => ['TH908947AM']],
            ['name' => '178.391211R Starter 39MT Type Rotatable', 'part' => '178.391211R', 'sku' => 'AUT-178391211R', 'brand' => 'Freightliner', 'cat' => 'Electrical', 'price' => 428.29, 'refs' => ['391211R']],
            ['name' => 'Haldex Brake Chamber Type 30', 'part' => 'GC3030', 'sku' => 'HAL-GC3030', 'brand' => 'Haldex', 'cat' => 'Brake', 'price' => 89.50, 'refs' => ['GC3030', 'Type 30']],
        ];

        foreach ($samples as $i => $row) {
            $product = Product::updateOrCreate(
                ['slug' => Str::slug($row['part'].'-'.$row['sku'])],
                [
                    'brand_id' => $brands[$row['brand']]->id,
                    'name' => $row['name'],
                    'part_number' => $row['part'],
                    'description' => '<p>Heavy duty truck part from MTC demo catalog. Searchable by part number and cross reference.</p>',
                    'meta_title' => $row['name'],
                    'meta_description' => 'Buy '.$row['name'].' at MTC Truck Parts.',
                    'status' => 'published',
                    'is_featured' => $i < 6,
                ]
            );

            $product->categories()->sync([$categories[$row['cat']]->id]);

            ProductVariant::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'product_id' => $product->id,
                    'price' => $row['price'],
                    'cost' => round($row['price'] * 0.65, 2),
                    'qty' => random_int(5, 80),
                    'stock_source' => 'manual',
                    'stock_updated_at' => now(),
                    'is_default' => true,
                ]
            );

            foreach ($row['refs'] as $ref) {
                CrossReference::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'ref_number' => $ref,
                    ],
                    ['ref_type' => 'oem']
                );
            }
        }
    }
}
