<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Page $page): View
    {
        abort_unless($page->is_published || auth()->user()?->isAdmin(), 404);

        return view('pages.show', ['page' => $page]);
    }
}
