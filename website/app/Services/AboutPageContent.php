<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Page;

/** Adapts the existing Pages blocks; no separate records or page builder. */
class AboutPageContent
{
    public const SECTIONS = [
        'history' => 'ប្រវត្តិ និងបេសកកម្ម',
        'vision' => 'ចក្ខុវិស័យ',
        'mission' => 'បេសកកម្ម',
        'roles' => 'តួនាទី និងភារកិច្ច',
        'values' => 'គោលតម្លៃ',
        'timeline' => 'ប្រវត្តិ និងការអភិវឌ្ឍ',
        'structure' => 'រចនាសម្ព័ន្ធ',
        'leadership' => 'ថ្នាក់ដឹកនាំ',
        'offices' => 'ការិយាល័យចំណុះ',
        'files' => 'ឯកសារពាក់ព័ន្ធ',
        'contact' => 'ទីតាំង និងទំនាក់ទំនង',
    ];

    public function load(): array
    {
        $page = Page::published()->where('slug', 'about')->first()
            ?? Page::published()->where('template', 'about')->orderBy('id')->first();
        $sections = collect(self::SECTIONS)->map(fn () => collect());
        $cards = collect();
        $timeline = collect();
        $files = collect();
        $map = null;
        $quote = null;
        $section = 'history';

        if ($page) {
            app(PageRendererService::class)->forShow($page);
            foreach ($page->blocks as $block) {
                if ($block->block_type === 'heading' && ($key = $this->sectionKey($block->get('text', '')))) {
                    $section = $key;
                    continue;
                }
                if ($block->block_type === 'map' && $this->validMap($block->data)) {
                    $map ??= $block;
                } elseif ($block->block_type === 'quote' && filled($block->get('text')) && in_array($section, ['history', 'mission'])) {
                    if (! $quote) {
                        $quote = $block;
                    } else {
                        $sections[$section]->push($block);
                    }
                } elseif ($block->block_type === 'cards' && $section === 'timeline') {
                    foreach ($block->get('cards', []) as $card) {
                        // Editors use the existing card title for the year + optional event title.
                        if (preg_match('/^([0-9០-៩]{4})(?:\s+|[\s:–—-]+)?(.*)$/u', trim($card['title'] ?? ''), $match) && filled($card['text'] ?? null)) {
                            $timeline->push(['year' => $match[1], 'title' => $match[2], 'text' => $card['text']]);
                        }
                    }
                } elseif ($block->block_type === 'cards' && in_array($section, ['history', 'vision', 'mission', 'roles', 'values'])) {
                    foreach ($block->get('cards', []) as $card) {
                        $key = $this->sectionKey($card['title'] ?? '');
                        if (in_array($key, ['vision', 'mission', 'roles', 'values']) && filled($card['text'] ?? null)) {
                            $cards->put($key, $card);
                        } elseif (filled($card['title'] ?? null) || filled($card['text'] ?? null)) {
                            // Preserve non-core cards rather than dropping CMS content.
                            $copy = clone $block;
                            $copy->data = ['cards' => [$card]];
                            $sections[$section]->push($copy);
                        }
                    }
                } elseif ($block->block_type === 'button' && $section === 'files') {
                    $path = parse_url($block->get('url', ''), PHP_URL_PATH);
                    $storageAt = is_string($path) ? strpos($path, '/storage/') : false;
                    $media = $storageAt !== false ? Media::where('disk_path', rawurldecode(substr($path, $storageAt + 9)))->first() : null;
                    if ($media && ! $media->fileMissing()) {
                        $files->push(['title' => $block->get('text') ?: $media->original_filename, 'media' => $media]);
                    } elseif ($storageAt === false) {
                        $sections[$section]->push($block);
                    }
                } elseif ($block->block_type !== 'contact_info' && $block->block_type !== 'map') {
                    $sections[$section]->push($block);
                }
            }
        } else {
            foreach (['vision', 'mission', 'roles', 'values'] as $key) {
                $cards->put($key, ['title' => self::SECTIONS[$key], 'text' => config("about.$key")]);
            }
        }

        // Dedicated heading + text blocks can also provide the core summaries.
        foreach (['vision', 'mission', 'roles', 'values'] as $key) {
            if (! $cards->has($key) && $sections[$key]->isNotEmpty()) {
                $summary = $sections[$key]->map(fn ($block) => strip_tags($block->get('html', $block->get('text', ''))))->filter()->implode(' ');
                if (filled($summary)) {
                    $cards->put($key, ['title' => self::SECTIONS[$key], 'text' => $summary]);
                }
            }
        }

        return compact('page', 'sections', 'cards', 'timeline', 'files', 'map', 'quote');
    }

    private function sectionKey(string $title): ?string
    {
        $title = trim($title);
        $aliases = ['ប្រវត្តិ' => 'history', 'គុណតម្លៃ' => 'values', 'គោលការណ៍' => 'values', 'គោលតម្លៃ / គោលការណ៍' => 'values', 'រចនាសម្ព័ន្ធអង្គភាព' => 'structure'];

        return $aliases[$title] ?? (array_search($title, self::SECTIONS, true) ?: (array_key_exists($title, self::SECTIONS) ? $title : null));
    }

    private function validMap(array $data): bool
    {
        return is_numeric($data['lat'] ?? null) && is_numeric($data['lng'] ?? null)
            && abs((float) $data['lat']) <= 90 && abs((float) $data['lng']) <= 180;
    }
}
