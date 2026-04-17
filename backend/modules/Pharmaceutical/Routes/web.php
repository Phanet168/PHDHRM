<?php

use Illuminate\Support\Facades\Route;
use Modules\Pharmaceutical\Http\Controllers\PharmDashboardController;
use Modules\Pharmaceutical\Http\Controllers\PharmDispensingController;
use Modules\Pharmaceutical\Http\Controllers\PharmDistributionController;
use Modules\Pharmaceutical\Http\Controllers\PharmHelpController;
use Modules\Pharmaceutical\Http\Controllers\PharmMedicineController;
use Modules\Pharmaceutical\Http\Controllers\PharmReportController;
use Modules\Pharmaceutical\Http\Controllers\PharmStockAdjustmentController;
use Modules\Pharmaceutical\Http\Controllers\PharmSummaryReportController;
use Modules\Pharmaceutical\Http\Controllers\PharmUserController;

Route::group(['prefix' => 'pharmaceutical', 'middleware' => ['web', 'auth']], function () {

    // Dashboard
    Route::get('/', [PharmDashboardController::class, 'index'])->middleware('permission:read_pharmaceutical_management|read_pharm_stock|read_pharm_reports|read_pharm_medicines')->name('pharmaceutical.index');
    Route::get('/help/{article?}', [PharmHelpController::class, 'index'])->middleware('permission:read_pharmaceutical_management|read_pharm_stock|read_pharm_reports|read_pharm_medicines')->name('pharmaceutical.help');
    Route::get('/stock', [PharmDashboardController::class, 'stock'])->middleware('permission:read_pharmaceutical_management|read_pharm_stock')->name('pharmaceutical.stock');
    Route::get('/stock/print', [PharmDashboardController::class, 'stockPrint'])->middleware('permission:read_pharmaceutical_management|read_pharm_stock')->name('pharmaceutical.stock.print');

    // Medicine master data
    Route::get('/medicines', [PharmMedicineController::class, 'index'])->middleware('permission:read_pharmaceutical_management|read_pharm_medicines')->name('pharmaceutical.medicines.index');
    Route::get('/medicines/create', [PharmMedicineController::class, 'create'])->middleware('permission:create_pharmaceutical_management|create_pharm_medicines')->name('pharmaceutical.medicines.create');
    Route::post('/medicines', [PharmMedicineController::class, 'store'])->middleware('permission:create_pharmaceutical_management|create_pharm_medicines')->name('pharmaceutical.medicines.store');
    Route::get('/medicines/{medicine}/edit', [PharmMedicineController::class, 'edit'])->middleware('permission:update_pharmaceutical_management|update_pharm_medicines')->name('pharmaceutical.medicines.edit');
    Route::put('/medicines/{medicine}', [PharmMedicineController::class, 'update'])->middleware('permission:update_pharmaceutical_management|update_pharm_medicines')->name('pharmaceutical.medicines.update');
    Route::delete('/medicines/{medicine}', [PharmMedicineController::class, 'destroy'])->middleware('permission:delete_pharmaceutical_management|delete_pharm_medicines')->name('pharmaceutical.medicines.destroy');

    // Categories
    Route::get('/categories', [PharmMedicineController::class, 'categories'])->middleware('permission:read_pharmaceutical_management|read_pharm_medicines')->name('pharmaceutical.categories.index');
    Route::post('/categories', [PharmMedicineController::class, 'storeCategory'])->middleware('permission:create_pharmaceutical_management|create_pharm_medicines')->name('pharmaceutical.categories.store');
    Route::put('/categories/{category}', [PharmMedicineController::class, 'updateCategory'])->middleware('permission:update_pharmaceutical_management|update_pharm_medicines')->name('pharmaceutical.categories.update');
    Route::delete('/categories/{category}', [PharmMedicineController::class, 'destroyCategory'])->middleware('permission:delete_pharmaceutical_management|delete_pharm_medicines')->name('pharmaceutical.categories.destroy');

    // Distributions
    Route::get('/distributions', [PharmDistributionController::class, 'index'])->middleware('permission:read_pharmaceutical_management|read_pharm_distributions')->name('pharmaceutical.distributions.index');
    Route::get('/distributions/create', [PharmDistributionController::class, 'create'])->middleware('permission:create_pharmaceutical_management|create_pharm_distributions')->name('pharmaceutical.distributions.create');
    Route::post('/distributions', [PharmDistributionController::class, 'store'])->middleware('permission:create_pharmaceutical_management|create_pharm_distributions')->name('pharmaceutical.distributions.store');
    Route::get('/distributions/{distribution}', [PharmDistributionController::class, 'show'])->middleware('permission:read_pharmaceutical_management|read_pharm_distributions')->name('pharmaceutical.distributions.show');
    Route::post('/distributions/{distribution}/send', [PharmDistributionController::class, 'send'])->middleware('permission:update_pharmaceutical_management|update_pharm_distributions')->name('pharmaceutical.distributions.send');
    Route::post('/distributions/{distribution}/receive', [PharmDistributionController::class, 'receive'])->middleware('permission:update_pharmaceutical_management|update_pharm_distributions')->name('pharmaceutical.distributions.receive');

    // Dispensing (Hospital & HC)
    Route::get('/dispensings', [PharmDispensingController::class, 'index'])->middleware('permission:read_pharmaceutical_management|read_pharm_dispensings')->name('pharmaceutical.dispensings.index');
    Route::get('/dispensings/create', [PharmDispensingController::class, 'create'])->middleware('permission:create_pharmaceutical_management|create_pharm_dispensings')->name('pharmaceutical.dispensings.create');
    Route::post('/dispensings', [PharmDispensingController::class, 'store'])->middleware('permission:create_pharmaceutical_management|create_pharm_dispensings')->name('pharmaceutical.dispensings.store');
    Route::get('/dispensings/{dispensing}', [PharmDispensingController::class, 'show'])->middleware('permission:read_pharmaceutical_management|read_pharm_dispensings')->name('pharmaceutical.dispensings.show');
    Route::get('/dispensings/{dispensing}/print', [PharmDispensingController::class, 'print'])->middleware('permission:read_pharmaceutical_management|read_pharm_dispensings')->name('pharmaceutical.dispensings.print');

    // Reports
    Route::get('/reports', [PharmReportController::class, 'index'])->middleware('permission:read_pharmaceutical_management|read_pharm_reports')->name('pharmaceutical.reports.index');
    Route::get('/reports/create', [PharmReportController::class, 'create'])->middleware('permission:create_pharmaceutical_management|create_pharm_reports')->name('pharmaceutical.reports.create');
    Route::post('/reports', [PharmReportController::class, 'store'])->middleware('permission:create_pharmaceutical_management|create_pharm_reports')->name('pharmaceutical.reports.store');
    Route::get('/reports/{report}', [PharmReportController::class, 'show'])->middleware('permission:read_pharmaceutical_management|read_pharm_reports')->name('pharmaceutical.reports.show');
    Route::get('/reports/{report}/edit', [PharmReportController::class, 'edit'])->middleware('permission:update_pharmaceutical_management|update_pharm_reports')->name('pharmaceutical.reports.edit');
    Route::put('/reports/{report}', [PharmReportController::class, 'update'])->middleware('permission:update_pharmaceutical_management|update_pharm_reports')->name('pharmaceutical.reports.update');
    Route::delete('/reports/{report}', [PharmReportController::class, 'destroy'])->middleware('permission:delete_pharmaceutical_management|delete_pharm_reports')->name('pharmaceutical.reports.destroy');
    Route::post('/reports/{report}/submit', [PharmReportController::class, 'submit'])->middleware('permission:update_pharmaceutical_management|update_pharm_reports')->name('pharmaceutical.reports.submit');
    Route::post('/reports/{report}/review', [PharmReportController::class, 'review'])->middleware('permission:update_pharmaceutical_management|update_pharm_reports')->name('pharmaceutical.reports.review');
    Route::get('/reports/{report}/print', [PharmReportController::class, 'print'])->middleware('permission:read_pharmaceutical_management|read_pharm_reports')->name('pharmaceutical.reports.print');
    Route::post('/reports/generate-data', [PharmReportController::class, 'generateData'])->middleware('permission:create_pharmaceutical_management|create_pharm_reports')->name('pharmaceutical.reports.generate-data');

    // Summary Reports
    Route::get('/summary-reports/usage', [PharmSummaryReportController::class, 'usage'])->middleware('permission:read_pharmaceutical_management|read_pharm_reports')->name('pharmaceutical.summary-reports.usage');
    Route::get('/summary-reports/usage/print', [PharmSummaryReportController::class, 'usagePrint'])->middleware('permission:read_pharmaceutical_management|read_pharm_reports')->name('pharmaceutical.summary-reports.usage-print');
    Route::get('/summary-reports/stock-summary', [PharmSummaryReportController::class, 'stockSummary'])->middleware('permission:read_pharmaceutical_management|read_pharm_reports')->name('pharmaceutical.summary-reports.stock-summary');
    Route::get('/summary-reports/stock-summary/print', [PharmSummaryReportController::class, 'stockSummaryPrint'])->middleware('permission:read_pharmaceutical_management|read_pharm_reports')->name('pharmaceutical.summary-reports.stock-summary-print');

    // Stock Adjustments (damage, expired, loss, correction)
    Route::get('/stock-adjustments', [PharmStockAdjustmentController::class, 'index'])->middleware('permission:read_pharmaceutical_management|read_pharm_stock')->name('pharmaceutical.stock-adjustments.index');
    Route::get('/stock-adjustments/create', [PharmStockAdjustmentController::class, 'create'])->middleware('permission:create_pharmaceutical_management|create_pharm_stock')->name('pharmaceutical.stock-adjustments.create');
    Route::post('/stock-adjustments', [PharmStockAdjustmentController::class, 'store'])->middleware('permission:create_pharmaceutical_management|create_pharm_stock')->name('pharmaceutical.stock-adjustments.store');

    // User Management (PHD/OD only)
    Route::get('/users', [PharmUserController::class, 'index'])->middleware('permission:read_pharmaceutical_management|read_pharm_users')->name('pharmaceutical.users.index');
    Route::get('/users/create', [PharmUserController::class, 'create'])->middleware('permission:create_pharmaceutical_management|create_pharm_users')->name('pharmaceutical.users.create');
    Route::post('/users', [PharmUserController::class, 'store'])->middleware('permission:create_pharmaceutical_management|create_pharm_users')->name('pharmaceutical.users.store');
    Route::patch('/users/{roleUuid}/toggle', [PharmUserController::class, 'toggle'])->middleware('permission:update_pharmaceutical_management|update_pharm_users')->name('pharmaceutical.users.toggle');
    Route::delete('/users/{roleUuid}', [PharmUserController::class, 'destroy'])->middleware('permission:delete_pharmaceutical_management|delete_pharm_users')->name('pharmaceutical.users.destroy');
    Route::get('/users/search', [PharmUserController::class, 'searchUsers'])->middleware('permission:read_pharmaceutical_management|read_pharm_users')->name('pharmaceutical.users.search');
});
