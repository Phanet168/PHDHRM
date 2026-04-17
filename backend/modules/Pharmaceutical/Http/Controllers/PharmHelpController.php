<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use Illuminate\Routing\Controller;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class PharmHelpController extends Controller
{
    private const ARTICLES = [
        'overview' => [
            'title' => 'ទិដ្ឋភាពទូទៅ',
            'file' => '01-overview.md',
            'icon' => 'fa-home',
        ],
        'dashboard' => [
            'title' => 'Dashboard',
            'file' => '02-dashboard.md',
            'icon' => 'fa-tachometer-alt',
        ],
        'medicines' => [
            'title' => 'Medicines',
            'file' => '03-medicines.md',
            'icon' => 'fa-pills',
        ],
        'categories' => [
            'title' => 'Categories',
            'file' => '04-categories.md',
            'icon' => 'fa-tags',
        ],
        'distributions' => [
            'title' => 'Distributions',
            'file' => '05-distributions.md',
            'icon' => 'fa-truck',
        ],
        'stock' => [
            'title' => 'Stock',
            'file' => '06-stock.md',
            'icon' => 'fa-boxes',
        ],
        'adjustments' => [
            'title' => 'Adjustments',
            'file' => '07-adjustments.md',
            'icon' => 'fa-exchange-alt',
        ],
        'dispensing' => [
            'title' => 'Dispensing',
            'file' => '08-dispensing.md',
            'icon' => 'fa-hand-holding-medical',
        ],
        'reports' => [
            'title' => 'Reports',
            'file' => '09-reports.md',
            'icon' => 'fa-chart-bar',
        ],
        'summary-reports' => [
            'title' => 'Summary Reports',
            'file' => '10-summary-reports.md',
            'icon' => 'fa-warehouse',
        ],
        'users' => [
            'title' => 'Users',
            'file' => '11-users.md',
            'icon' => 'fa-users-cog',
        ],
        'troubleshooting-faq' => [
            'title' => 'Troubleshooting និង FAQ',
            'file' => '12-troubleshooting-faq.md',
            'icon' => 'fa-life-ring',
        ],
    ];

    public function index(?string $article = null)
    {
        $slug = $article ?: 'overview';
        abort_unless(array_key_exists($slug, self::ARTICLES), 404);

        $articleMeta = self::ARTICLES[$slug];
        $articlePath = base_path('docs/pharmacy-help-km/' . $articleMeta['file']);
        abort_unless(is_file($articlePath), 404);

        $markdown = file_get_contents($articlePath) ?: '';
        $articleHtml = $this->renderMarkdown($markdown);

        return view('pharmaceutical::help.index', [
            'articles' => self::ARTICLES,
            'activeArticle' => $slug,
            'articleMeta' => $articleMeta,
            'articleHtml' => $articleHtml,
        ]);
    }

    private function renderMarkdown(string $markdown): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $converter = new MarkdownConverter($environment);

        return (string) $converter->convert($markdown);
    }
}