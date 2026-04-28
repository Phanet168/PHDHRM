<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\DeviceAccessRequestAdminController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\SecurityController;
use App\Http\Controllers\Auth\TelegramPasswordResetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'rootRedirect'])->name('root.redirect');

Route::get('dev/artisan-http/storage-link', [HomeController::class, 'storageLinkTools'])->name('dev.storage-link');

Auth::routes([
    'register' => false,
]);
Route::post('/password/telegram', [TelegramPasswordResetController::class, 'send'])->name('password.telegram');

Route::get('get-localization-strings', [LocalizationController::class, 'index'])->name('get-localization-strings');
Route::post('get-localization-strings', [LocalizationController::class, 'store']);

Route::group(['middleware' => ['auth', 'isAdmin']], function () {
    Route::get('dashboard', [HomeController::class, 'index'])->name('home');
    Route::post('departmentWiseAttendance/{type}', [HomeController::class, 'attendanceDepartmentWise'])->name('departmentWiseAttendance');
    Route::post('positionWiseRecruitment/{type}', [HomeController::class, 'recruitmentPositionWise'])->name('positionWiseRecruitment');

    // Device Access Request Routes
    Route::get('device-access-requests', [DeviceAccessRequestAdminController::class, 'index'])->name('device-access-requests.index');
    Route::post('device-access-requests/{deviceAccessRequest}/review', [DeviceAccessRequestAdminController::class, 'review'])->name('device-access-requests.review');
});

Route::group(['middleware' => ['auth']], function () {
    Route::get('/security/phone-required', [SecurityController::class, 'showPhoneRequired'])->name('security.phone.required.show');
    Route::post('/security/phone-required', [SecurityController::class, 'updatePhoneRequired'])->name('security.phone.required.update');

    Route::get('/security/telegram/connect', [SecurityController::class, 'showTelegramConnect'])->name('security.telegram.connect.show');
    Route::post('/security/telegram/connect/regenerate', [SecurityController::class, 'regenerateTelegramConnect'])->name('security.telegram.connect.regenerate');
    Route::post('/security/telegram/connect/sync', [SecurityController::class, 'syncTelegramConnect'])->name('security.telegram.connect.sync');

    Route::get('/security/otp', [OtpVerificationController::class, 'show'])->name('security.otp.show');
    Route::post('/security/otp/verify', [OtpVerificationController::class, 'verify'])->name('security.otp.verify');
    Route::post('/security/otp/resend', [OtpVerificationController::class, 'resend'])->name('security.otp.resend');
});

Route::group(['middleware' => ['auth']], function () {
    Route::get('/dashboard/home', [HomeController::class, 'staffHome'])->name('staffHome');
    Route::get('/dashboard/employee', [HomeController::class, 'myProfile'])->name('myProfile');
    Route::get('/dashboard/employee/edit', [HomeController::class, 'editMyProfile'])->name('editMyProfile');
    // Backward-compatibility: some old links still open /dashboard/employee/{id|undefined}
    Route::get('/dashboard/employee/{legacy}', [HomeController::class, 'redirectLegacyEmployeeProfile'])
        ->where('legacy', '.*');

    Route::get('/dashboard/profile', [HomeController::class, 'empProfile'])->name('empProfile');

    Route::get('/dashboard/today-yesterday-sales', [HomeController::class, 'todayAndYesterdaySales'])->name('todayAndYesterdaySales');
    Route::get('/dashboard/sales-return-amount', [HomeController::class, 'salesReturnAmount'])->name('salesReturnAmount');

    Route::post('/dashboard/total-sales-amount', [HomeController::class, 'totalSalesAmount'])->name('totalSalesAmount');
    Route::post('/dashboard/stock-valuation', [HomeController::class, 'stockValuationData'])->name('stockValuation');
    Route::post('/dashboard/invoice-due', [HomeController::class, 'invoiceDue'])->name('invoiceDue');
    Route::post('/dashboard/total-expense', [HomeController::class, 'totalExpense'])->name('totalExpense');
    Route::post('/dashboard/purchase-return-amount', [HomeController::class, 'purchaseReturnAmount'])->name('purchaseReturnAmount');
    Route::post('/dashboard/purchase-due', [HomeController::class, 'purchaseDue'])->name('purchaseDue');

    Route::post('/dashboard/sales/report', [HomeController::class, 'last30daySales'])->name('last30daySales');
    Route::post('/dashboard/purchase/report', [HomeController::class, 'last30dayPurchase'])->name('last30dayPurchase');
    Route::get('/dashboard/stock-alert', [HomeController::class, 'getStockAlertProductList'])->name('getStockAlertProductList');
    Route::get('/dashboard/low-stock', [HomeController::class, 'getLowStockProductList'])->name('getLowStockProductList');
    Route::post('/dashboard/income-expense/report', [HomeController::class, 'incomeExpense'])->name('incomeExpense');
    Route::post('/dashboard/warehouse-wise-stock/report', [HomeController::class, 'warehouseWiseStock'])->name('warehouseWiseStock');
    Route::post('/dashboard/bank-and-cash-balance', [HomeController::class, 'bankAndCashBalance'])->name('bankAndCashBalance');
    Route::post('/dashboard/counter-wise-sale', [HomeController::class, 'counterWiseSale'])->name('counterWiseSale');
    Route::post('/dashboard/cashier-wise-sale', [HomeController::class, 'cashierWiseSale'])->name('cashierWiseSale');
    Route::post('/dashboard/most-selling-product', [HomeController::class, 'mostSellingProduct'])->name('mostSellingProduct');
    Route::post('/dashboard/less-selling-product', [HomeController::class, 'lessSellingProduct'])->name('lessSellingProduct');

    // For employee dashboard reports/charts
    Route::get('/home/yearly_employee_points', [HomeController::class, 'yearlyEmployeePoints'])->name('home.yearly-employee-points');
    Route::get('/home/monthly_employee_points', [HomeController::class, 'monthlyEmployeePoints'])->name('home.monthly-employee-points');
    Route::get('/home/monthly_employee_attendence', [HomeController::class, 'monthlyEmployeeAttendence'])->name('home.monthly-employee-attendence');
});

//All Clear
Route::get('/all-clear', [HomeController::class, 'allClear'])->name('all_clear');

Route::get('/insert-language', [HomeController::class, 'insertLanguage'])->name('dev.insert-language');
Route::get('/insert-language-value', [HomeController::class, 'insertLanguageValue'])->name('dev.insert-language-value');
Route::get('test1', [HomeController::class, 'testSession'])->name('dev.test-session');
