<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AirAlerts;
use App\Support\FamilyContext;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    public function index(AirAlerts $alerts, FamilyContext $context): JsonResponse
    {
        $regions = $context->family()->alert_regions ?: AirAlerts::DEFAULT_REGIONS;

        return response()->json([
            'configured' => $alerts->configured(),
            'regions' => $alerts->configured() ? $alerts->forRegions($regions) : [],
            'available' => AirAlerts::REGIONS,
            'watched' => $regions,
        ]);
    }
}
