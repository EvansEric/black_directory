<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Carbon;

class SubscriptionGating
{
    public const TIER_FREE = 'free';

    public const TIER_STANDARD = 'standard';

    public const TIER_PRO = 'pro';

    public const TIER_SPOTLIGHT = 'spotlight';

    public const TIERS = [
        self::TIER_STANDARD => [
            'key' => self::TIER_STANDARD,
            'name' => 'Basic / Standard',
            'monthly_price' => 19.00,
            'annual_price' => 190.00,
            'stripe_monthly_price_id' => 'price_standard_monthly',
            'stripe_annual_price_id' => 'price_standard_annual',
            'features' => ['Custom Link', 'Logo Upload', 'Business Description'],
            'priority' => 1,
        ],
        self::TIER_PRO => [
            'key' => self::TIER_PRO,
            'name' => 'Featured / Pro',
            'monthly_price' => 49.00,
            'annual_price' => 490.00,
            'stripe_monthly_price_id' => 'price_pro_monthly',
            'stripe_annual_price_id' => 'price_pro_annual',
            'features' => ['Priority Search Placement', 'Lead Generation Forms', 'All Basic Features'],
            'priority' => 2,
        ],
        self::TIER_SPOTLIGHT => [
            'key' => self::TIER_SPOTLIGHT,
            'name' => 'Homepage Spotlight',
            'monthly_price' => 199.00,
            'annual_price' => 1990.00,
            'stripe_monthly_price_id' => 'price_spotlight_monthly',
            'stripe_annual_price_id' => 'price_spotlight_annual',
            'features' => ['Top Banner & Homepage Rotation Slots', 'Priority Search Placement', 'Lead Generation Forms', 'All Featured Features'],
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
        $tier = self::getEffectiveTier($listing);

        return in_array($tier, [self::TIER_STANDARD, self::TIER_PRO, self::TIER_SPOTLIGHT], true);
    }

    /**
     * Check if a listing has access to custom URL link.
     */
    public static function canAccessCustomUrl(?Business $listing): bool
    {
        $tier = self::getEffectiveTier($listing);

        return in_array($tier, [self::TIER_STANDARD, self::TIER_PRO, self::TIER_SPOTLIGHT], true);
    }

    /**
     * Check if a listing has access to business description.
     */
    public static function canAccessDescription(?Business $listing): bool
    {
        $tier = self::getEffectiveTier($listing);

        return in_array($tier, [self::TIER_STANDARD, self::TIER_PRO, self::TIER_SPOTLIGHT], true);
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
}
