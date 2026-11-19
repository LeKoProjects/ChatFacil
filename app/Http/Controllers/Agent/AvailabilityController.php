<?php

namespace App\Http\Controllers\Agent;

use App\Enums\AgentAvailability;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'availability' => ['required', Rule::enum(AgentAvailability::class)],
        ]);

        $request->user()->forceFill([
            'availability' => $data['availability'],
            'last_seen_at' => now(),
        ])->save();

        return response()->json(['availability' => $data['availability']]);
    }
}
