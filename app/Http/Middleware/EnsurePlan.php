<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlan
{
    public function handle(Request $request, Closure $next, string $plan = 'pro'): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')->with('status', 'سجّل الدخول أولاً');
        }

        if (! $user->hasPlan($plan)) {
            return redirect()->route('pricing')->with('status', 'هذه الميزة متاحة في باقة '.config("dinar.plans.{$plan}.name").' 💎');
        }

        return $next($request);
    }
}
