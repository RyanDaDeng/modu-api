<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhook\MchPaymentWebhookWebController;

Route::get('/', function () {
    return view('welcome');
});
