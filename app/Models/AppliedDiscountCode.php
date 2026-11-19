<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A discount code that has been applied to a cart.
 *
 * One row means: the cart identified by `cookie_id` currently has
 * the discount code `discount_id` applied to it. The cart is keyed
 * by its cookie (not by user) because guests have no user id, and
 * the web cart is cookie-based even for logged-in customers.
 *
 * Table: `applied_discount_codes` (Laravel convention — no $table
 * override needed). Compare with `DiscountCode`, which is the code
 * definition itself (code string, type, amount, usage limits).
 */
class AppliedDiscountCode extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['cookie_id', 'discount_id'];
}
