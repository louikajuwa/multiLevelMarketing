<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use ApiResponse;

    /** Per-page size for searchable-select option endpoints (default 10, hard cap 100). */
    protected function optionLimit(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 10), 1), 100);
    }
}
