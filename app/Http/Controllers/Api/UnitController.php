<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Unit::where('status', 'active')->orderBy('name')->get(['id', 'name', 'status'])]);
    }
}
