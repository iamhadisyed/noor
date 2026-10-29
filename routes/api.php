<?php

use App\Http\Controllers\Api\AdminBillingController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GuidanceController;
use App\Http\Controllers\Api\GameLibraryController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\InsightsController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\ContentReviewController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\AacCardController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\LessonImportController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\NeedsController;
use App\Http\Controllers\Api\NotificationPrefsController;
use App\Http\Controllers\Api\PecsController;
use App\Http\Controllers\Api\PrivacyController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SpecialistController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('legal/terms', [AuthController::class, 'terms']);
    Route::post('auth/code', [AuthController::class, 'requestCode'])->middleware('throttle:otp-request');
    Route::post('auth/verify', [AuthController::class, 'verify'])->middleware('throttle:otp-verify');

    // Pictures and sounds: the signed, short-lived link is the credential (works in <img> and <audio>).
    Route::get('media/{task}/{kind}', [MediaController::class, 'show'])->name('media.show')->middleware('signed');
    Route::get('media/game/{item}/{kind}', [MediaController::class, 'game'])->name('media.game')->middleware('signed');
    Route::get('media/aac/{card}/{kind}', [MediaController::class, 'aac'])->name('media.aac')->middleware('signed');
    Route::get('media/family/{member}/{kind}', [MediaController::class, 'family'])->name('media.family')->middleware('signed');

    Route::middleware(['auth:sanctum', 'idempotent'])->group(function () {
        Route::get('sync', [SyncController::class, 'index']);

        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::get('announcements', [AnnouncementController::class, 'mine']);
        Route::post('announcements/{id}/dismiss', [AnnouncementController::class, 'dismiss']);
        Route::get('me/notifications', [NotificationPrefsController::class, 'show']);
        Route::patch('me/notifications', [NotificationPrefsController::class, 'update']);
        Route::get('billing', [BillingController::class, 'show']);
        Route::post('billing/redeem', [BillingController::class, 'redeem'])->middleware('throttle:10,60,billing-redeem');
        Route::post('me/terms', [AuthController::class, 'acceptTerms']);
        Route::get('me/export', [PrivacyController::class, 'export'])->middleware('throttle:3,60,me-export');
        Route::post('me/delete-code', [PrivacyController::class, 'requestDeleteCode'])->middleware('throttle:5,60,me-delete-code');
        Route::delete('me', [PrivacyController::class, 'destroy'])->middleware('throttle:10,60,me');
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me/sessions', [AuthController::class, 'sessions']);
        Route::delete('me/sessions/{id}', [AuthController::class, 'revokeSession']);

        Route::middleware('role:super_admin')->prefix('admin')->group(function () {
            Route::get('ping', fn () => response()->json(['ok' => true]));
            Route::get('overview', [AdminController::class, 'overview']);
            Route::get('usage', [AdminController::class, 'usage']);
            Route::get('quality', [AdminController::class, 'quality']);
            Route::get('alerts', [AdminController::class, 'alerts']);
            Route::get('ai-reports', [AdminController::class, 'aiReports']);
            Route::get('health', [AdminController::class, 'health']);
            Route::get('users', [AdminController::class, 'users']);
            Route::patch('users/{id}', [AdminController::class, 'updateUser']);
            Route::get('audit', [AdminController::class, 'audit']);

            Route::get('content/flags', [ContentReviewController::class, 'flags']);
            Route::post('content/flags/{id}/resolve', [ContentReviewController::class, 'resolve']);
            Route::get('content/lessons', [ContentReviewController::class, 'lessonsIndex']);
            Route::get('content/lessons/{id}', [ContentReviewController::class, 'lessonShow']);
            Route::get('games/items', [GameLibraryController::class, 'index']);
            Route::post('games/items', [GameLibraryController::class, 'store']);
            Route::post('games/items/{id}', [GameLibraryController::class, 'update'])->middleware('throttle:60,1,games-items');
            Route::delete('games/items/{id}', [GameLibraryController::class, 'destroy']);
            Route::get('vouchers', [AdminBillingController::class, 'vouchers']);
            Route::post('vouchers', [AdminBillingController::class, 'createVouchers']);
            Route::delete('vouchers/{id}', [AdminBillingController::class, 'revoke']);
            Route::post('users/{id}/grant', [AdminBillingController::class, 'grant']);
            Route::get('announcements', [AnnouncementController::class, 'index']);
            Route::post('announcements', [AnnouncementController::class, 'store']);
            Route::patch('announcements/{id}', [AnnouncementController::class, 'update']);
            Route::delete('announcements/{id}', [AnnouncementController::class, 'destroy']);
            Route::get('content/family/{id}', [ContentReviewController::class, 'familyShow']);
            Route::post('content/lessons/{id}/flag', [ContentReviewController::class, 'flagLesson']);
            Route::get('content/terms', [ContentReviewController::class, 'terms']);
            Route::post('content/terms', [ContentReviewController::class, 'addTerm']);
            Route::delete('content/terms/{id}', [ContentReviewController::class, 'removeTerm']);
        });

        Route::get('household', [HouseholdController::class, 'show']);
        Route::post('household/invite', [HouseholdController::class, 'invite'])->middleware('throttle:10,60,household-invite');
        Route::delete('household/members/{id}', [HouseholdController::class, 'remove']);
        Route::delete('household/joined/{id}', [HouseholdController::class, 'leave']);
        Route::post('household/invitations/{id}/accept', [HouseholdController::class, 'accept']);
        Route::post('household/invitations/{id}/decline', [HouseholdController::class, 'decline']);

        // therapists and teachers: the parent's side
        Route::get('children/{child}/specialists', [SpecialistController::class, 'index']);
        Route::post('children/{child}/specialists', [SpecialistController::class, 'invite'])->middleware('throttle:20,60,children-specialists');
        Route::post('specialists/{id}/new-code', [SpecialistController::class, 'newCodeFor'])->middleware('throttle:20,60,specialists-new-code');
        Route::delete('specialists/{id}', [SpecialistController::class, 'revoke']);
        Route::get('children/{child}/assignments', [SpecialistController::class, 'parentAssignments']);
        Route::post('assignments/{id}/done', [SpecialistController::class, 'complete']);
        // the specialist's side (any signed-in person who has a code)
        Route::post('specialist/redeem', [SpecialistController::class, 'redeem'])->middleware('throttle:10,60,specialist-redeem');
        Route::get('specialist/children', [SpecialistController::class, 'shared']);
        Route::get('specialist/children/{child}/summary', [SpecialistController::class, 'summary']);
        Route::delete('specialist/children/{child}', [SpecialistController::class, 'leave']);
        Route::get('specialist/children/{child}/assignments', [SpecialistController::class, 'myAssignments']);
        Route::post('specialist/children/{child}/assignments', [SpecialistController::class, 'assign'])->middleware('throttle:60,60,specialist-children-assignments');
        Route::patch('specialist/assignments/{id}', [SpecialistController::class, 'updateAssignment']);
        Route::delete('specialist/assignments/{id}', [SpecialistController::class, 'deleteAssignment']);

        Route::post('devices', [AuthController::class, 'registerDevice']);

        Route::get('children', [ChildController::class, 'index']);
        Route::post('children', [ChildController::class, 'store']);
        Route::get('children/{child}', [ChildController::class, 'show']);
        Route::patch('children/{child}', [ChildController::class, 'update']);
        Route::delete('children/{child}', [ChildController::class, 'destroy']);

        Route::get('meta', [LessonController::class, 'meta']);
        Route::get('guidance', [GuidanceController::class, 'index']);
        Route::get('guidance/{guide}', [GuidanceController::class, 'show']);
        Route::get('task-types', [LessonController::class, 'taskTypes']);
        Route::get('lesson-templates', [LessonController::class, 'templates']);
        Route::get('children/{child}/lessons', [LessonController::class, 'index']);
        Route::post('children/{child}/lessons', [LessonController::class, 'store']);
        Route::post('children/{child}/lessons/import', [LessonImportController::class, 'store'])->middleware('throttle:10,60,children-lessons-import');
        Route::post('children/{child}/lessons/from-template/{template}', [LessonController::class, 'fromTemplate']);
        Route::get('lessons/{lesson}', [LessonController::class, 'show']);
        Route::patch('lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('lessons/{lesson}', [LessonController::class, 'destroy']);
        Route::get('lessons/{lesson}/readiness', [LessonController::class, 'readiness']);
        Route::post('lessons/{lesson}/publish', [LessonController::class, 'publish']);
        Route::post('lessons/{lesson}/unpublish', [LessonController::class, 'unpublish']);
        Route::post('lessons/{lesson}/duplicate', [LessonController::class, 'duplicate']);
        Route::post('lessons/{lesson}/tasks', [TaskController::class, 'store']);
        Route::post('lessons/{lesson}/tasks/reorder', [TaskController::class, 'reorder']);
        Route::patch('tasks/{task}', [TaskController::class, 'update']);
        Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
        Route::post('tasks/{task}/media', [TaskController::class, 'upload'])->middleware('throttle:60,1,tasks-media');
        Route::delete('tasks/{task}/media/{kind}', [TaskController::class, 'removeMedia']);

        Route::post('child/alert', [NeedsController::class, 'alert'])->middleware('throttle:30,1,child-alert');
        Route::get('children/{child}/needs', [NeedsController::class, 'feed']);
        Route::get('needs/{log}', [NeedsController::class, 'show']);
        Route::post('needs/{log}/seen', [NeedsController::class, 'seen']);
        Route::post('needs/{log}/acknowledge', [NeedsController::class, 'acknowledge']);
        Route::get('aac-cards', [NeedsController::class, 'cards']);
        Route::post('children/{child}/aac-cards', [AacCardController::class, 'store'])->middleware('throttle:60,1,children-aac-cards');
        Route::post('aac-cards/{id}', [AacCardController::class, 'update'])->middleware('throttle:60,1,aac-cards');   // POST so a phone can send files with the change
        Route::patch('aac-cards/{id}', [AacCardController::class, 'update']);
        Route::delete('aac-cards/{id}', [AacCardController::class, 'destroy']);
        Route::get('pecs.pdf', [PecsController::class, 'download']);

        Route::get('children/{child}/family', [FamilyMemberController::class, 'index']);
        Route::post('children/{child}/family', [FamilyMemberController::class, 'store']);
        Route::post('family/{id}', [FamilyMemberController::class, 'update'])->middleware('throttle:60,1,family');   // POST so a phone can send files with the change
        Route::patch('family/{id}', [FamilyMemberController::class, 'update']);
        Route::delete('family/{id}', [FamilyMemberController::class, 'destroy']);
        Route::post('children/{child}/sessions', [SessionController::class, 'store']);

        Route::get('games', [GameController::class, 'index']);
        Route::get('games/library', [GameController::class, 'library']);
        Route::get('activities', [ActivityController::class, 'index']);
        Route::post('children/{child}/activities/{activity}/done', [ActivityController::class, 'done'])->middleware('throttle:60,1,activity-done');
        Route::get('children/{child}/milestones', [MilestoneController::class, 'index']);
        Route::get('children/{child}/milestones/{key}/card.png', [MilestoneController::class, 'card'])->where('key', '[a-z0-9_]+')->middleware('throttle:30,1,children-milestones-card-png');
        Route::get('children/{child}/rewards', [GameController::class, 'rewards']);
        Route::patch('children/{child}/rewards/settings', [GameController::class, 'updateSettings']);
        Route::post('children/{child}/rewards/bonus', [GameController::class, 'bonus']);
        Route::post('children/{child}/games/{game}/start', [GameController::class, 'start'])->middleware('throttle:60,1,children-games-start');
        Route::post('game-sessions/{id}/finish', [GameController::class, 'finish']);

        Route::get('children/{child}/insights/summary', [InsightsController::class, 'summary']);
        Route::get('children/{child}/letters', [InsightsController::class, 'letters']);
        Route::get('children/{child}/insights/daily', [InsightsController::class, 'daily']);
        Route::get('children/{child}/insights/report.pdf', [InsightsController::class, 'exportPdf'])->middleware('throttle:20,1,children-insights-report-pdf');
        Route::get('children/{child}/insights/export.csv', [InsightsController::class, 'exportCsv']);
        Route::post('children/{child}/reports', [ReportController::class, 'generate']);
        Route::get('children/{child}/reports/latest', [ReportController::class, 'latest']);
    });
});
