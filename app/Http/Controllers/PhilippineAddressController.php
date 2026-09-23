<?php

namespace App\Http\Controllers;

use App\Services\PhilippineAddressService;
use Illuminate\Http\JsonResponse;

class PhilippineAddressController extends Controller
{
    public function localities(string $areaCode, PhilippineAddressService $addresses): JsonResponse
    {
        abort_unless(hash_equals(PhilippineAddressService::SERVICE_AREA_CODE, $areaCode), 404);

        $localities = $addresses->localitiesFor($areaCode);
        abort_if($localities === null, 404, 'Address area not found.');

        return response()->json(['data' => $localities]);
    }

    public function barangays(string $localityCode, PhilippineAddressService $addresses): JsonResponse
    {
        abort_if($addresses->locality(PhilippineAddressService::SERVICE_AREA_CODE, $localityCode) === null, 404);

        $barangays = $addresses->barangaysFor($localityCode);
        abort_if($barangays === null, 404, 'City or municipality not found.');

        return response()->json(['data' => $barangays]);
    }
}
