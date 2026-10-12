<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\LoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the e-mail form (HU-001).
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Send a one-time code to the given e-mail (HU-002).
     */
    public function store(Request $request, LoginCodeService $loginCodes): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        $loginCodes->send($validated['email'], $request->ip());

        $request->session()->put('login.email', $loginCodes->normalize($validated['email']));

        return redirect()->route('login.verify')
            ->with('status', 'Te enviamos un código de acceso a tu correo.');
    }

    /**
     * Resend the code to the e-mail stored in the session (HU-005).
     */
    public function resend(Request $request, LoginCodeService $loginCodes): RedirectResponse
    {
        $email = $request->session()->get('login.email');

        if (! $email) {
            return redirect()->route('login');
        }

        $loginCodes->send($email, $request->ip());

        return back()->with('status', 'Te enviamos un nuevo código.');
    }

    /**
     * Log the user out (HU-005).
     */
    public function destroy(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $auditLogger->log('auth.logout');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
