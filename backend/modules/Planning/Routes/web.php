<?php

use Illuminate\Support\Facades\Route;
use Modules\Planning\Http\Controllers\PlanController;
use Modules\Planning\Http\Controllers\PlanningConsolidationController;
use Modules\Planning\Http\Controllers\PlanningDashboardController;
use Modules\Planning\Http\Controllers\PlanningMasterDataController;
use Modules\Planning\Http\Controllers\PlanningReportController;

Route::group(['prefix' => 'planning', 'middleware' => ['web', 'auth']], function () {
    $masterResources = 'org_units|chapters|accounts|sub_accounts|programs|sub_programs|activity_clusters|indicators|funding_sources';

    Route::get('/', [PlanningDashboardController::class, 'index'])->name('planning.dashboard');
    Route::get('/dashboard', [PlanningDashboardController::class, 'index'])->name('planning.dashboard.index');

    Route::get('/plans', [PlanController::class, 'index'])->name('planning.plans.index');
    Route::get('/plans/create', [PlanController::class, 'create'])->name('planning.plans.create');
    Route::post('/plans', [PlanController::class, 'store'])->name('planning.plans.store');
    Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('planning.plans.show');
    Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('planning.plans.edit');
    Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('planning.plans.update');
    Route::get('/plans/{plan}/micro-plan', [PlanController::class, 'microPlan'])->name('planning.plans.micro-plan.edit');
    Route::put('/plans/{plan}/micro-plan', [PlanController::class, 'updateMicroPlan'])->name('planning.plans.micro-plan.update');
    Route::get('/plans/{plan}/activity-plan', [PlanController::class, 'activityPlan'])->name('planning.plans.activity-plan.edit');
    Route::put('/plans/{plan}/activity-plan', [PlanController::class, 'updateActivityPlan'])->name('planning.plans.activity-plan.update');
    Route::get('/plans/{plan}/monthly-activity-plan', [PlanController::class, 'monthlyActivityPlan'])->name('planning.plans.monthly-activity-plan.edit');
    Route::put('/plans/{plan}/monthly-activity-plan', [PlanController::class, 'updateMonthlyActivityPlan'])->name('planning.plans.monthly-activity-plan.update');
    Route::get('/plans/{plan}/daily-activity-plan', [PlanController::class, 'dailyActivityPlan'])->name('planning.plans.daily-activity-plan.edit');
    Route::put('/plans/{plan}/daily-activity-plan', [PlanController::class, 'updateDailyActivityPlan'])->name('planning.plans.daily-activity-plan.update');
    Route::post('/plans/{plan}/submit', [PlanController::class, 'submit'])->name('planning.plans.submit');
    Route::post('/plans/{plan}/review', [PlanController::class, 'review'])->name('planning.plans.review');
    Route::post('/plans/{plan}/approve', [PlanController::class, 'approve'])->name('planning.plans.approve');
    Route::post('/plans/{plan}/reject', [PlanController::class, 'reject'])->name('planning.plans.reject');
    Route::post('/plans/{plan}/consolidate', [PlanController::class, 'consolidate'])->name('planning.plans.consolidate');
    Route::post('/plans/{plan}/comments', [PlanController::class, 'comment'])->name('planning.plans.comments.store');
    Route::get('/plans/{plan}/attachments/{attachmentId}', [PlanController::class, 'attachment'])->name('planning.plans.attachments.download');
    Route::get('/plans/{plan}/export', [PlanController::class, 'export'])->name('planning.plans.export');

    Route::get('/consolidation', [PlanningConsolidationController::class, 'index'])->name('planning.consolidation.index');

    Route::get('/reports', [PlanningReportController::class, 'index'])->name('planning.reports.index');
    Route::get('/reports/export', [PlanningReportController::class, 'export'])->name('planning.reports.export');

    Route::get('/master-data/{resource}', [PlanningMasterDataController::class, 'index'])
        ->where('resource', $masterResources)
        ->name('planning.master-data.index');
    Route::get('/master-data/{resource}/create', [PlanningMasterDataController::class, 'create'])
        ->where('resource', $masterResources)
        ->name('planning.master-data.create');
    Route::post('/master-data/{resource}', [PlanningMasterDataController::class, 'store'])
        ->where('resource', $masterResources)
        ->name('planning.master-data.store');
    Route::get('/master-data/{resource}/{recordId}/edit', [PlanningMasterDataController::class, 'edit'])
        ->where('resource', $masterResources)
        ->name('planning.master-data.edit');
    Route::put('/master-data/{resource}/{recordId}', [PlanningMasterDataController::class, 'update'])
        ->where('resource', $masterResources)
        ->name('planning.master-data.update');
    Route::delete('/master-data/{resource}/{recordId}', [PlanningMasterDataController::class, 'destroy'])
        ->where('resource', $masterResources)
        ->name('planning.master-data.destroy');
});
