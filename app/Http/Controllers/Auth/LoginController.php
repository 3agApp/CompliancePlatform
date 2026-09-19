<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /**
     * Send guests to 3AG Accounts. Local email/password login is disabled.
     *
     * An invitation code rides along in the session because the round trip
     * through Accounts discards the query string it arrived in. Both member
     * invitations and supplier connections use the same parameter.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $invitation = $request->query('invitation');

        if (is_string($invitation) && $invitation !== '') {
            $request->session()->put('organization_invitation', $invitation);
        }

        return redirect()->route('auth.accounts.redirect');
    }
}
