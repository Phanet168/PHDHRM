<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StickyHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_bar_and_main_bar_are_wrapped_together_in_the_sticky_container(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $start = strpos($html, 'data-sticky-header');
        $topbar = strpos($html, 'public-header-topbar');
        $mainBar = strpos($html, '<header x-data="{ mobileOpen: false }"');
        $end = strpos($html, '</header>');
        $content = strpos($html, 'class="public-main"');

        $this->assertNotFalse($start);
        $this->assertTrue($start < $topbar && $topbar < $mainBar && $mainBar < $end && $end < $content, 'sticky wrapper must contain the top bar and the header, before the page content');
        $this->assertSame(1, substr_count($html, 'data-sticky-header'));
        // The wrapper closes right after the header, still before the page body.
        $this->assertMatchesRegularExpression('/<\/header>\s*<\/div>\s*<div class="public-main">/', $html);
    }

    #[DataProvider('publicPages')]
    public function test_every_public_page_pins_the_header(string $url): void
    {
        $this->get($url)->assertOk()->assertSee('class="public-sticky-header"', false);
    }

    public static function publicPages(): array
    {
        return [['/'], ['/news'], ['/legal-documents'], ['/contact'], ['/facilities'], ['/health-content'], ['/about']];
    }

    public function test_stylesheet_pins_the_wrapper_above_page_content_but_below_modals(): void
    {
        $css = file_get_contents(resource_path('css/header.css'));

        $this->assertMatchesRegularExpression('/\.public-sticky-header\s*\{[^}]*position:\s*sticky;[^}]*top:\s*0;/', $css);
        preg_match('/\.public-sticky-header\s*\{[^}]*z-index:\s*(\d+)/', $css, $z);
        $this->assertNotEmpty($z);
        // Above hero/slider layers and the back-to-top button (40), below the video modal (100).
        $this->assertGreaterThan(40, (int) $z[1]);
        $this->assertLessThan(100, (int) $z[1]);
        // Map layers are isolated so Leaflet's own z-indexes cannot paint over the header.
        $this->assertStringContainsString('.leaflet-container', $css);
        $this->assertStringContainsString('isolation: isolate', $css);
    }

    public function test_sticky_sidebars_and_anchors_clear_the_pinned_header(): void
    {
        $header = file_get_contents(resource_path('css/header.css'));
        $this->assertStringContainsString('scroll-padding-top: calc(var(--public-sticky-offset)', $header);

        foreach (['news.css', 'legal-documents.css', 'health-content.css'] as $file) {
            $css = file_get_contents(resource_path('css/'.$file));
            $this->assertStringContainsString('top: calc(var(--public-sticky-offset) + 20px)', $css, $file.' sticky offset must clear the header');
            $this->assertDoesNotMatchRegularExpression('/position:\s*sticky;\s*top:\s*(24|20)px/', $css, $file.' still pins at a fixed offset under the header');
        }
    }

    public function test_the_script_publishes_the_pinned_height_and_is_registered(): void
    {
        $script = file_get_contents(resource_path('js/sticky-header.js'));
        $this->assertStringContainsString("'--public-sticky-offset'", $script);
        $this->assertStringContainsString('ResizeObserver', $script);
        $this->assertStringContainsString('initStickyHeader();', file_get_contents(resource_path('js/app.js')));
    }
}
