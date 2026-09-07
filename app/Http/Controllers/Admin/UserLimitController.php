<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLimitController extends Controller
{
    private function authorizeAdmin(): void
    {
        if (!auth()->user()->is_admin) {
            abort(403);
        }
    }

    public function index(): JsonResponse
    {
        $this->authorizeAdmin();

        $users = User::orderBy('name')
            ->get([
                'id', 'name', 'email', 'is_admin',
                'monthly_token_limit', 'monthly_cost_limit_usd',
                'tokens_used_this_month', 'cost_used_this_month',
                'usage_reset_at',
            ]);

        return response()->json($users);
    }

    public function updateLimits(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'monthly_token_limit'    => ['nullable', 'integer', 'min:0'],
            'monthly_cost_limit_usd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user->update($validated);

        return response()->json($user->fresh([
            'id', 'name', 'email', 'is_admin',
            'monthly_token_limit', 'monthly_cost_limit_usd',
            'tokens_used_this_month', 'cost_used_this_month',
            'usage_reset_at',
        ]));
    }

    public function resetUsage(User $user): JsonResponse
    {
        $this->authorizeAdmin();

        $user->tokens_used_this_month = 0;
        $user->cost_used_this_month   = 0;
        $user->usage_reset_at         = now();
        $user->save();

        return response()->json($user->fresh());
    }
}
