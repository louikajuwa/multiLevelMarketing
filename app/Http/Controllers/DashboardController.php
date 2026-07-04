<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index(): mixed
    {
        return view('dashboard', ['user' => auth()->user()]);
    }
}
