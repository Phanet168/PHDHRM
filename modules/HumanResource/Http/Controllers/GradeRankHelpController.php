<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class GradeRankHelpController extends Controller
{
    private const SLUG_MAP = [
        'overview' => 'overview',
        'workflow' => 'grade-rank',
        'tabs' => 'grade-rank',
        'requests' => 'grade-rank',
        'rules' => 'grade-rank',
        'reports' => 'reports',
        'faq' => 'faq',
    ];

    public function index(?string $article = null): RedirectResponse
    {
        $targetArticle = self::SLUG_MAP[$article ?: 'overview'] ?? 'grade-rank';

        return redirect()->route('employees.help', ['article' => $targetArticle]);
    }
}

