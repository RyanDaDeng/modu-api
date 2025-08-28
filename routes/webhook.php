<?php

use Illuminate\Support\Facades\Route;

Route::middleware([])->namespace('App\Http\Controllers\Webhook')->group(function () {
    Route::post('/receive-mch', 'MchPaymentWebhookWebController@receivePayment')->name('payment.receive-mch');
});
