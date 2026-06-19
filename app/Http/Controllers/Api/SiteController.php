<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

/** Consulta de cruces (panel). */
class SiteController extends Controller
{
    public function index(): JsonResponse
    {
        $sites = Site::query()
            ->select('id', 'code', 'name', 'lat', 'lng', 'created_at')
            ->orderBy('code')
            ->get();

        return response()->json(['ok' => true, 'sites' => $sites]);
    }
}
