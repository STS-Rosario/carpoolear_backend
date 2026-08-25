<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\DonationPayment;

class DonationPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DonationPayment::query()->with(['user', 'tier'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }
}
