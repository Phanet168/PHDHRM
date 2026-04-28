<?php

namespace Modules\HumanResource\Support;

use Illuminate\Support\Collection;

class ModuleGovernancePolicyRegistry
{
    public function modules(): array
    {
        return collect((array) config('hr_governance.modules', []))
            ->mapWithKeys(fn ($definition, $key) => [trim(mb_strtolower((string) $key)) => (array) $definition])
            ->all();
    }

    public function moduleKeys(): array
    {
        return array_keys($this->modules());
    }

    public function actionMap(): array
    {
        return collect($this->modules())
            ->map(fn (array $definition) => array_keys((array) ($definition['actions'] ?? [])))
            ->all();
    }

    public function actionLabels(): array
    {
        return collect($this->modules())
            ->flatMap(fn (array $definition) => (array) ($definition['actions'] ?? []))
            ->mapWithKeys(fn ($label, $key) => [trim(mb_strtolower((string) $key)) => (string) $label])
            ->all();
    }

    public function workflowCatalog(): array
    {
        return collect($this->modules())
            ->map(function (array $definition) {
                return [
                    'label' => (string) ($definition['label'] ?? ''),
                    'request_types' => collect((array) ($definition['request_types'] ?? []))
                        ->map(fn ($label) => ['label' => (string) $label])
                        ->all(),
                ];
            })
            ->all();
    }

    public function templateDefinitions(?string $moduleKey = null): Collection
    {
        $moduleKey = trim(mb_strtolower((string) $moduleKey));

        return collect($this->modules())
            ->when($moduleKey !== '', fn (Collection $modules) => $modules->only([$moduleKey]))
            ->flatMap(function (array $definition, string $key) {
                return collect((array) ($definition['templates'] ?? []))
                    ->map(function (array $template) use ($key) {
                        $template['module_key'] = $key;
                        $template['action_presets_json'] = (array) ($template['actions'] ?? $template['action_presets_json'] ?? []);
                        unset($template['actions']);
                        return $template;
                    });
            })
            ->values();
    }
}
