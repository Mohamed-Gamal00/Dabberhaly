<?php

namespace App\Http\Controllers;

use App\Models\MainCategory;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * GET /sitemap.xml — product, category and static-page URLs.
     * Cached for a day; the sitemap is listed in public/robots.txt.
     */
    public function __invoke()
    {
        $xml = Cache::remember('sitemap_xml', 86400, function () {
            $urls = collect();

            $add = function (string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5') use (&$urls) {
                $urls->push(compact('loc', 'lastmod', 'changefreq', 'priority'));
            };

            $add(url('/'), null, 'daily', '1.0');
            $add(url('/products'), null, 'daily', '0.9');
            $add(url('/categories'), null, 'weekly', '0.8');
            $add(url('/brands'), null, 'weekly', '0.7');
            $add(url('/offers'), null, 'daily', '0.8');
            $add(url('/faq'), null, 'monthly', '0.5');
            $add(url('/contact-us'), null, 'monthly', '0.5');

            foreach (['shipping-policy', 'terms-conditions', 'privacy-policy', 'exchanges-returns'] as $page) {
                $add(url('/' . $page), null, 'monthly', '0.4');
            }

            MainCategory::select('slug', 'updated_at')->get()->each(
                fn ($c) => $add(url('/categories/' . $c->slug), $c->updated_at?->toDateString(), 'weekly', '0.7')
            );

            Product::where('status', 'active')->select('slug', 'updated_at')->get()->each(
                fn ($p) => $add(url('/products/' . $p->slug), $p->updated_at?->toDateString(), 'weekly', '0.8')
            );

            $body = $urls->map(function ($u) {
                $lastmod = $u['lastmod'] ? "<lastmod>{$u['lastmod']}</lastmod>" : '';
                return "<url><loc>{$u['loc']}</loc>{$lastmod}<changefreq>{$u['changefreq']}</changefreq><priority>{$u['priority']}</priority></url>";
            })->implode("\n");

            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
                . $body . "\n</urlset>";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
