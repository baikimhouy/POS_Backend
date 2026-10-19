<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->string('idempotency_key', 80)->nullable()->unique(); // checkout retry protection
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->string('currency', 3);                       // currency the sale is priced/displayed in
            $table->decimal('exchange_rate', 12, 4);             // snapshot: KHR per 1 USD
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);    // not hardcoded; confirm requirements first
            $table->decimal('total_amount', 14, 2);
            $table->text('notes')->nullable();
            $table->dateTime('sold_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete()->index();
            // snapshots so later catalog edits never change old receipts or margins
            $table->string('product_name');
            $table->string('sku');
            $table->string('size', 20);
            $table->string('color', 40);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->string('method', 30);                        // cash | bank_transfer | khqr (manually confirmed)
            $table->string('currency', 3);
            $table->decimal('amount', 14, 2);                    // original currency amount
            $table->decimal('exchange_rate', 12, 4);             // snapshot used for this payment
            $table->decimal('amount_base_currency', 14, 2);      // converted to the reporting currency
            $table->string('status', 30)->default('pending')->index();
            $table->string('reference_number')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->index();
            $table->integer('quantity_change');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
