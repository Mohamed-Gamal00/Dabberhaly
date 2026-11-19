<?php

namespace App\Helper;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\Request;

trait Helper
{
    public function uploadedImage($request, $fileName, $dirName)
    {
        // if request hasn't it will make this method return null and if not it will return the path
        if (!$request->hasFile($fileName)) {
            return;
        }

        $file = $request->file($fileName); // return uploadedFile object
        $path = $file->store('uploads/' . $dirName, [
            'disk' => 'public'
        ]); // or i can put key and value ('disk' => 'public')
        return $path;
    }

    public function uploadedLogo($request, $fileName, $dirName)
    {
        // if request hasn't it will make this method return null and if not it will return the path
        if (!$request->hasFile($fileName)) {
            return;
        }

        $file = $request->file($fileName); // return uploadedFile object
        $path = $file->store('uploads/' . $dirName, [
            'disk' => 'public'
        ]); // or i can put key and value ('disk' => 'public')
        return $path;
    }


    /* favourit products */

    /**
     * Request-scoped cache of wishlist product ids, kept on the current
     * request (so it dies with the request and can never leak into
     * another request or test). Without this, every product in a list
     * re-loaded the whole wishlist (one full query per product).
     */
    public function checkIfProductExists(Request $request, $productId = null)
    {
        $userId = $request->user()?->id ?? $request->header('user-id');
        $guestId = $request->header('guest-id');

        if ($userId) {
            $ids = $this->wishlistProductIds($request, 'user:' . $userId, fn () =>
                User::find($userId)?->wishlistProducts()->pluck('wishlist_products_user.product_id')->all() ?? []);
            return in_array($productId, $ids);
        }

        if ($guestId) {
            $ids = $this->wishlistProductIds($request, 'guest:' . $guestId, fn () =>
                Guest::find($guestId)?->wishlistProducts()->pluck('wishlist_products_guest.product_id')->all() ?? []);
            return in_array($productId, $ids);
        }

        return false;
    }

    private function wishlistProductIds(Request $request, string $ownerKey, callable $load): array
    {
        $cache = $request->attributes->get('wishlist_ids_cache', []);

        if (!array_key_exists($ownerKey, $cache)) {
            $cache[$ownerKey] = $load();
            $request->attributes->set('wishlist_ids_cache', $cache);
        }

        return $cache[$ownerKey];
    }
}
