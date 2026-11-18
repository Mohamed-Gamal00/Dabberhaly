<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    public function createGuest(Request $request)
    {
        // cookie_id is NOT NULL (unique) — minting a guest without one
        // 500s on insert, so generate it here.
        $guest = Guest::create(['cookie_id' => (string) Str::uuid()]);
        return ApiResponse::sendResponse(200, 'Guest Created Successfully', ['id' => $guest->id]);
    }
}
