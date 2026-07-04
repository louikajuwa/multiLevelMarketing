<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function edit(): mixed
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $request->expectsJson() || $request->ajax()
            ? $this->ok($user->only('name', 'email', 'phone'), 'Profile updated.')
            : back()->with('status', 'Profile updated.');
    }
}
