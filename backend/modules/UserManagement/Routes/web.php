<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\UserTypeController;
use Modules\UserManagement\Http\Controllers\RoleManagementController;
use Modules\UserManagement\Http\Controllers\UserManagementController;
use Modules\UserManagement\Http\Controllers\PasswordSettingController;

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

Route::group(['middleware' => 'auth'], function () {

    Route::resource('user-types' , UserTypeController::class);
    Route::resource('password-settings' , PasswordSettingController::class);

    //for select2 search
    Route::get('get-user-by-ajax',  [UserManagementController::class, 'getUserByAjax'])->name('user.search');

    // User Profile Update
    Route::post('/user-profile-update', [UserManagementController::class, 'update'])->name('profile.update');
    Route::post('/user-profile-image-update/{id:id}', [UserManagementController::class, 'profilePictureUpdate'])->name('profile_image.update');
    Route::post('/user-profile-cover-image-update/{id:id}', [UserManagementController::class, 'coverImageUpdate'])->name('profile_cover_image.update');
    Route::post('/user-change-password', [UserManagementController::class, 'updatePassword'])->name('profile.changePassword');

    Route::name('role.')->group(function () {
        Route::controller(RoleManagementController::class)->group(function () {
            Route::get('role-list', 'roleList')->name('list');
            Route::get('role-add', 'roleCreate')->name('add');
            Route::get('role-view', 'roleView')->name('view');
            Route::post('role-store', 'roleStore')->name('store');
            Route::get('role-edit/{role}', 'roleEdit')->name('edit');
            Route::post('role-update', 'roleUpdate')->name('update');
            Route::post('role-delete', 'roleDelete')->name('delete');

            //menu
            Route::get('menu-list', 'menuList')->name('menu.list');
            Route::get('menu-add', 'menuCreate')->name('menu.add');
            Route::post('menu-store', 'menuStore')->name('menu.store');
            Route::get('menu-edit/{id}', 'menuEdit')->name('menu.edit');
            Route::post('menu-update/{menupermission:uuid}', 'menuUpdate')->name('menu.update');
            Route::post('menu-delete', 'menuDelete')->name('menu.delete');

            //permission
            Route::get('permission-list', 'permissionList')->name('permission.list');
            Route::get('permission-add', 'permissionCreate')->name('permission.add');
            Route::post('permission-store', 'permissionStore')->name('permission.store');
            Route::get('permission-edit/{permission}', 'permissionEdit')->name('permission.edit');
            Route::post('permission-update/{permission:uuid}', 'permissionUpdate')->name('permission.update');
            Route::post('permission-delete', 'permissionDelete')->name('permission.delete');

        });

        Route::controller(UserManagementController::class)->group(function () {
            //user
            Route::get('user-list', 'userList')->name('user.list');
            Route::get('user-add', 'userCreate')->name('user.add');
            Route::post('user-store', 'userStore')->name('user.store');
            Route::get('user-edit/{user}', 'userEdit')->name('user.edit');
            Route::post('user-update/{user}', 'userUpdate')->name('user.update');
            Route::post('user-delete', 'userDelete')->name('user.delete');
            Route::post('user-device-store/{user}', 'userDeviceStore')->name('user.device.store');
            Route::post('user-device-status/{device}', 'userDeviceStatus')->name('user.device.status');
            Route::delete('user-device-delete/{device}', 'userDeviceDelete')->name('user.device.delete');

        });

        // Phase 2 Access Control Center foundation: read-only "Effective Access"
        // data endpoint (no UI yet). Gated behind the same read_user_list
        // permission already used to view the user list/edit screens above.
        Route::get(
            'user-effective-access/{user}',
            [\App\Http\Controllers\AccessControlAdminController::class, 'effectiveAccess']
        )->name('user.effective-access');
    });

    // Phase 3A: Access Control Center (មជ្ឈមណ្ឌលគ្រប់គ្រងសិទ្ធិ). One unified
    // admin screen over the Phase 2 foundation. Every action below reuses an
    // existing Spatie permission already used by the role/user screens above
    // -- no new "manage everything" permission was created for this.
    Route::prefix('access-control')->name('access-control.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AccessControlCenterController::class, 'index'])
            ->middleware('permission:read_role_list|read_user_list')->name('index');
        Route::get('/summary', [\App\Http\Controllers\AccessControlCenterController::class, 'summary'])
            ->middleware('permission:read_role_list|read_user_list')->name('summary');
        Route::get('/catalog', [\App\Http\Controllers\AccessControlCenterController::class, 'catalog'])
            ->middleware('permission:read_role_list|read_user_list')->name('catalog');

        Route::get('/roles', [\App\Http\Controllers\AccessControlCenterController::class, 'roles'])
            ->middleware('permission:read_role_list')->name('roles.index');
        Route::get('/roles/{role}', [\App\Http\Controllers\AccessControlCenterController::class, 'roleShow'])
            ->middleware('permission:read_role_list')->name('roles.show');
        Route::post('/roles', [\App\Http\Controllers\AccessControlCenterController::class, 'roleStore'])
            ->middleware('permission:create_role_list')->name('roles.store');
        Route::put('/roles/{role}', [\App\Http\Controllers\AccessControlCenterController::class, 'roleUpdate'])
            ->middleware('permission:update_role_list')->name('roles.update');
        Route::put('/roles/{role}/permissions', [\App\Http\Controllers\AccessControlCenterController::class, 'rolePermissionsUpdate'])
            ->middleware('permission:update_role_list')->name('roles.permissions.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\AccessControlCenterController::class, 'roleDestroy'])
            ->middleware('permission:delete_role_list')->name('roles.destroy');

        Route::get('/users', [\App\Http\Controllers\AccessControlCenterController::class, 'usersSearch'])
            ->middleware('permission:read_user_list')->name('users.search');
        Route::get('/users/{user}', [\App\Http\Controllers\AccessControlCenterController::class, 'userShow'])
            ->middleware('permission:read_user_list')->name('users.show');
        Route::put('/users/{user}/roles', [\App\Http\Controllers\AccessControlCenterController::class, 'userRolesUpdate'])
            ->middleware('permission:update_user_list')->name('users.roles.update');
        Route::put('/users/{user}/direct-permissions', [\App\Http\Controllers\AccessControlCenterController::class, 'userDirectPermissionsUpdate'])
            ->middleware('permission:update_user_list')->name('users.direct-permissions.update');

        // Phase 3B: Organization Scope. Reuses the SAME permissions that
        // already protect the org-governance UserAssignment screen
        // (UserAssignmentController) since these endpoints write to the same
        // underlying UserAssignment rows through the same GovernanceAssignmentService.
        Route::get('/org-tree', [\App\Http\Controllers\AccessControlCenterController::class, 'orgTree'])
            ->middleware('permission:read_org_governance|read_department|read_user_list')->name('org-tree');
        Route::get('/users/{user}/scopes', [\App\Http\Controllers\AccessControlCenterController::class, 'userScopes'])
            ->middleware('permission:read_org_governance|read_department|read_user_list')->name('users.scopes.index');
        Route::put('/users/{user}/scopes/{groupKey}', [\App\Http\Controllers\AccessControlCenterController::class, 'userScopeUpdate'])
            ->middleware('permission:update_org_governance|update_department')->name('users.scopes.update');

        // Approval Authority (ការអនុម័ត). Reuses the SAME permissions and the
        // SAME WorkflowDefinition/WorkflowDefinitionStep tables already used
        // by the existing Workflow Policies screen -- no new permission, no
        // new schema.
        Route::get('/approvals', [\App\Http\Controllers\AccessControlCenterController::class, 'approvals'])
            ->middleware('permission:read_org_governance|read_department')->name('approvals.index');
        Route::get('/approvals/options', [\App\Http\Controllers\AccessControlCenterController::class, 'approvalOptions'])
            ->middleware('permission:read_org_governance|read_department')->name('approvals.options');
        Route::get('/approvals/{workflowDefinition}', [\App\Http\Controllers\AccessControlCenterController::class, 'approvalShow'])
            ->middleware('permission:read_org_governance|read_department')->name('approvals.show');
        Route::put('/approvals/{workflowDefinition}', [\App\Http\Controllers\AccessControlCenterController::class, 'approvalUpdate'])
            ->middleware('permission:update_org_governance|update_department')->name('approvals.update');
        Route::get('/users/{user}/approval-authority', [\App\Http\Controllers\AccessControlCenterController::class, 'userApprovalAuthority'])
            ->middleware('permission:read_org_governance|read_department|read_user_list')->name('users.approval-authority');

        // Delegation (ផ្ទេរសិទ្ធិ), Phase 3D.1. Reuses the SAME permissions as
        // Organization Scope editing -- no new permission was created.
        Route::get('/delegations', [\App\Http\Controllers\AccessControlCenterController::class, 'delegations'])
            ->middleware('permission:read_org_governance|read_department')->name('delegations.index');
        Route::get('/delegations/options', [\App\Http\Controllers\AccessControlCenterController::class, 'delegationOptions'])
            ->middleware('permission:read_org_governance|read_department')->name('delegations.options');
        Route::get('/delegations/authorities/{user}', [\App\Http\Controllers\AccessControlCenterController::class, 'delegationAuthorities'])
            ->middleware('permission:read_org_governance|read_department')->name('delegations.authorities');
        Route::get('/delegations/boundary/{user}', [\App\Http\Controllers\AccessControlCenterController::class, 'delegationBoundary'])
            ->middleware('permission:read_org_governance|read_department')->name('delegations.boundary');
        Route::post('/delegations', [\App\Http\Controllers\AccessControlCenterController::class, 'delegationStore'])
            ->middleware('permission:update_org_governance|update_department')->name('delegations.store');
        Route::post('/delegations/{delegation}/revoke', [\App\Http\Controllers\AccessControlCenterController::class, 'delegationRevoke'])
            ->middleware('permission:update_org_governance|update_department')->name('delegations.revoke');

        // Audit History (Phase 3E), read-only. Reuses the same broad read
        // permission already protecting the Access Control Center index --
        // no new permission was created.
        Route::get('/audit-history', [\App\Http\Controllers\AccessControlCenterController::class, 'auditHistory'])
            ->middleware('permission:read_role_list|read_user_list')->name('audit-history.index');
        Route::get('/audit-history/options', [\App\Http\Controllers\AccessControlCenterController::class, 'auditHistoryOptions'])
            ->middleware('permission:read_role_list|read_user_list')->name('audit-history.options');
    });
});
