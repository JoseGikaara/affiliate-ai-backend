<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CreditTransaction::where('user_id', $request->user()->id)
            ->latest();

        if ($request->user()->is_admin) {
            $query = CreditTransaction::with('user:id,name,email')
                ->latest();
        }

        $transactions = $query->paginate(50);

        return response()->json($transactions);
    }
}

