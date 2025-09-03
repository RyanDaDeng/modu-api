<?php

use Illuminate\Support\Facades\Route;

Route::middleware([])->namespace('App\Http\Controllers\Webhook')->group(function () {
    Route::post('/api/receive-mch', 'MchPaymentWebhookWebController@receivePayment')->name('payment.receive-mch');
});

Route::post('/redemption/create', [\App\Http\Controllers\Api\RedemptionCodeController::class, 'create'])
    ->middleware([]);
