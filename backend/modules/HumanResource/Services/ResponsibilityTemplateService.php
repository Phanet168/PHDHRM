<?php

namespace Modules\HumanResource\Services;

use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\ResponsibilityTemplate;
use Modules\HumanResource\Support\ModuleGovernancePolicyRegistry;

class ResponsibilityTemplateService
{
    public function __construct(private readonly ModuleGovernancePolicyRegistry $policyRegistry)
    {
    }

    public function moduleActionMap(): array
    {
        return $this->policyRegistry->actionMap();
    }

    public function moduleOptions(): array
    {
        return $this->policyRegistry->moduleKeys();
    }

    public function actionLabels(): array
    {
        return $this->policyRegistry->actionLabels();
    }

    public function activeTemplates(?string $moduleKey = null): Collection
    {
        $moduleKey = trim(mb_strtolower((string) $moduleKey));

        return ResponsibilityTemplate::query()
            ->withoutGlobalScope('sortByLatest')
            ->with([
                'responsibility:id,code,name,name_km',
                'position:id,position_name,position_name_km',
            ])
            ->active()
            ->when($moduleKey !== '', fn ($q) => $q->where('module_key', $moduleKey))
            ->orderBy('module_key')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function groupedOptions(?string $moduleKey = null): array
    {
        return $this->activeTemplates($moduleKey)
            ->groupBy(fn (ResponsibilityTemplate $template) => (string) $template->module_key)
            ->map(function (Collection $templates) {
                return $templates->map(function (ResponsibilityTemplate $template) {
                    $responsibilityName = (string) ($template->responsibility?->name_km ?: $template->responsibility?->name ?: '');
                    $positionName = (string) ($template->position?->position_name_km ?: $template->position?->position_name ?: '');
                    $label = $template->displayName();
                    if ($responsibilityName !== '') {
                        $label .= ' - ' . $responsibilityName;
                    }
                    if ($positionName !== '') {
                        $label .= ' - ' . $positionName;
                    }

                    return [
                        'id' => (int) $template->id,
                        'module_key' => (string) $template->module_key,
                        'template_key' => (string) $template->template_key,
                        'label' => $label,
                        'responsibility_id' => (int) ($template->responsibility_id ?? 0),
                        'position_id' => !empty($template->position_id) ? (int) $template->position_id : null,
                        'default_scope_type' => (string) ($template->default_scope_type ?? ''),
                        'action_presets' => $this->normalizeActionKeys($template->action_presets_json ?? []),
                    ];
                })->values()->all();
            })
            ->all();
    }

    public function normalizeActionKeys(array $actions): array
    {
        return collect($actions)
            ->map(fn ($item) => trim(mb_strtolower((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
