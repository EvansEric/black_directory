<?php

use App\Models\Business;
use App\Services\SubscriptionGating;

if (! function_exists('canAccessLeadForms')) {
    function canAccessLeadForms(?Business $listing): bool
    {
        return SubscriptionGating::canAccessLeadForms($listing);
    }
}

if (! function_exists('canAccessLogo')) {
    function canAccessLogo(?Business $listing): bool
    {
        return SubscriptionGating::canAccessLogo($listing);
    }
}

if (! function_exists('canAccessCustomUrl')) {
    function canAccessCustomUrl(?Business $listing): bool
    {
        return SubscriptionGating::canAccessCustomUrl($listing);
    }
}

if (! function_exists('canAccessDescription')) {
    function canAccessDescription(?Business $listing): bool
    {
        return SubscriptionGating::canAccessDescription($listing);
    }
}

if (! function_exists('canAccessSpotlight')) {
    function canAccessSpotlight(?Business $listing): bool
    {
        return SubscriptionGating::canAccessSpotlight($listing);
    }
}
