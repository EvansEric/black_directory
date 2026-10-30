<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\User;
use App\Services\SubscriptionGating;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySubscriptionFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $feature  Feature flag to verify (e.g. 'lead_forms', 'logo', 'custom_url', 'spotlight')
     */
    public function handle(Request $request, Closure $next, string $feature = 'lead_forms'): Response
    {
        $listingId = $request->route('business') ?? $request->route('listing') ?? $request->input('business_id') ?? $request->input('listing_id');

        $listing = null;
        if ($listingId instanceof Business) {
            $listing = $listingId;
        } elseif (is_numeric($listingId)) {
            $listing = Business::query()->find($listingId);
        }

        $user = $request->user();
        if (! $listing && $user instanceof User) {
            $listing = $user->businesses()->first();
        }

        if (! $listing instanceof Business || ! SubscriptionGating::verifyFeature($listing, $feature)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => "Your subscription tier does not have access to the '{$feature}' feature.",
                    'required_feature' => $feature,
                    'current_tier' => $listing instanceof Business ? $listing->subscription_tier : 'free',
                ], 403);
            }

            abort(403, "Upgrade your listing subscription to access the '{$feature}' feature.");
        }

        return $next($request);
    }
}
