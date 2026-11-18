<?php

namespace App\Helper;

use Illuminate\Support\Facades\DB;

class SlugHelper
{
    /**
     * Return a slug that does not collide with an existing row in
     * $table, appending -2, -3, ... as needed. Pass $ignoreId when
     * updating an existing row so its own slug is not a collision.
     */
    public static function unique(string $table, string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $suffix = 2;

        while (
            DB::table($table)
                ->where('slug', $slug)
                ->when(! is_null($ignoreId), fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
