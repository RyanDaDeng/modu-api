<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('order_reference')->unique()->nullable();
            $table->string('remote_order_id')->nullable();
            $table->integer('remote_order_status')->default(-1);
            $table->string('product_key');
            $table->string('product_name');
            $table->integer('product_value');
            $table->string('product_type');
            $table->decimal('product_price', 10, 2);
            $table->decimal('receive_amount', 10, 2)->nullable();
            $table->boolean('is_success')->default(false);
            $table->boolean('is_finished')->default(false);
            $table->json('order_success_response')->nullable();
            $table->json('order_notify_response')->nullable();
            $table->integer('source')->default(1);
            $table->string('payment_method')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->index('order_reference');
            $table->index('remote_order_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_orders');
    }
};