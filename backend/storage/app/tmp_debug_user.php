<?php
use App\Models\User;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Entities\OrgRoleModulePermission;

$user = User::query()->where('email', 'phanet@gmail.com')->first();
if (!$user) {
    echo "USER_NOT_FOUND\n";
    return;
}

echo "USER=" . json_encode([
    'id' => (int)$user->id,
    'name' => (string)$user->full_name,
    'email' => (string)$user->email,
    'user_type_id' => (int)($user->user_type_id ?? 0),
], JSON_UNESCAPED_UNICODE) . "\n";

$roles = method_exists($user, 'roles') ? $user->roles()->pluck('name')->values()->all() : [];
echo "ROLES=" . json_encode($roles, JSON_UNESCAPED_UNICODE) . "\n";

$perms = method_exists($user, 'getAllPermissions')
    ? $user->getAllPermissions()->pluck('name')->filter(fn($p)=>str_contains($p,'correspondence'))->values()->all()
    : [];
echo "CORR_PERMISSIONS=" . json_encode($perms, JSON_UNESCAPED_UNICODE) . "\n";

$orgRoles = UserOrgRole::query()
    ->withoutGlobalScope('sortByLatest')
    ->with(['department:id,department_name','systemRole:id,code,name'])
    ->where('user_id', (int)$user->id)
    ->orderBy('id')
    ->get()
    ->map(function($r){
        return [
            'id' => (int)$r->id,
            'department_id' => (int)$r->department_id,
            'department_name' => (string)optional($r->department)->department_name,
            'org_role' => (string)$r->org_role,
            'system_role_code' => (string)optional($r->systemRole)->code,
            'scope_type' => (string)$r->scope_type,
            'effective_from' => $r->effective_from ? $r->effective_from->toDateString() : null,
            'effective_to' => $r->effective_to ? $r->effective_to->toDateString() : null,
            'is_active' => (bool)$r->is_active,
            'deleted_at' => $r->deleted_at ? $r->deleted_at->toDateTimeString() : null,
        ];
    })->values()->all();

echo "USER_ORG_ROLES=" . json_encode($orgRoles, JSON_UNESCAPED_UNICODE) . "\n";

$matrix = OrgRoleModulePermission::query()
    ->with('systemRole:id,code')
    ->where('module_key','correspondence')
    ->whereIn('action_key',['create_incoming','delegate','distribute'])
    ->orderBy('action_key')
    ->orderBy('org_role')
    ->get()
    ->map(function($m){
        return [
            'id'=>(int)$m->id,
            'action_key'=>(string)$m->action_key,
            'org_role'=>(string)$m->org_role,
            'system_role_code'=>(string)optional($m->systemRole)->code,
            'is_active'=>(bool)$m->is_active,
        ];
    })->values()->all();

echo "MATRIX=" . json_encode($matrix, JSON_UNESCAPED_UNICODE) . "\n";
