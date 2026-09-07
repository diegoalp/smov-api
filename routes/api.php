<?php

use App\Http\Controllers\Api\{AuthController,AutomationRuleController,BrandingController,BusinessController,BusinessEventController,BusinessMessageController,CategoryController,ClientController,CustomFieldController,DispositionController,FunnelController,InstanceController,LeadSourceController,OperationTemplateController,PermissionRoleController,PhoneController,ProductController,PublicLeadFormController,StageController,TaskController,TeamController,UserController,ActivityTypeController};
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureInstanceIsActive::class, \App\Http\Middleware\EnsureExpiredBusinessesAreHandled::class])->group(function (): void {
    Route::patch('instances/{instance}/activate', [InstanceController::class, 'activate']);
    Route::post('clients/resolve', [ClientController::class, 'resolve'])->middleware('throttle:20,1');
    Route::get('branding', [BrandingController::class, 'show']);
    Route::put('branding', [BrandingController::class, 'update']);
    Route::post('branding/logo', [BrandingController::class, 'uploadLogo']);
    Route::delete('branding/logo', [BrandingController::class, 'removeLogo']);
    Route::get('operation-template', [OperationTemplateController::class, 'show']);
    Route::put('operation-template', [OperationTemplateController::class, 'install']);
    Route::apiResource('businesses.events', BusinessEventController::class)->only(['index', 'store'])->shallow();
    Route::apiResource('businesses.messages', BusinessMessageController::class)->only(['index', 'store'])->shallow();

    Route::get('activities/assignees', [\App\Http\Controllers\Api\ActivityController::class, 'assignees']);
    Route::get('activities/today', [\App\Http\Controllers\Api\ActivityController::class, 'today']);
    Route::apiResource('activities', \App\Http\Controllers\Api\ActivityController::class);
    Route::apiResource('notes', \App\Http\Controllers\Api\NoteController::class)->only(['index','store','destroy']);
    Route::get('documents/{document}/download', [\App\Http\Controllers\Api\DocumentController::class, 'download']);
    Route::apiResource('documents', \App\Http\Controllers\Api\DocumentController::class)->only(['index','store','destroy']);
    Route::apiResources([
        'users' => UserController::class,
        'phones' => PhoneController::class,
        'products' => ProductController::class,
        'categories' => CategoryController::class,
        'funnels' => FunnelController::class,
        'stages' => StageController::class,
        'businesses' => BusinessController::class,
        'instances' => InstanceController::class,
        'lead-sources' => LeadSourceController::class,
        'dispositions' => DispositionController::class,
        'custom-fields' => CustomFieldController::class,
        'automation-rules' => AutomationRuleController::class,
        'teams' => TeamController::class,
        'permission-roles' => PermissionRoleController::class,
        'public-lead-forms' => PublicLeadFormController::class,
        'tasks' => TaskController::class,
        'activity-types' => ActivityTypeController::class,
        'document-types' => \App\Http\Controllers\Api\DocumentTypeController::class,
    ]);
    Route::apiResource('clients', ClientController::class)->except('store');
});
