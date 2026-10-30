<?php
use Illuminate\Support\Facades\Route;
use Modules\Report\Http\Controllers\Health\AnalysisController;
use Modules\Report\Http\Controllers\Health\DashboardController;
use Modules\Report\Http\Controllers\Health\DataEntryController;
use Modules\Report\Http\Controllers\Health\HisController;
use Modules\Report\Http\Controllers\Health\HrController;
use Modules\Report\Http\Controllers\Health\ImportController;
use Modules\Report\Http\Controllers\Health\IssueController;
use Modules\Report\Http\Controllers\Health\SettingController;
use Modules\Report\Http\Controllers\Health\StructureController;
use Modules\Report\Http\Controllers\Health\SummaryController;
use Modules\Report\Http\Controllers\Health\UserUnitController;
use Modules\Report\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Health activity report (របាយការណ៍សកម្មភាពសុខាភិបាល) — port of PHDReport
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'report/health', 'middleware' => ['auth'], 'as' => 'report.health.'], function () {
    $read = 'permission:read_health_activity_report|read_health_report_settings';
    $enter = 'permission:update_health_activity_report|update_health_report_settings';
    $settings = 'permission:read_health_report_settings';
    $manage = 'permission:update_health_report_settings';

    Route::get('/', [DashboardController::class, 'index'])->middleware($read)->name('dashboard');

    Route::get('/data-entry', [DataEntryController::class, 'index'])->middleware($read)->name('entry');
    Route::post('/data-entry', [DataEntryController::class, 'save'])->middleware($enter)->name('entry.save');
    Route::get('/data-entry/previous', [DataEntryController::class, 'previous'])->middleware($enter)->name('entry.previous');
    Route::get('/data-entry/export', [DataEntryController::class, 'export'])->middleware($read)->name('entry.export');
    Route::get('/data-entry/print', [DataEntryController::class, 'print'])->middleware($read)->name('entry.print');

    Route::get('/import', [ImportController::class, 'index'])->middleware('permission:create_health_activity_report|update_health_report_settings')->name('import');
    Route::get('/import/preview', fn () => redirect()->route('report.health.import'));
    Route::post('/import/preview',[ImportController::class, 'preview'])->middleware('permission:create_health_activity_report|update_health_report_settings')->name('import.preview');
    Route::post('/import/commit', [ImportController::class, 'commit'])->middleware('permission:create_health_activity_report|update_health_report_settings')->name('import.commit');
    Route::get('/import/template', [ImportController::class, 'template'])->middleware($read)->name('import.template');
    Route::get('/import/preview-export', [ImportController::class, 'exportPreview'])->middleware('permission:create_health_activity_report|update_health_report_settings')->name('import.preview-export');

    Route::get('/analysis', [AnalysisController::class, 'index'])->middleware($read)->name('analysis');
    Route::get('/his', [HisController::class, 'index'])->middleware('permission:create_health_activity_report|update_health_report_settings')->name('his');
    Route::get('/his/preview', fn () => redirect()->route('report.health.his'));
    Route::post('/his/preview', [HisController::class, 'preview'])->middleware('permission:create_health_activity_report|update_health_report_settings')->name('his.preview');
    Route::post('/his/commit', [HisController::class, 'commit'])->middleware('permission:create_health_activity_report|update_health_report_settings')->name('his.commit');
    $hrm = 'permission:create_health_activity_report|update_health_report_settings';
    Route::get('/hrm/people', [HrController::class, 'people'])->middleware($read)->name('hr.people');
    Route::get('/hrm/{tab?}', [HrController::class, 'index'])->middleware($hrm)->whereIn('tab', ['snapshot', 'skills', 'quality'])->name('hr');
    Route::post('/hrm/snapshot', [HrController::class, 'save'])->middleware($hrm)->name('hr.save');
    Route::delete('/hrm/snapshot/{period}', [HrController::class, 'destroy'])->middleware('permission:delete_health_activity_report|delete_health_report_settings')->whereNumber('period')->name('hr.destroy');
    Route::post('/hrm/skills', [HrController::class, 'saveSkills'])->middleware($manage)->name('hr.skills');
    Route::post('/hrm/link', [HrController::class, 'link'])->middleware($manage)->name('hr.link');
    Route::post('/hrm/employee-types', [HrController::class, 'setEmployeeTypes'])->middleware($hrm)->name('hr.employee-types');
    Route::delete('/his/batches/{batch}',[HisController::class, 'destroy'])->middleware('permission:delete_health_activity_report|delete_health_report_settings')->whereNumber('batch')->name('his.destroy');

    Route::get('/issues', [IssueController::class, 'index'])->middleware($read)->name('issues');
    Route::post('/issues', [IssueController::class, 'store'])->middleware($enter)->name('issues.store');
    Route::put('/issues/{issue}', [IssueController::class, 'update'])->middleware($enter)->whereNumber('issue')->name('issues.update');
    Route::delete('/issues/{issue}', [IssueController::class, 'destroy'])->middleware('permission:delete_health_activity_report|delete_health_report_settings')->whereNumber('issue')->name('issues.destroy');
    Route::get('/issues/export', [IssueController::class, 'export'])->middleware($read)->name('issues.export');

    Route::get('/reports', [SummaryController::class, 'index'])->middleware($read)->name('reports');
    Route::get('/reports/print', [SummaryController::class, 'print'])->middleware($read)->name('reports.print');
    Route::get('/reports/export', [SummaryController::class, 'export'])->middleware($read)->name('reports.export');

    Route::get('/structure', [StructureController::class, 'index'])->middleware($settings)->name('structure');
    Route::post('/structure/departments', [StructureController::class, 'saveDepartment'])->middleware($manage)->name('structure.departments.save');
    Route::delete('/structure/departments/{department}', [StructureController::class, 'destroyDepartment'])->middleware('permission:delete_health_report_settings')->whereNumber('department')->name('structure.departments.destroy');
    Route::post('/structure/units', [StructureController::class, 'saveUnit'])->middleware($manage)->name('structure.units.save');
    Route::delete('/structure/units/{unit}', [StructureController::class, 'destroyUnit'])->middleware('permission:delete_health_report_settings')->whereNumber('unit')->name('structure.units.destroy');

    Route::get('/users', [UserUnitController::class, 'index'])->middleware($settings)->name('users');
    Route::post('/users', [UserUnitController::class, 'store'])->middleware($manage)->name('users.store');
    Route::delete('/users/{user}', [UserUnitController::class, 'destroy'])->middleware($manage)->whereNumber('user')->name('users.destroy');

    Route::get('/settings/{tab?}', [SettingController::class, 'index'])->middleware($settings)->whereIn('tab', ['indicators', 'periods', 'mappings', 'master'])->name('settings');
    Route::post('/settings/indicators', [SettingController::class, 'saveIndicator'])->middleware($manage)->name('settings.indicators.save');
    Route::get('/settings/his-elements', [SettingController::class, 'hisElements'])->middleware($manage)->name('settings.his-elements');
    Route::post('/settings/indicators/suggest',[SettingController::class, 'suggestIndicatorSettings'])->middleware($manage)->name('settings.indicators.suggest');
    Route::delete('/settings/indicators/{indicator}',[SettingController::class, 'destroyIndicator'])->middleware('permission:delete_health_report_settings')->whereNumber('indicator')->name('settings.indicators.destroy');
    Route::post('/settings/periods/generate', [SettingController::class, 'generatePeriods'])->middleware($manage)->name('settings.periods.generate');
    Route::put('/settings/periods/{period}', [SettingController::class, 'updatePeriod'])->middleware($manage)->whereNumber('period')->name('settings.periods.update');
    Route::post('/settings/mappings', [SettingController::class, 'saveMappings'])->middleware($manage)->name('settings.mappings.save');
    Route::delete('/settings/mappings/{mapping}', [SettingController::class, 'destroyMapping'])->middleware('permission:delete_health_report_settings')->whereNumber('mapping')->name('settings.mappings.destroy');
    Route::post('/settings/mappings/test', [SettingController::class, 'testMappings'])->middleware($manage)->name('settings.mappings.test');
    Route::post('/settings/master', [SettingController::class, 'importMaster'])->middleware($manage)->name('settings.master.import');
});

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'report', 'middleware' => 'auth'], function () {
    Route::name('report.')->group(function () {
        Route::controller(ReportController::class)->group(function () {

            //Sales Report Route
            Route::get('/sales-report', 'sales_report')->name('sales-report');
            Route::get('/sale-report-casher', 'sale_report_casher')->name('sale-report-casher');
            Route::get('/userwise-sales-report', 'userwise_sales_report')->name('userwise-sales-report');
            Route::get('/day-wise-sales-report', 'day_wise_sales_report')->name('daywise-sales-report');
            Route::get('/sales-due-report', 'sales_due_report')->name('sales-due-report');
            Route::get('/sales-return', 'sales_return')->name('sales-return');
            Route::get('/profit-report-sales-wise', 'profit_report_sales_wise')->name('profit-report-sales-wise');
            Route::get('/undelivered-sale-report', 'undelivered_sale_report')->name('undelivered-sale-report');
            Route::get('/supplier-wise-sale-profit-report', 'supplier_wise_sale_profit_report')->name('supplier-wise-sale-profit-report');
            Route::get('/cash-register-report', 'cashRegisterReport')->name('cash_register_report');
            Route::get('/category-wise-sale-report', 'CategoryWiseSalesReport')->name('category_wise_sale_report');

            //Purchase Report Route
            Route::get('/purchase-summary-report', 'purchaseSummaryReport')->name('purchases_summary_report');
            Route::get('/purchase-report', 'purchase_report')->name('purchase-report');
            Route::get('/purchase-details-show/{id:id}', 'purchaseDetailsShow')->name('purchase_details_show');
            Route::get('/receive-report', 'receive_report')->name('receive-report');
            Route::get('/receive-details-show/{id:id}', 'receiveDetailsShow')->name('receive_details_show');
            Route::get('/category-wise-purchase-report', 'CategoryWisePurchaseReport')->name('category_wise_purchase_report');

            //Warehouse Wise Report Route
            Route::get('/warehouse-wise-report', 'warehouseWiseReport')->name('warehouse_wise_report');
            Route::get('/warehouse-wise-product-report', 'warehouseWiseProductReport')->name('warehouse_wise_product_report');
            Route::get('/warehouse-wise-product-report/get-response', 'getResponseWarehouseWiseProductReport');

            //Alert Report
            Route::get('/alert-product-qty-report', 'alert_product_qty_report')->name('alert-product-qty-report');

            //dropdown data
            Route::post('/get-product', 'all_product_dropdown')->name('get-product');
            Route::post('/get-category', 'all_category_dropdown')->name('get-category');
            Route::post('/get-supplier', 'all_supplier_dropdown')->name('get-supplier');

            //cash register
            Route::get('/cash-register', 'cashRegister')->name('cash-register');
        });
    });
});
