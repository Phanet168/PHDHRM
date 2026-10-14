<?php

namespace App\Http\Controllers;

use App\Services\AboutContent;

class AboutController extends Controller
{
    public function index(AboutContent $content)
    {
        return view('about', $content->load());
    }
}
