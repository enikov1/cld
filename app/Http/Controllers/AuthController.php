<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserLibraryService;
use App\Support\RespondsWithJsonForms;
use App\Support\SiteConfig;
use App\Support\WatchlistDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use RespondsWithJsonForms;

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:' . SiteConfig::int('auth_email_max_length')],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool)($credentials['remember'] ?? false);
        unset($credentials['remember']);

        if (!Auth::attempt($credentials, $remember)) {
            $message = SiteConfig::str('auth_msg_login_failed');
            if ($this->wantsFormJson($request)) {
                return $this->jsonError($request, $message, ['email' => [$message]]);
            }

            return back()
                ->withErrors(['email' => $message])
                ->withInput($request->only('email'))
                ->with('auth_panel', 'login');
        }

        $guestKey = UserLibraryService::guestKey($request);

        /** @var User|null $user */
        $user = Auth::user();
        if ($user?->isBlocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = SiteConfig::str('auth_msg_account_blocked');
            if ($this->wantsFormJson($request)) {
                return $this->jsonError($request, $message, ['email' => [$message]], 403);
            }

            return back()
                ->withErrors(['email' => $message])
                ->withInput($request->only('email'))
                ->with('auth_panel', 'login');
        }

        UserLibraryService::mergeGuestToUser($user, $guestKey);

        $request->session()->regenerate();

        if ($this->wantsFormJson($request)) {
            return $this->jsonOk(
                $request,
                SiteConfig::str('auth_msg_login_success'),
                $this->authSessionPayload($user)
            );
        }

        return redirect()->intended(url('/'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:' . SiteConfig::int('auth_name_max_length')],
            'email' => ['required', 'email', 'max:' . SiteConfig::int('auth_email_max_length'), 'unique:users,email'],
            'password' => ['required', 'confirmed', SiteConfig::passwordRule()],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['role' => 'user'])->save();

        $guestKey = UserLibraryService::guestKey($request);

        Auth::login($user);
        WatchlistDefaults::ensureForUser($user);
        UserLibraryService::mergeGuestToUser($user, $guestKey);
        $request->session()->regenerate();

        if ($this->wantsFormJson($request)) {
            return $this->jsonOk(
                $request,
                SiteConfig::str('auth_msg_register_success'),
                $this->authSessionPayload($user)
            );
        }

        return redirect()->intended(url('/'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($this->wantsFormJson($request)) {
            return $this->jsonRedirect($request, url('/'), SiteConfig::str('auth_msg_logout_success'));
        }

        return redirect('/');
    }

    /**
     * @return array<string, mixed>
     */
    private function authSessionPayload(User $user): array
    {
        $initial = mb_substr(trim($user->name), 0, 1);

        return [
            'logged_in' => true,
            'close_auth' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'initial' => $initial !== '' ? mb_strtoupper($initial) : '',
            ],
        ];
    }
}
