<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\LoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VerifyCodeController extends Controller
{
    /**
     * Show the code form.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('login.email');

        if (! $email) {
            return redirect()->route('login');
        }

        return view('auth.verify', ['email' => $email]);
    }

    /**
     * Validate the code and start a secure session (HU-003).
     */
    public function store(Request $request, LoginCodeService $loginCodes, AuditLogger $auditLogger): RedirectResponse
    {
        $email = $request->session()->get('login.email');

        if (! $email) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:'.LoginCodeService::CODE_LENGTH],
        ]);

        $user = $loginCodes->verify($email, $validated['code']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->forget('login.email');

        $auditLogger->log('auth.login', user: $user);

        if ($user->can('access-admin')) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->intended(route('book.start'));
    }
}
