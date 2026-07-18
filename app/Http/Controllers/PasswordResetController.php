<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RespondsWithJsonForms;
use App\Support\SiteConfig;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    use RespondsWithJsonForms;

    public function sendLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:' . SiteConfig::int('auth_email_max_length')],
        ]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        // Always show a generic success message (no user enumeration), except throttle.
        $message = $status === Password::RESET_THROTTLED
            ? 'Слишком много попыток. Подождите немного и попробуйте снова.'
            : SiteConfig::str('auth_msg_reset_link_sent');

        if ($this->wantsFormJson($request)) {
            if ($status === Password::RESET_THROTTLED) {
                return $this->jsonError($request, $message, ['email' => [$message]]);
            }

            return $this->jsonOk($request, $message, ['panel' => 'forgot']);
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withErrors(['email' => $message])
                ->with('auth_panel', 'forgot')
                ->withInput($request->only('email'));
        }

        return back()
            ->with('auth_notice', $message)
            ->with('auth_panel', 'forgot');
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:' . SiteConfig::int('auth_email_max_length')],
            'password' => ['required', 'confirmed', SiteConfig::passwordRule()],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $message = SiteConfig::str('auth_msg_password_updated');
            if ($this->wantsFormJson($request)) {
                return $this->jsonOk($request, $message, ['panel' => 'login']);
            }

            return redirect('/?auth=login')->with('auth_notice', $message);
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => SiteConfig::str('auth_msg_reset_invalid_token'),
            Password::INVALID_USER => SiteConfig::str('auth_msg_reset_user_not_found'),
            default => SiteConfig::str('auth_msg_reset_failed'),
        };

        if ($this->wantsFormJson($request)) {
            return $this->jsonError($request, $message, ['email' => [$message]]);
        }

        return back()
            ->withErrors(['email' => $message])
            ->with('auth_panel', 'reset')
            ->withInput($request->only('email', 'token'));
    }
}
