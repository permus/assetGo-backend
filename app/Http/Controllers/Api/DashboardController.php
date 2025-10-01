<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service)
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $data = $this->service->getDashboardData($companyId, $request);
        return response()->json(['success' => true, 'data' => $data]);
    }
}


