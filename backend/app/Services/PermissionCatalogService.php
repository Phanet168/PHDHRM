<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\Permission as MenuPermission;

/**
 * Read-side catalog that groups the EXISTING Spatie permissions into
 * module / resource / action / display_name, for the future Access Control
 * Center UI and the capabilities read model.
 *
 * This does not rename or duplicate any permission. Module classification is
 * a best-effort heuristic over permission name patterns (most permissions in
 * this project follow a "verb_resource" convention, plus a "module.action"
 * convention used only by the Planning module). Display names reuse the
 * already-populated `per_menus.menu_name` (the same data source that already
 * drives the role/permission admin screens), with a humanized fallback for
 * anything not linked to a menu.
 */
class PermissionCatalogService
{
    /**
     * First matching regex wins. Checked against the raw permission name.
     *
     * @var array<string, string>
     */
    private const MODULE_RULES = [
        '/^planning\./' => 'planning',
        '/pharmaceutical_management|_pharm_/' => 'pharmaceutical',
        '/correspondence/' => 'correspondence',
        '/voucher|ledger|chart_of_accounts|financial_year|opening_balance|trial_balance|profit_loss|balance_sheet|_subtype$|_quarter$|predefine_accounts/' => 'accounts',
        '/^(read|create|update|delete)_(sales?_|sale_report|purchase|warehouse|stock_alert|category_wise|supplier_wise|cash_register|goods_received|day_wise_sales|user_wise_sales|undelivered_sales|sales_due|sales_return)/' => 'report',
        '/^(read|create|update|delete|destroy)_currency|^(read|update)_application$|mail_setup|tax_settings|^(read|create|update|delete)_delivery$|language_list|doc_expired|zkt|_backup/' => 'setting',
        '/_user_list$|^(read|create|update|delete)_role|_menu$|user_type/' => 'user_management',
    ];

    private const MODULE_LABELS = [
        'planning' => 'Planning',
        'pharmaceutical' => 'Pharmaceutical',
        'correspondence' => 'Correspondence',
        'accounts' => 'Accounts',
        'report' => 'Report',
        'setting' => 'Setting',
        'user_management' => 'User Management',
        'human_resource' => 'Human Resource',
    ];

    /**
     * verb/prefix => normalized action name.
     *
     * @var array<string, string>
     */
    private const ACTION_PREFIXES = [
        'create' => 'create',
        'read' => 'view',
        'view' => 'view',
        'update' => 'update',
        'edit' => 'update',
        'delete' => 'delete',
        'destroy' => 'delete',
        'approve' => 'approve',
        'reject' => 'reject',
        'export' => 'export',
        'manage' => 'manage',
        'review' => 'review',
        'submit' => 'submit',
        'consolidate' => 'consolidate',
        'comment' => 'comment',
        'calculate' => 'calculate',
        'setting' => 'manage',
    ];

    /**
     * @var Collection<string, string|null>|null permission name => per_menus.menu_name
     * Instance-scoped (not static) so it never leaks stale data across
     * requests or test cases within the same PHP process.
     */
    private ?Collection $menuNameByPermission = null;

    public function entryFor(string $permissionName): array
    {
        $module = $this->classifyModule($permissionName);
        [$action, $resource] = $this->splitNameParts($permissionName);
        $menuName = $this->menuNameFor($permissionName);

        return [
            'permission' => $permissionName,
            'module' => $module,
            'module_label' => self::MODULE_LABELS[$module] ?? Str::title(str_replace('_', ' ', $module)),
            'resource' => $resource,
            'action' => $action,
            'display_name' => $menuName ?: Str::title(str_replace(['_', '.'], ' ', $permissionName)),
        ];
    }

    /** @return Collection<int, array> every existing permission, cataloged */
    public function catalog(): Collection
    {
        return MenuPermission::query()
            ->pluck('name')
            ->map(fn (string $name) => $this->entryFor($name))
            ->values();
    }

    /** @return Collection<string, Collection> catalog entries grouped by module key */
    public function groupedByModule(): Collection
    {
        return $this->catalog()->groupBy('module');
    }

    private function classifyModule(string $name): string
    {
        foreach (self::MODULE_RULES as $pattern => $module) {
            if (preg_match($pattern, $name) === 1) {
                return $module;
            }
        }

        return 'human_resource';
    }

    /** @return array{0: string, 1: string} [action, resource] */
    private function splitNameParts(string $name): array
    {
        if (str_contains($name, '.')) {
            $segments = explode('.', $name);
            $action = array_pop($segments);

            return [self::ACTION_PREFIXES[$action] ?? $action, implode('.', $segments)];
        }

        foreach (self::ACTION_PREFIXES as $prefix => $normalized) {
            if (Str::startsWith($name, $prefix . '_')) {
                return [$normalized, Str::after($name, $prefix . '_')];
            }
        }

        return ['other', $name];
    }

    private function menuNameFor(string $permissionName): ?string
    {
        if ($this->menuNameByPermission === null) {
            $this->menuNameByPermission = MenuPermission::query()
                ->with('perMenu:id,menu_name')
                ->get(['id', 'name', 'per_menu_id'])
                ->mapWithKeys(fn (MenuPermission $permission) => [
                    $permission->name => $permission->perMenu?->menu_name,
                ]);
        }

        return $this->menuNameByPermission->get($permissionName);
    }
}
