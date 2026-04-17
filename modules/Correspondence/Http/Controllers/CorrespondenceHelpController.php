<?php

namespace Modules\Correspondence\Http\Controllers;

use Illuminate\Routing\Controller;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class CorrespondenceHelpController extends Controller
{
    private const ARTICLES = [
        'overview' => [
            'title' => 'ទិដ្ឋភាពទូទៅ',
            'file' => '01-overview.md',
            'icon' => 'fa-home',
        ],
        'incoming' => [
            'title' => 'លិខិតចូល',
            'file' => '02-incoming.md',
            'icon' => 'fa-inbox',
        ],
        'outgoing' => [
            'title' => 'លិខិតចេញ',
            'file' => '03-outgoing.md',
            'icon' => 'fa-paper-plane',
        ],
        'workflow' => [
            'title' => 'ដំណើរការ Workflow',
            'file' => '04-workflow.md',
            'icon' => 'fa-project-diagram',
        ],
        'feedback-notes' => [
            'title' => 'ចំណារ និងមតិយោបល់',
            'file' => '05-feedback-notes.md',
            'icon' => 'fa-comments',
        ],
        'troubleshooting-faq' => [
            'title' => 'Troubleshooting និង FAQ',
            'file' => '06-troubleshooting-faq.md',
            'icon' => 'fa-life-ring',
        ],
        'role-permission-setup' => [
            'title' => 'កំណត់តួនាទី និងសិទ្ធិ',
            'file' => '07-role-permission-setup.md',
            'icon' => 'fa-user-shield',
        ],
    ];

    public function index(?string $article = null)
    {
        $slug = $article ?: 'overview';
        abort_unless(array_key_exists($slug, self::ARTICLES), 404);

        $articleMeta = self::ARTICLES[$slug];
        $articlePath = base_path('docs/correspondence-help-km/' . $articleMeta['file']);
        abort_unless(is_file($articlePath), 404);

        $markdown = file_get_contents($articlePath) ?: '';
        $articleHtml = $this->renderMarkdown($markdown);

        return view('correspondence::help.index', [
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
