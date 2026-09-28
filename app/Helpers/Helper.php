<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (! function_exists('user')) {
    /**
     * Get the signed-in user as the application's User model. Aborts with 403 when nobody is
     * signed in, so callers never have to handle a missing user.
     */
    function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
