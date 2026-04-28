<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Routing\Controller;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class EmployeeHelpController extends Controller
{
    private const EMPLOYEE_ARTICLES = [
        'overview' => [
            'title_km' => 'ទិដ្ឋភាពទូទៅ',
            'title_en' => 'Overview',
            'file' => '01-overview.md',
            'icon' => 'fa-home',
        ],
        'employee-list' => [
            'title_km' => 'បញ្ជីបុគ្គលិក',
            'title_en' => 'Employee List',
            'file' => '02-employee-list.md',
            'icon' => 'fa-list',
        ],
        'profile' => [
            'title_km' => 'បង្កើត/កែព័ត៌មានបុគ្គលិក',
            'title_en' => 'Create/Edit Profile',
            'file' => '03-profile-create-edit.md',
            'icon' => 'fa-user-edit',
        ],
        'career' => [
            'title_km' => 'គ្រប់គ្រងអាជីពការងារ',
            'title_en' => 'Career Management',
            'file' => '04-career-management.md',
            'icon' => 'fa-briefcase',
        ],
        'position-promotion' => [
            'title_km' => 'ឡើងតួនាទី',
            'title_en' => 'Position Promotion',
            'file' => '05-position-promotion.md',
            'icon' => 'fa-level-up-alt',
        ],
        'grade-rank' => [
            'title_km' => 'ថ្នាក់ និងឋានន្តរស័ក្តិ',
            'title_en' => 'Grade and Rank',
            'file' => '06-grade-rank.md',
            'icon' => 'fa-layer-group',
        ],
        'workplace-transfer' => [
            'title_km' => 'ផ្លាស់ប្តូរកន្លែងការងារ',
            'title_en' => 'Workplace Transfer',
            'file' => '07-workplace-transfer.md',
            'icon' => 'fa-random',
        ],
        'retirement' => [
            'title_km' => 'គ្រប់គ្រងចូលនិវត្តន៍',
            'title_en' => 'Retirement Management',
            'file' => '08-retirement-and-inactive.md',
            'icon' => 'fa-user-clock',
        ],
        'reports' => [
            'title_km' => 'របាយការណ៍ និងបោះពុម្ព',
            'title_en' => 'Reports and Printing',
            'file' => '09-reports-printing.md',
            'icon' => 'fa-file-alt',
        ],
        'faq' => [
            'title_km' => 'សំណួរញឹកញាប់ (FAQ)',
            'title_en' => 'Troubleshooting FAQ',
            'file' => '10-troubleshooting-faq.md',
            'icon' => 'fa-life-ring',
        ],
    ];

    private const ORG_STRUCTURE_ARTICLES = [
        'org-structure-overview' => [
            'title_key' => 'org_structure_governance_help',
            'title_km' => 'ជំនួយ៖ គ្រប់គ្រងរចនាសម្ព័ន្ធអង្គភាព',
            'title_en' => 'Org Structure Governance Help',
            'file' => '01-org-structure-governance.md',
            'icon' => 'fa-sitemap',
        ],
    ];

    public function index(?string $article = null)
    {
        $slug = $article ?: 'overview';
        abort_unless(array_key_exists($slug, self::EMPLOYEE_ARTICLES), 404);

        $articleMeta = self::EMPLOYEE_ARTICLES[$slug];
        $articlePath = base_path('docs/hr-employee-help-km/' . $articleMeta['file']);
        abort_unless(is_file($articlePath), 404);

        $markdown = file_get_contents($articlePath) ?: '';
        $articleHtml = $this->renderMarkdown($markdown);

        $locale = app()->getLocale();
        $isEnglish = $locale === 'en';

        return view('humanresource::help.employee.index', [
            'articles' => self::EMPLOYEE_ARTICLES,
            'activeArticle' => $slug,
            'articleMeta' => $articleMeta,
            'articleTitle' => $isEnglish ? $articleMeta['title_en'] : $articleMeta['title_km'],
            'articleHtml' => $articleHtml,
            'isEnglish' => $isEnglish,
        ]);
    }

    public function orgGovernanceHelp(?string $article = null)
    {
        $slug = trim((string) ($article ?: 'org-structure-overview'));
        if (!array_key_exists($slug, self::ORG_STRUCTURE_ARTICLES)) {
            $slug = 'org-structure-overview';
        }

        $articleMeta = self::ORG_STRUCTURE_ARTICLES[$slug];
        $articlePath = base_path('docs/hr-org-structure-help-km/' . $articleMeta['file']);
        abort_unless(is_file($articlePath), 404);

        $markdown = file_get_contents($articlePath) ?: '';
        $articleHtml = $this->renderMarkdown($markdown);

        $locale = app()->getLocale();
        $isEnglish = $locale === 'en';
        $articleTitleFallback = $isEnglish ? $articleMeta['title_en'] : $articleMeta['title_km'];
        $articleTitleKey = (string) ($articleMeta['title_key'] ?? '');

        return view('humanresource::help.org-structure.index', [
            'articles' => self::ORG_STRUCTURE_ARTICLES,
            'activeArticle' => $slug,
            'articleMeta' => $articleMeta,
            'articleTitle' => $articleTitleKey !== ''
                ? localize($articleTitleKey, $articleTitleFallback)
                : $articleTitleFallback,
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

