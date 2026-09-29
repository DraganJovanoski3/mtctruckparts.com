<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('short_description')->nullable()->after('description');
            $table->string('focus_keyphrase')->nullable()->after('meta_description');
            $table->string('meta_keywords')->nullable()->after('focus_keyphrase');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('sale_price', 12, 2)->nullable()->after('compare_at_price');
            $table->decimal('length', 10, 2)->nullable()->after('weight');
            $table->decimal('width', 10, 2)->nullable()->after('length');
            $table->decimal('height', 10, 2)->nullable()->after('width');
            $table->string('stock_status')->default('instock')->after('qty');
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // price, stock, products, content
            $table->string('status')->default('pending')->index();
            $table->string('original_filename')->nullable();
            $table->string('stored_path')->nullable();
            $table->string('mode')->default('update'); // update, create_or_update, create_skip_existing
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('report_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'length', 'width', 'height', 'stock_status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'focus_keyphrase', 'meta_keywords']);
        });
    }
};
