<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;

class PendingPlanSelection
{
    private const SESSION_KEY = 'pending_plan_selection';

    public function remember(string $tier, string $cycle): void
    {
        Session::put(self::SESSION_KEY, ['tier' => $tier, 'cycle' => $cycle]);
    }

    public function exists(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /** @return array{tier: string, cycle: string}|null */
    public function pull(): ?array
    {
        return Session::pull(self::SESSION_KEY);
    }

    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
