<?php

namespace App\Models;

use App\Helper\ArabicSearch;
use App\Helper\SlugHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'name_en',
        'description',
        'description_en',
        'image',
        'price',
        'discount_price',
        'status',
        'category_id',
        'parent_id',
        'company_id',
        'quantity',
        'is_special',
        'product_availability_id',
        'weight',
        'slug'
    ];

    /// mohamed gamal
    protected $appends = ['image_url'];

    protected static function booted()
    {
        // Only generate a slug when none was given: an explicitly set
        // slug (the seeder's English slug, the admin form's slug) must
        // survive — overwriting it here made ProductSeeder::updateOrCreate
        // (which matches by slug) never find its rows, so every re-seed
        // duplicated all demo products. Uniqueness is still enforced.
        static::creating(function ($product) {
            $product->slug = SlugHelper::unique(
                'products',
                $product->slug ?: str_replace(' ', '-', $product->name)
            );
        });
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items', 'product_id', 'order_id', 'id')
            ->using(OrderItem::class)
            ->as('order_items')
            ->withPivot([
                'product_name', 'price', 'quantity',
            ]);
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'product_id', 'id');
    }

    public function cartItemsWithoutScope()
    {
        return $this->hasMany(Cart::class, 'product_id', 'id')->withoutGlobalScope('cookie_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_id', 'id');
    }

    public function choices()
    {
        return $this->belongsToMany(Choice::class, 'choices_products', 'product_id', 'choice_id');
    }

    public function discountCodes()
    {
        return $this->belongsToMany(DiscountCode::class, 'discount_code_product');
    }

//    public function getProductDiscountAttribute()
//    {
//        $result = $this->discount_price ? round((($this->price - $this->discount_price) / $this->discount_price) * 100) : null;
//        return $result;
//    }

    // old

    public function getCurrentNameLangAttribute()
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' || empty($this->name_en)) {
            return $this->name;
        }
        return $this->name_en;
    }

    /**
     * English description when the current locale is English and an
     * English description was filled in, otherwise the Arabic one.
     */
    public function localizedDescription(): ?string
    {
        return app()->getLocale() === 'en' && !empty($this->description_en)
            ? $this->description_en
            : $this->description;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MainCategory::class, 'category_id', 'id');
    }

    // uses in product filters page in dashboard

    public function sub_category(): BelongsTo
    {
        return $this->belongsTo(MainCategory::class, 'parent_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function colors()
    {
        return $this->belongsToMany(Color::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id');
    }

    public function features()
    {
        return $this->hasMany(ProductFeature::class, 'product_id', 'id');
    }

    public function availability()
    {
        return $this->belongsTo(ProductAvailability::class, 'product_availability_id', 'id')
            ->withDefault([
                'name' => '-'
            ]);
    }

    // public function getImageUrlAttribute()
    // {
    //     if (!$this->image) {
    //         return asset('assets/images/no-image.jpg');
    //     }

    //     if (Str::startsWith($this->image, ['http://', 'https://'])) {
    //         return $this->image;
    //     }

    //     return asset('storage/' . $this->image);
    // }

    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return url('assets/images/no-image.jpg');
        }

        if (Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }

        return url('storage/' . $this->image);
    }


    public function scopeFilter(Builder $builder, $filters)
    {
        $builder->when($filters['name'] ?? false, function ($builder, $value) {

            $builder->where('products.name', 'LIKE', "%{$value}%");
        });

        $builder->when($filters['status'] ?? false, function ($builder, $value) {

            $builder->where('products.status', 'LIKE', $value);
        });
        $builder->when($filters['category'] ?? false, function ($builder, $value) {

            $builder->where('products.category_id', 'LIKE', $value);
        });
    }

    /**
     * Free-text search over the given columns, tolerant of Arabic letter
     * variants: a query typed with ا matches stored أ/إ/آ, ة matches ه,
     * ى matches ي, and vice versa (see ArabicSearch). Uses REGEXP
     * because normalizing only the query could never match the stored
     * text with LIKE. English terms keep working as before — REGEXP is
     * case-insensitive under the _ci collation, just like LIKE was.
     */
    public function scopeSearchText(Builder $query, ?string $term, array $columns = ['name', 'name_en']): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $pattern = ArabicSearch::toRegExpPattern($term);

        if ($pattern === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($pattern, $columns) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $q->{$method}("products.{$column} REGEXP ?", [$pattern]);
            }
        });
    }

    public function wishlistUsers()
    {
        return $this->belongsToMany(
            User::class,
            'wishlist_products_user',
            'product_id',
            'user_id',
        );
    }

    public function scopeWithIsInWishlist($query, $user = null)
    {
        if ($user) {
            $query->withExists(['wishlistUsers as is_in_wishlist' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);
        } else {
            $query->selectRaw('products.*, false as is_in_wishlist');
        }
    }
}
