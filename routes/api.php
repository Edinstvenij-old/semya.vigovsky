<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SafetyController;
use App\Http\Controllers\Api\ShoppingController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\TravelController;
use App\Http\Controllers\Api\TravelDocumentController;
use App\Http\Controllers\Api\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
| Family Hub REST API. The SPA authenticates with Sanctum session cookies
| (GET /sanctum/csrf-cookie first). Every route under "family" is scoped
| to the signed-in user's current family; viewers are read-only.
*/

// --- Public -------------------------------------------------------------------
Route::get('config', [AuthController::class, 'config']);
Route::get('me', [AuthController::class, 'me']);
Route::middleware('throttle:auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('invitations/{token}', [AuthController::class, 'showInvitation']);
    Route::post('invitations/{token}/accept', [AuthController::class, 'acceptInvitation']);
    Route::post('forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('reset-password', [PasswordResetController::class, 'reset']);
});

// --- Signed in ------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);

    Route::patch('profile', [ProfileController::class, 'update']);
    Route::patch('profile/preferences', [ProfileController::class, 'preferences']);
    Route::put('profile/password', [ProfileController::class, 'password']);
    Route::post('profile/family', [ProfileController::class, 'switchFamily']);

    Route::prefix('two-factor')->middleware('throttle:two-factor')->group(function () {
        Route::get('/', [TwoFactorController::class, 'status']);
        Route::post('setup', [TwoFactorController::class, 'setup']);
        Route::post('confirm', [TwoFactorController::class, 'confirm']);
        Route::post('verify', [TwoFactorController::class, 'verify']);
        Route::delete('/', [TwoFactorController::class, 'disable']);
    });

    Route::middleware('family')->group(function () {
        // Read endpoints and personal actions — available to viewers too.
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('counts', [DashboardController::class, 'counts']);
        Route::get('family/members', [FamilyController::class, 'members']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'markRead']);
        Route::post('push/subscribe', [NotificationController::class, 'subscribe']);
        Route::post('push/unsubscribe', [NotificationController::class, 'unsubscribe']);
        Route::post('threads/{thread}/read', [ThreadController::class, 'markRead']);
        Route::get('safety-checks/active', [SafetyController::class, 'active']);
        Route::post('safety-checks/{check}/respond', [SafetyController::class, 'respond']); // viewers answer too

        // Everything below: GET for everyone, writes for admin/member only.
        Route::middleware('can-write')->group(function () {
            Route::get('categories', [TaskController::class, 'categories']);
            Route::post('categories', [TaskController::class, 'storeCategory']);
            Route::patch('categories/{category}', [TaskController::class, 'updateCategory']);
            Route::delete('categories/{category}', [TaskController::class, 'destroyCategory']);

            Route::get('tasks', [TaskController::class, 'index']);
            Route::post('tasks', [TaskController::class, 'store']);
            Route::get('tasks/{task}', [TaskController::class, 'show']);
            Route::patch('tasks/{task}', [TaskController::class, 'update']);
            Route::post('tasks/{task}/move', [TaskController::class, 'move']);
            Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
            Route::post('tasks/{task}/checklist', [TaskController::class, 'addChecklistItem']);
            Route::patch('tasks/{task}/checklist/{item}', [TaskController::class, 'updateChecklistItem']);
            Route::delete('tasks/{task}/checklist/{item}', [TaskController::class, 'deleteChecklistItem']);
            Route::post('tasks/{task}/links', [TaskController::class, 'addLink']);
            Route::delete('tasks/{task}/links/{link}', [TaskController::class, 'deleteLink']);
            Route::post('tasks/{task}/attachments', [TaskController::class, 'upload']);
            Route::post('tasks/{task}/thread', [TaskController::class, 'thread']);

            Route::get('attachments/{attachment}', [AttachmentController::class, 'show']);
            Route::get('attachments/{attachment}/thumb', [AttachmentController::class, 'thumb']);
            Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);

            Route::get('maintenance', [MaintenanceController::class, 'index']);
            Route::get('calendar', [MaintenanceController::class, 'calendar']);
            Route::post('maintenance', [MaintenanceController::class, 'store']);
            Route::patch('maintenance/{item}', [MaintenanceController::class, 'update']);
            Route::delete('maintenance/{item}', [MaintenanceController::class, 'destroy']);
            Route::post('maintenance/{item}/complete', [MaintenanceController::class, 'complete']);

            Route::get('shopping', [ShoppingController::class, 'index']);
            Route::post('shopping', [ShoppingController::class, 'store']);
            Route::post('shopping/clear-done', [ShoppingController::class, 'clearDone']);
            Route::patch('shopping/{item}', [ShoppingController::class, 'update']);
            Route::delete('shopping/{item}', [ShoppingController::class, 'destroy']);

            Route::post('safety-checks', [SafetyController::class, 'store']);
            Route::post('safety-checks/{check}/close', [SafetyController::class, 'close']);

            Route::get('checkin-types', [TravelController::class, 'checkinTypes']);
            Route::get('checkins', [TravelController::class, 'checkins']);
            Route::post('checkins', [TravelController::class, 'checkin']);
            Route::delete('checkins/{checkin}', [TravelController::class, 'destroyCheckin']);
            Route::get('trips', [TravelController::class, 'trips']);
            Route::post('trips', [TravelController::class, 'storeTrip']);
            Route::patch('trips/{trip}', [TravelController::class, 'updateTrip']);
            Route::delete('trips/{trip}', [TravelController::class, 'destroyTrip']);

            Route::middleware('two-factor')->group(function () {
                Route::get('documents', [TravelDocumentController::class, 'index']);
                Route::post('documents', [TravelDocumentController::class, 'store']);
                Route::post('documents/{document}', [TravelDocumentController::class, 'update']); // POST: multipart
                Route::delete('documents/{document}', [TravelDocumentController::class, 'destroy']);
            });

            Route::get('threads', [ThreadController::class, 'index']);
            Route::post('threads', [ThreadController::class, 'store']);
            Route::patch('threads/{thread}', [ThreadController::class, 'update']);
            Route::delete('threads/{thread}', [ThreadController::class, 'destroy']);
            Route::get('threads/{thread}/messages', [ThreadController::class, 'messages']);
            Route::post('threads/{thread}/messages', [ThreadController::class, 'storeMessage']);
            Route::delete('threads/{thread}/messages/{message}', [ThreadController::class, 'destroyMessage']);
        });

        // Family administration.
        Route::middleware('family-admin')->prefix('family')->group(function () {
            Route::patch('/', [FamilyController::class, 'update']);
            Route::patch('members/{userId}', [FamilyController::class, 'updateMember'])->whereNumber('userId');
            Route::delete('members/{userId}', [FamilyController::class, 'removeMember'])->whereNumber('userId');
            Route::post('members/{userId}/reset-link', [PasswordResetController::class, 'adminLink'])->whereNumber('userId');
            Route::get('invitations', [FamilyController::class, 'invitations']);
            Route::post('invitations', [FamilyController::class, 'createInvitation']);
            Route::delete('invitations/{invitation}', [FamilyController::class, 'revokeInvitation']);
            Route::get('backups', [FamilyController::class, 'backups']);
            Route::post('backups', [FamilyController::class, 'runBackup']);
            Route::get('backups/{name}', [FamilyController::class, 'downloadBackup']);
        });
    });
});
