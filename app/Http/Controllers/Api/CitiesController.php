<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\Request;

class CitiesController extends Controller
{
  public function allCities()
  {
    // Egypt only: resolve the country by code instead of a hardcoded id
    // (the old hardcoded 178 was Saudi Arabia).
    $egyptId = Country::where('code', 'EG')->value('id');

    $cities = $egyptId ? City::where('country_id', $egyptId)->get() : collect();

    if (count($cities) > 0) {
      return ApiResponse::sendResponse(200, 'cities Retrieved Successfully', CityResource::collection($cities));
    }
    return ApiResponse::sendResponse(200, 'No cities To Retrieved', []);
  }
}
