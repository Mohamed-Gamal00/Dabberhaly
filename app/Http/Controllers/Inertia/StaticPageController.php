<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\CommonQuestion;
use App\Models\Page;
use App\Services\HtmlSanitizer;
use Inertia\Inertia;

class StaticPageController extends Controller
{
    public function page($id, $view)
    {
        $page = Page::find($id);

        // Content is rendered with v-html: purify on read too, so rows
        // saved before purification was added cannot execute scripts.
        // English title/content are served when the locale is English
        // and filled in, otherwise the Arabic values are used.
        if ($page) {
            $page->title = $page->localizedTitle();
            $page->content = HtmlSanitizer::purify($page->localizedContent());
        }

        return Inertia::render('StaticPages/Page', ['page' => $page, 'view' => $view]);
    }

    public function shipping_policy()   { return $this->page(2, 'shipping_policy'); }
    public function terms_conditions()  { return $this->page(1, 'terms_conditions'); }
    public function privacy_policy()    { return $this->page(3, 'privacy_policy'); }
    public function exchanges_returns() { return $this->page(5, 'exchanges_returns'); }

    public function questions()
    {
        $questions = CommonQuestion::all();
        return Inertia::render('StaticPages/Questions', ['questions' => $questions]);
    }
}
