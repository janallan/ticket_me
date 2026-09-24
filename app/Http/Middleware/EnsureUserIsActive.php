<?php

namespace App\Http\Middleware;

use App\Livewire\Actions\Logout;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(private Logout $logout) {}

    /**
     * Log out users whose account has been deactivated, including sessions started
     * before the deactivation or through passkeys and "remember me" cookies.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            ($this->logout)();

            return redirect()->route('login')
                ->with('status', __('This account has been deactivated.'));
        }

        return $next($request);
    }
}
