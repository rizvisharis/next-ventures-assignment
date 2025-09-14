<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Customers table
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique(); // CSV order ID
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('total', 10, 2);
            $table->enum('status', [
                'pending',
                'stock_reserved',
                'payment_simulated',
                'completed',
                'failed',
                'refunded'
            ])->default('pending');
            $table->json('items');
            $table->timestamps();
        });

        // Order notifications table
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel'); // email or log
            $table->string('status');  // success or fail
            $table->decimal('total', 12, 2);
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        // Refunds table
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('refund_notifications');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('order_notifications');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
        Schema::enableForeignKeyConstraints();
    }
};
