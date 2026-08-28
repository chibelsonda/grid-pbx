<?php

use App\Domains\CallDetailRecords\Controllers\CallDetailRecordController;
use App\Domains\CallDetailRecords\Controllers\CallDetailRecordSyncController;
use App\Domains\CallRouting\Controllers\CallflowController;
use App\Domains\Devices\Controllers\DeviceController;
use App\Domains\Extensions\Controllers\ExtensionController;
use App\Domains\Extensions\Controllers\ExtensionDetailController;
use App\Domains\Extensions\Controllers\ExtensionRecoveryController;
use App\Domains\IdentityAccess\Controllers\SessionController;
use App\Domains\Organizations\Controllers\AccountController;
use App\Domains\PhoneNumbers\Controllers\PhoneNumberController;
use App\Domains\PhoneNumbers\Controllers\PhoneNumberSyncController;
use App\Domains\SwitchSynchronization\Controllers\ExtensionSyncController;
use App\Domains\Voicemail\Controllers\VoicemailBoxController;
use App\Domains\Voicemail\Controllers\VoicemailGreetingController;
use App\Domains\Voicemail\Controllers\VoicemailMessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'data' => [
            'service' => 'grid-api',
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ],
    ]));

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/session', [SessionController::class, 'show']);
        Route::get('/accounts', AccountController::class);
        Route::get('/accounts/{account}/extensions', ExtensionController::class);
        Route::post('/accounts/{account}/extensions', [ExtensionController::class, 'store']);
        Route::get('/accounts/{account}/extensions/{extension}', ExtensionDetailController::class);
        Route::put('/accounts/{account}/extensions/{extension}', [ExtensionController::class, 'update']);
        Route::get('/accounts/{account}/extensions/{extension}/deletion-preview', [ExtensionController::class, 'deletionPreview']);
        Route::delete('/accounts/{account}/extensions/{extension}', [ExtensionController::class, 'destroy']);
        Route::get('/accounts/{account}/extension-recovery', [ExtensionRecoveryController::class, 'index']);
        Route::post('/accounts/{account}/extension-recovery/{operation}', [ExtensionRecoveryController::class, 'recover']);
        Route::get('/accounts/{account}/devices', [DeviceController::class, 'index']);
        Route::post('/accounts/{account}/devices', [DeviceController::class, 'store']);
        Route::get('/accounts/{account}/devices/{device}', [DeviceController::class, 'show']);
        Route::put('/accounts/{account}/devices/{device}', [DeviceController::class, 'update']);
        Route::delete('/accounts/{account}/devices/{device}', [DeviceController::class, 'destroy']);
        Route::get('/accounts/{account}/phone-numbers', [PhoneNumberController::class, 'index']);
        Route::get('/accounts/{account}/phone-numbers/{phoneNumber}', [PhoneNumberController::class, 'show']);
        Route::get('/accounts/{account}/callflows', [CallflowController::class, 'index']);
        Route::get('/accounts/{account}/callflows/editor', [CallflowController::class, 'createOptions']);
        Route::post('/accounts/{account}/callflows', [CallflowController::class, 'store']);
        Route::get('/accounts/{account}/callflows/{callflow}/editor', [CallflowController::class, 'edit']);
        Route::get('/accounts/{account}/callflows/{callflow}', [CallflowController::class, 'show']);
        Route::put('/accounts/{account}/callflows/{callflow}', [CallflowController::class, 'update']);
        Route::delete('/accounts/{account}/callflows/{callflow}', [CallflowController::class, 'destroy']);
        Route::get('/accounts/{account}/call-detail-records', [CallDetailRecordController::class, 'index']);
        Route::get('/accounts/{account}/call-detail-records/{callDetailRecord}', [CallDetailRecordController::class, 'show']);
        Route::post('/accounts/{account}/sync/call-detail-records', [CallDetailRecordSyncController::class, 'store']);
        Route::get('/accounts/{account}/sync/call-detail-records/{run}', [CallDetailRecordSyncController::class, 'show']);
        Route::post('/accounts/{account}/sync/phone-numbers', [PhoneNumberSyncController::class, 'store']);
        Route::get('/accounts/{account}/sync/phone-numbers/{run}', [PhoneNumberSyncController::class, 'show']);
        Route::get('/accounts/{account}/voicemail-boxes', [VoicemailBoxController::class, 'index']);
        Route::post('/accounts/{account}/voicemail-boxes', [VoicemailBoxController::class, 'store']);
        Route::get('/accounts/{account}/voicemail-boxes/{voicemailBox}', [VoicemailBoxController::class, 'show']);
        Route::put('/accounts/{account}/voicemail-boxes/{voicemailBox}', [VoicemailBoxController::class, 'update']);
        Route::delete('/accounts/{account}/voicemail-boxes/{voicemailBox}', [VoicemailBoxController::class, 'destroy']);
        Route::get('/accounts/{account}/voicemail-boxes/{voicemailBox}/messages', [VoicemailMessageController::class, 'index']);
        Route::patch('/accounts/{account}/voicemail-boxes/{voicemailBox}/messages', [VoicemailMessageController::class, 'bulkUpdate']);
        Route::patch('/accounts/{account}/voicemail-boxes/{voicemailBox}/messages/{message}', [VoicemailMessageController::class, 'update']);
        Route::get('/accounts/{account}/voicemail-boxes/{voicemailBox}/messages/{message}/audio', [VoicemailMessageController::class, 'audio']);
        Route::post('/accounts/{account}/voicemail-boxes/{voicemailBox}/greeting', [VoicemailGreetingController::class, 'store']);
        Route::get('/accounts/{account}/voicemail-boxes/{voicemailBox}/greeting/audio', [VoicemailGreetingController::class, 'audio']);
        Route::delete('/accounts/{account}/voicemail-boxes/{voicemailBox}/greeting', [VoicemailGreetingController::class, 'destroy']);
        Route::post('/accounts/{account}/sync/extensions', [ExtensionSyncController::class, 'store']);
        Route::get('/accounts/{account}/sync/extensions/{run}', [ExtensionSyncController::class, 'show']);
    });
});
