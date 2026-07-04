<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function showForgot(): mixed
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse|RedirectResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            $message = __($status);

            return $request->expectsJson() || $request->ajax()
                ? $this->ok(null, $message)
                : back()->with('status', $message);
        }

        throw ValidationException::withMessages(['email' => __($status)]);
    }

    public function showReset(string $token): mixed
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse|RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $request->expectsJson() || $request->ajax()
                ? $this->ok(['redirect' => route('login')], __($status))
                : redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages(['email' => __($status)]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse|RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->string('password')),
        ]);

        return $request->expectsJson() || $request->ajax()
            ? $this->ok(null, 'Password updated.')
            : back()->with('status', 'Password updated.');
    }
}
