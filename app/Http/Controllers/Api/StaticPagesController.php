<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Models\CommonQuestion;
use App\Models\Page;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;

class StaticPagesController extends Controller
{
    /**
     * Serve the admin-written English title/content when the locale is
     * English and they are filled in; otherwise fall back to the Arabic
     * values with the previous machine-translation behaviour.
     */
    private function localizePage(?Page $page): ?Page
    {
        if (!$page) {
            return null;
        }

        $isEnglish = app()->getLocale() === 'en';

        $page->title = $isEnglish && !empty($page->title_en)
            ? $page->title_en
            : translateWithHTMLTags($page->title);

        $page->content = $isEnglish && !empty($page->content_en)
            ? HtmlSanitizer::purify($page->content_en)
            : HtmlSanitizer::purify(translateWithHTMLTags($page->content));

        return $page;
    }

    public function static_pages()
    {
        $page = $this->localizePage(
            Page::select('title', 'content', 'title_en', 'content_en')->where('id', '3')->first()
        );

        $shipping_policy = $this->localizePage(
            Page::select('title', 'content', 'title_en', 'content_en')->where('id', '2')->first()
        );

        $Terms_and_Conditions = $this->localizePage(
            Page::select('title', 'content', 'title_en', 'content_en')->where('id', '1')->first()
        );

        $data = [$page, $shipping_policy, $Terms_and_Conditions];
        if ($page) {
            return ApiResponse::sendResponse(200, 'data Retrieved Successfully', $data);
        } else {
            return ApiResponse::sendResponse(200, 'data not found');
        }
    }

    public function common_questions()
    {
        $questions = CommonQuestion::select('title', 'description', 'title_en', 'description_en')->get();

        if ($questions->isEmpty()) {
            return ApiResponse::sendResponse(200, 'Data not found');
        }

        // Serve the admin-written English title/description when the
        // locale is English and they are filled in; otherwise fall back
        // to the Arabic values with the previous machine-translation
        // behaviour (same as static_pages above).
        $isEnglish = app()->getLocale() === 'en';

        $questions = $questions->map(function ($question) use ($isEnglish) {
            $question->title = $isEnglish && !empty($question->title_en)
                ? $question->title_en
                : translateWithHTMLTags($question->title);
            $question->description = $isEnglish && !empty($question->description_en)
                ? $question->description_en
                : translateWithHTMLTags($question->description);
            return $question;
        });

        return ApiResponse::sendResponse(200, 'Data Retrieved Successfully', $questions);
    }

}
