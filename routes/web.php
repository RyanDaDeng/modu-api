<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhook\MchPaymentWebhookWebController;

Route::get('/', function () {
    return view('welcome');
});

// Payment webhook routes
Route::post('/webhook/receive-mch', [MchPaymentWebhookWebController::class, 'receivePayment'])
    ->name('webhook.mch.payment')
    ->withoutMiddleware(['web', 'csrf']);
