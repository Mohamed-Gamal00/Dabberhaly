<?php

namespace App\Exceptions;

use Exception;

/**
 * A deliberate, user-facing checkout error: a bad shipping/address
 * choice, a missing shipping setup, an unavailable product. Its
 * message is written to be shown to the customer.
 *
 * Checkout pre-check catches pass this message through, while any
 * other (unexpected) exception is answered with a generic message —
 * so internal details never leak to the client.
 */
class CheckoutException extends Exception
{
}
