<?php

namespace App\Http\Controllers;

use App\Services\RealtimeService;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function token(Request $request, RealtimeService $realtime): mixed
    {
        if (! $realtime->isConfigured()) {
            return response()->json(['message' => 'Realtime not configured'], 503);
        }

        return $this->ok($realtime->tokenFor($request->user()));
    }
}
