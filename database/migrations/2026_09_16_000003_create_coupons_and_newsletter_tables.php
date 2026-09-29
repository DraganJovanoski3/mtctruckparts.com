<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('discount_type')->default('percent'); // percent, fixed_cart, fixed_product
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('free_shipping')->default(false);
            $table->boolean('individual_use')->default(false);
            $table->decimal('minimum_amount', 12, 2)->nullable();
            $table->decimal('maximum_amount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('limit_usage_to_x_items')->nullable();
            $table->json('product_ids')->nullable();
            $table->json('exclude_product_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('exclude_category_ids')->nullable();
            $table->json('email_restrictions')->nullable();
            $table->boolean('exclude_sale_items')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_newsletter_welcome')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->string('source')->default('popup');
            $table->string('ip')->nullable();
            $table->timestamp('subscribed_at');
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('discount_total');
            $table->foreignId('coupon_id')->nullable()->after('coupon_code')->constrained('coupons')->nullOnDelete();
        });

        DB::table('coupons')->insert([
            'code' => 'WELCOME10',
            'description' => 'Newsletter signup — 10% off first order',
            'discount_type' => 'percent',
            'amount' => 10,
            'free_shipping' => false,
            'individual_use' => true,
            'minimum_amount' => 50,
            'maximum_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => 1,
            'used_count' => 0,
            'limit_usage_to_x_items' => null,
            'product_ids' => null,
            'exclude_product_ids' => null,
            'category_ids' => null,
            'exclude_category_ids' => null,
            'email_restrictions' => null,
            'exclude_sale_items' => false,
            'is_active' => true,
            'is_newsletter_welcome' => true,
            'starts_at' => now(),
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('coupon_code');
        });
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('coupons');
    }
};
