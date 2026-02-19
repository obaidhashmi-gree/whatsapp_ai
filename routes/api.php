<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// WhatsApp Webhook Routes
Route::get('/webhook', [WhatsAppController::class, 'verifyWebhook']);  // Verification
Route::post('/webhook', [WhatsAppController::class, 'receiveMessage']); // Message Handling
