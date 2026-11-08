<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;

class BusinessLinkController extends Controller
{
    /**
     * Masked business address: /b/{slug} forwards visitors to the real website.
     */
    public function __invoke(string $slug): RedirectResponse
    {
        $business = Business::query()->where('custom_slug', $slug)->where('is_verified', true)->first();

        abort_unless($business && $business->canAccessCustomUrl() && filled($business->website), 404);

        return redirect()->away($business->website);
    }
}
