<?php

namespace App\Models;

use App\Services\SubscriptionGating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'location',
        'address',
        'zip',
        'phone',
        'website',
        'image',
        'description',
        'rating',
        'reviews_count',
        'featured',
        'tags',
        'subscription_tier',
        'billing_cycle',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'subscription_ends_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'featured' => 'boolean',
        'rating' => 'decimal:1',
        'subscription_ends_at' => 'datetime',
    ];

    // CamelCase accessors to fulfill API requirements ($business->subscriptionTier, etc.)
    public function getSubscriptionTierAttribute(): string
    {
        return $this->attributes['subscription_tier'] ?? SubscriptionGating::TIER_FREE;
    }

    public function setSubscriptionTierAttribute(?string $value): void
    {
        $this->attributes['subscription_tier'] = $value ?? SubscriptionGating::TIER_FREE;
    }

    public function getBillingCycleAttribute(): ?string
    {
        return $this->attributes['billing_cycle'] ?? null;
    }

    public function setBillingCycleAttribute(?string $value): void
    {
        $this->attributes['billing_cycle'] = $value;
    }

    public function getStripeCustomerIdAttribute(): ?string
    {
        return $this->attributes['stripe_customer_id'] ?? null;
    }

    public function setStripeCustomerIdAttribute(?string $value): void
    {
        $this->attributes['stripe_customer_id'] = $value;
    }

    public function getStripeSubscriptionIdAttribute(): ?string
    {
        return $this->attributes['stripe_subscription_id'] ?? null;
    }

    public function setStripeSubscriptionIdAttribute(?string $value): void
    {
        $this->attributes['stripe_subscription_id'] = $value;
    }

    public function getSubscriptionStatusAttribute(): string
    {
        return $this->attributes['subscription_status'] ?? 'active';
    }

    public function setSubscriptionStatusAttribute(?string $value): void
    {
        $this->attributes['subscription_status'] = $value ?? 'active';
    }

    // Feature gating helpers
    public function hasActiveSubscription(): bool
    {
        return SubscriptionGating::hasActiveSubscription($this);
    }

    public function canAccessLogo(): bool
    {
        return SubscriptionGating::canAccessLogo($this);
    }

    public function canAccessCustomUrl(): bool
    {
        return SubscriptionGating::canAccessCustomUrl($this);
    }

    public function canAccessDescription(): bool
    {
        return SubscriptionGating::canAccessDescription($this);
    }

    public function canAccessLeadForms(): bool
    {
        return SubscriptionGating::canAccessLeadForms($this);
    }

    public function canAccessSpotlight(): bool
    {
        return SubscriptionGating::canAccessSpotlight($this);
    }

    public function canAccessPrioritySearch(): bool
    {
        return SubscriptionGating::canAccessPrioritySearch($this);
    }

    // Scope for Tier Priority Sorting (Homepage Spotlight > Featured / Pro > Standard > Free)
    public function scopeOrderedByTier(Builder $query, string $secondarySort = 'featured'): Builder
    {
        $query->orderByRaw("
            CASE
                WHEN subscription_tier = 'spotlight' AND (subscription_status = 'active' OR subscription_status = 'trialing' OR subscription_status IS NULL) THEN 4
                WHEN (subscription_tier = 'pro' OR subscription_tier = 'featured' OR featured = 1) AND (subscription_status = 'active' OR subscription_status = 'trialing' OR subscription_status IS NULL) THEN 3
                WHEN (subscription_tier = 'standard' OR subscription_tier = 'basic') AND (subscription_status = 'active' OR subscription_status = 'trialing' OR subscription_status IS NULL) THEN 2
                ELSE 1
            END DESC
        ");

        return match ($secondarySort) {
            'rating' => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('featured')->orderByDesc('created_at'),
        };
    }

    public function scopeSpotlight(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('subscription_tier', 'spotlight')
                ->where(function ($sq) {
                    $sq->whereIn('subscription_status', ['active', 'trialing'])
                        ->orWhereNull('subscription_status');
                });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
