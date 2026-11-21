<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Http\Resources\HeaderBannerResource;
use App\Models\Design;
use App\Models\HeaderBanner;
use App\Models\MainCategory;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $user = Auth::guard('web')->user();

        // Each home section has its own list now (they used to all render
        // the same latest-10 list): latest products, discounted offers,
        // the gifts category, and the household-essentials category.
        $products = $this->mapProducts(
            $this->baseProductsQuery($user)->latest()->take(10)->get()
        );

        $offerProducts = $this->mapProducts(
            $this->baseProductsQuery($user)
                ->whereNotNull('discount_price')
                ->where('discount_price', '>', 0)
                ->whereColumn('discount_price', '<', 'price')
                ->latest()
                ->take(10)
                ->get()
        );

        $giftProducts = $this->mapProducts(
            $this->categoryProducts($user, 'gifts')
        );

        $householdProducts = $this->mapProducts(
            $this->categoryProducts($user, 'household-essentials')
        );

        $banners = HeaderBannerResource::collection(HeaderBanner::all())->resolve();

        $homeDesigns = Design::where('page_name', 'home')
            ->whereIn('title', ['home_band_left', 'home_band_right', 'home_tv_banner'])
            ->get()
            ->keyBy('title')
            ->map(fn($d) => $d->image_url);

        // Fetch top offers designs
        $topOffers = Design::where('page_name', 'home')
            ->whereIn('title', ['top_offer_1', 'top_offer_2', 'top_offer_3', 'top_offer_4', 'top_offer_5'])
            ->orderBy('title')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'image_url' => $d->image_url,
                'description' => $d->description,
            ]);

        // Fetch new products banner
        $newProductsBanner = Design::where('page_name', 'home')
            ->where('title', 'new_products_banner')
            ->first();

        return Inertia::render('Home', [
            'products'           => $products,
            'offerProducts'      => $offerProducts,
            'giftProducts'       => $giftProducts,
            'householdProducts'  => $householdProducts,
            'banners'            => $banners,
            'homeDesigns'        => $homeDesigns,
            'topOffers'          => $topOffers,
            'newProductsBanner'  => $newProductsBanner ? $newProductsBanner->image_url : null,
        ]);
    }

    /** Latest 10 products of one category (by slug); empty when the category does not exist. */
    private function categoryProducts($user, string $slug): Collection
    {
        $category = MainCategory::where('slug', $slug)->first();

        if (! $category) {
            return collect();
        }

        return $this->baseProductsQuery($user)
            ->where('category_id', $category->id)
            ->latest()
            ->take(10)
            ->get();
    }

    private function baseProductsQuery($user)
    {
        return Product::with(['images', 'parent'])->withIsInWishlist($user);
    }

    /** The product shape every home section renders (identical for all four lists). */
    private function mapProducts(Collection $products): Collection
    {
        return $products->map(fn($p) => [
            'id'             => $p->id,
            'name'           => $p->name,
            'name_en'        => $p->name_en,
            'slug'           => $p->slug,
            'price'          => $p->price,
            'discount_price' => $p->discount_price,
            'image_url'      => $p->image_url,
            'is_in_wishlist' => (bool) $p->is_in_wishlist,
            'quantity'       => (int) $p->quantity,
            'images'         => $p->images->map(fn($img) => ['image_url' => $img->image_url])->values(),
            'parent'         => $p->parent
                ? ['id' => $p->parent->id, 'name' => $p->parent->name, 'name_en' => $p->parent->name_en]
                : null,
        ]);
    }
}
