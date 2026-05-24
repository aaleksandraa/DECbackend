<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\BookingSafetyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingSafetyController extends Controller
{
    public function __construct(
        private BookingSafetyService $bookingSafetyService,
    ) {}

    public function report(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->bookingSafetyService->audit(false),
        ]);
    }

    public function backfill(Request $request): JsonResponse
    {
        $request->validate([
            'confirm' => 'required|accepted',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->bookingSafetyService->audit(true),
        ]);
    }
}
