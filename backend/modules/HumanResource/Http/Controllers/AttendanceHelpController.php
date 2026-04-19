<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Routing\Controller;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class AttendanceHelpController extends Controller
{
    private const ARTICLES = [
        'overview' => [
            'title_km' => 'មើលទូទៅ',
            'title_en' => 'Overview',
            'file' => 'README.md',
            'icon' => 'fa-home',
        ],
        'employee-flow' => [
            'title_km' => 'លំហូរ Mobile បុគ្គលិក',
            'title_en' => 'Mobile Employee Flow',
            'file' => '01-mobile-employee-flow.md',
            'icon' => 'fa-mobile-alt',
        ],
        'admin-flow' => [
            'title_km' => 'លំហូរ Web សម្រាប់ HR/Admin',
            'title_en' => 'Web Admin Flow',
            'file' => '02-web-admin-flow.md',
            'icon' => 'fa-desktop',
        ],
        'policy' => [
            'title_km' => 'គោលនយោបាយ និងការកំណត់',
            'title_en' => 'Policy and Configuration',
            'file' => '03-policy-and-configuration.md',
            'icon' => 'fa-sliders-h',
        ],
        'exceptions' => [
            'title_km' => 'Exceptions និងការកែសម្រួល',
            'title_en' => 'Exceptions and Corrections',
            'file' => '04-exceptions-and-corrections.md',
            'icon' => 'fa-exclamation-triangle',
        ],
        'faq' => [
            'title_km' => 'សំណួរញឹកញាប់ (FAQ)',
            'title_en' => 'Troubleshooting FAQ',
            'file' => '05-troubleshooting-faq.md',
            'icon' => 'fa-life-ring',
        ],
    ];

    public function index(?string $article = null)
    {
        $slug = $article ?: 'overview';
        abort_unless(array_key_exists($slug, self::ARTICLES), 404);

        $articleMeta = self::ARTICLES[$slug];
        $articlePath = base_path('docs/hr-attendance-help-km/' . $articleMeta['file']);
        abort_unless(is_file($articlePath), 404);

        $markdown = file_get_contents($articlePath) ?: '';
        $articleHtml = $this->renderMarkdown($markdown);

        $locale = app()->getLocale();
        $isEnglish = $locale === 'en';

        return view('humanresource::help.attendance.index', [
            'articles' => self::ARTICLES,
            'activeArticle' => $slug,
            'articleMeta' => $articleMeta,
            'articleTitle' => $isEnglish ? $articleMeta['title_en'] : $articleMeta['title_km'],
            'articleHtml' => $articleHtml,
            'isEnglish' => $isEnglish,
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

