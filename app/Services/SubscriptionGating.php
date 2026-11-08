<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Carbon;

class SubscriptionGating
{
    public const TIER_FREE = 'free';

    public const TIER_STANDARD = 'standard';

    public const TIER_PRO = 'pro';

    public const TIER_SPOTLIGHT = 'spotlight';

    /** Maximum businesses an owner may list, by their highest active tier (null = unlimited). */
    public const BUSINESS_LIMITS = [
        self::TIER_FREE => 1,
        self::TIER_STANDARD => 1,
        self::TIER_PRO => 5,
        self::TIER_SPOTLIGHT => null,
    ];

    public const TIERS = [
        self::TIER_FREE => [
            'key' => self::TIER_FREE,
            'name' => 'Free',
            'monthly_price' => 0,
            'annual_price' => 0,
            'features' => ['1 Business Listing', 'Business Profile & Directory Placement', 'Business Contact Information'],
            'priority' => 0,
        ],
        self::TIER_STANDARD => [
            'key' => self::TIER_STANDARD,
            'name' => 'Standard',
            'monthly_price' => 4.99,
            'annual_price' => 47.90,
            'stripe_monthly_price_id' => 'price_standard_monthly',
            'stripe_annual_price_id' => 'price_standard_annual',
            'features' => ['1 Business Listing', 'Custom Link', 'Logo Upload', 'Business Description'],
            'priority' => 1,
        ],
        self::TIER_PRO => [
            'key' => self::TIER_PRO,
            'name' => 'Pro',
            'monthly_price' => 10.99,
            'annual_price' => 105.50,
            'stripe_monthly_price_id' => 'price_pro_monthly',
            'stripe_annual_price_id' => 'price_pro_annual',
            'features' => ['Up to 5 Business Listings', 'Priority Search Placement', 'Lead Generation Forms', 'All Standard Features'],
            'priority' => 2,
        ],
        self::TIER_SPOTLIGHT => [
            'key' => self::TIER_SPOTLIGHT,
            'name' => 'Homepage Spotlight',
            'monthly_price' => 39.98,
            'annual_price' => 383.80,
            'stripe_monthly_price_id' => 'price_spotlight_monthly',
            'stripe_annual_price_id' => 'price_spotlight_annual',
            'features' => ['Unlimited Business Listings', 'Top Banner & Homepage Rotation Slots', 'Everything in Pro'],
            'priority' => 3,
        ],
    ];

    /**
     * Determine if a listing has an active subscription.
     */
    public static function hasActiveSubscription(?Business $listing): bool
    {
        if (! $listing) {
            return false;
        }

        $tier = strtolower($listing->subscription_tier ?? self::TIER_FREE);
        if ($tier === self::TIER_FREE) {
            return false;
        }

        $status = strtolower($listing->subscription_status ?? 'active');

        if (in_array($status, ['active', 'trialing'], true)) {
            return true;
        }

        if ($listing->subscription_ends_at && Carbon::parse($listing->subscription_ends_at)->isFuture()) {
            return true;
        }

        return false;
    }

    /**
     * Get the effective tier of a listing (returns 'free' if subscription is inactive).
     */
    public static function getEffectiveTier(?Business $listing): string
    {
        if (! $listing) {
            return self::TIER_FREE;
        }

        $rawTier = strtolower($listing->subscription_tier ?? self::TIER_FREE);

        if ($rawTier === 'featured') {
            $rawTier = self::TIER_PRO;
        } elseif ($rawTier === 'basic') {
            $rawTier = self::TIER_STANDARD;
        }

        if ($rawTier === self::TIER_FREE) {
            return self::TIER_FREE;
        }

        if (self::hasActiveSubscription($listing)) {
            return $rawTier;
        }

        return self::TIER_FREE;
    }

    /**
     * Check if a listing has access to logo upload/display.
     */
    public static function canAccessLogo(?Business $listing): bool
    {
        return self::hasStandardFeatures($listing);
    }

    /**
     * Check if a listing has access to custom URL link.
     */
    public static function canAccessCustomUrl(?Business $listing): bool
    {
        return self::hasStandardFeatures($listing);
    }

    /**
     * Check if a listing has access to business description.
     */
    public static function canAccessDescription(?Business $listing): bool
    {
        return self::hasStandardFeatures($listing);
    }

    private static function hasStandardFeatures(?Business $listing): bool
    {
        if (! $listing) {
            return false;
        }

        if (in_array(self::getEffectiveTier($listing), [self::TIER_STANDARD, self::TIER_PRO, self::TIER_SPOTLIGHT], true)) {
            return true;
        }

        if (! $listing->exists) {
            return false;
        }

        $listing->loadMissing('user.businesses');

        return $listing->user?->businesses->contains(
            fn (Business $business): bool => in_array(self::getEffectiveTier($business), [self::TIER_PRO, self::TIER_SPOTLIGHT], true)
        ) ?? false;
    }

    /**
     * Check if a listing has access to lead generation forms.
     */
    public static function canAccessLeadForms(?Business $listing): bool
    {
        $tier = self::getEffectiveTier($listing);

        return in_array($tier, [self::TIER_PRO, self::TIER_SPOTLIGHT], true);
    }

    /**
     * Check if a listing has access to Homepage Spotlight rotation/banner.
     */
    public static function canAccessSpotlight(?Business $listing): bool
    {
        $tier = self::getEffectiveTier($listing);

        return $tier === self::TIER_SPOTLIGHT;
    }

    /**
     * Check if a listing has priority search placement.
     */
    public static function canAccessPrioritySearch(?Business $listing): bool
    {
        $tier = self::getEffectiveTier($listing);

        return in_array($tier, [self::TIER_PRO, self::TIER_SPOTLIGHT], true);
    }

    /**
     * Generic feature verification helper.
     */
    public static function verifyFeature(?Business $listing, string $feature): bool
    {
        return match ($feature) {
            'logo', 'image' => self::canAccessLogo($listing),
            'custom_url', 'website', 'custom_link' => self::canAccessCustomUrl($listing),
            'description' => self::canAccessDescription($listing),
            'lead_forms', 'lead_form', 'leads' => self::canAccessLeadForms($listing),
            'spotlight', 'homepage_spotlight', 'banner' => self::canAccessSpotlight($listing),
            'priority_search', 'priority' => self::canAccessPrioritySearch($listing),
            default => false,
        };
    }

    /**
     * The owner's plan is the highest effective tier across their listings.
     */
    public static function getOwnerTier(User $user): string
    {
        $business = self::getOwnerPlanBusiness($user);

        if ($business) {
            return self::getEffectiveTier($business);
        }

        return self::pendingTier($user) ?? self::TIER_FREE;
    }

    /**
     * A paid plan the user bought before creating a listing, if any.
     */
    public static function pendingTier(User $user): ?string
    {
        $tier = $user->pending_plan_tier;

        if (blank($user->pending_stripe_session_id) || ! isset(self::TIERS[$tier])) {
            return null;
        }

        // A downgraded pending plan lapses once the period already paid for is over.
        $ends = $user->pending_plan_ends_at;

        return $ends && $ends->isPast() ? null : $tier;
    }

    /**
     * The listing that determines the owner's plan (highest effective paid tier), if any.
     */
    public static function getOwnerPlanBusiness(User $user): ?Business
    {
        $rank = [self::TIER_FREE => 0, self::TIER_STANDARD => 1, self::TIER_PRO => 2, self::TIER_SPOTLIGHT => 3];
        $best = null;
        $bestRank = 0;

        foreach ($user->businesses()->get() as $business) {
            $tierRank = $rank[self::getEffectiveTier($business)] ?? 0;

            if ($tierRank > $bestRank) {
                $best = $business;
                $bestRank = $tierRank;
            }
        }

        return $best;
    }

    /**
     * Whether a paid plan has been cancelled and is only running until its period ends.
     */
    public static function isEnding(Business $listing): bool
    {
        return strtolower((string) $listing->subscription_status) === 'canceled'
            && self::hasActiveSubscription($listing);
    }

    /**
     * Maximum number of listings the user may own (null = unlimited).
     */
    public static function businessLimit(User $user): ?int
    {
        return self::BUSINESS_LIMITS[self::getOwnerTier($user)] ?? 1;
    }

    public static function canAddBusiness(User $user): bool
    {
        $limit = self::businessLimit($user);

        return $limit === null || $user->businesses()->count() < $limit;
    }
}
