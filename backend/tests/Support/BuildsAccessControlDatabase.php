<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Minimal, Spatie-compatible in-memory sqlite schema for testing the Phase 2
 * AccessControlService/PermissionCatalogService/CapabilityController without
 * touching the application's real database. Mirrors the isolation approach
 * already used by BuildsAttendanceDatabase.
 */
trait BuildsAccessControlDatabase
{
    protected function createAccessControlDatabase(): void
    {
        config(['database.default' => 'access_control_testing', 'database.connections.access_control_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('full_name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->integer('user_type_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('guard_name');
            $t->unsignedBigInteger('per_menu_id')->nullable();
            $t->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
        });

        Schema::create('per_menus', function (Blueprint $t) {
            $t->id();
            $t->string('menu_name')->nullable();
            $t->unsignedBigInteger('parentmenu_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('system_roles', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->nullable();
            $t->string('code')->nullable();
            $t->string('name')->nullable();
            $t->boolean('can_approve')->default(false);
            $t->softDeletes();
            $t->timestamps();
        });

        // Empty legacy fallback table: OrgHierarchyAccessService::effectiveOrgRoles()
        // queries this when a user has no UserAssignment-derived org role, so it
        // must exist even when a test only exercises the modern UserAssignment path.
        Schema::create('user_org_roles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('department_id')->nullable();
            $t->string('org_role')->nullable();
            $t->unsignedBigInteger('system_role_id')->nullable();
            $t->string('scope_type')->nullable();
            $t->boolean('is_active')->default(true);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('user_assignments', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->nullable();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('position_id')->nullable();
            $t->unsignedBigInteger('responsibility_id')->nullable();
            $t->unsignedBigInteger('responsibility_template_id')->nullable();
            $t->string('scope_type')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->boolean('is_active')->default(true);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        DB::table('per_menus')->insert(['id' => 1, 'menu_name' => 'Employee', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function makeAccessControlUser(array $attributes = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'full_name' => 'Test User',
            'user_type_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return User::query()->findOrFail($id);
    }

    protected function makeAccessControlRole(string $name): int
    {
        return DB::table('roles')->insertGetId([
            'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function makeAccessControlPermission(string $name): int
    {
        return DB::table('permissions')->insertGetId([
            'name' => $name, 'guard_name' => 'web', 'per_menu_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
