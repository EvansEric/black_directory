<?php

namespace App\Models;

use App\Services\SubscriptionGating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Business extends Model
{
    /** @use HasFactory<Factory<Business>> */
    use Billable, HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'location',
        'city',
        'state',
        'address',
        'zip',
        'latitude',
        'longitude',
        'phone',
        'website',
        'custom_slug',
        'image',
        'description',
        'has_rich_description',
        'rating',
        'reviews_count',
        'featured',
        'is_verified',
        'tags',
        'free_parking',
        'insurance_types',
        'subscription_tier',
        'billing_cycle',
        'stripe_id',
        'subscription_status',
        'subscription_ends_at',
    ];

    /** @var array<string, string> */
    public const INSURANCE_TYPES = [
        'auto' => 'Auto',
        'home' => 'Home',
        'commercial' => 'Commercial',
        'life' => 'Life',
        'health' => 'Health',
        'pet' => 'Pet',
    ];

    protected static function booted(): void
    {
        // The combined "City, ST" label stays in sync for search and display.
        static::saving(function (self $business): void {
            if (filled($business->city) && filled($business->state)) {
                $business->location = trim($business->city).', '.strtoupper(trim($business->state));
            }
        });
    }

    protected $casts = [
        'tags' => 'array',
        'featured' => 'boolean',
        'has_rich_description' => 'boolean',
        'is_verified' => 'boolean',
        'free_parking' => 'boolean',
        'insurance_types' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
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

    public function stripeEmail(): ?string
    {
        return $this->user?->email;
    }

    public function stripeName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
     */
    public function stripeMetadata(): array
    {
        return ['business_id' => (string) $this->id];
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
        if ($this->user?->is_admin) {
            return true;
        }

        return SubscriptionGating::canAccessLogo($this);
    }

    /**
     * The address shown to visitors: the masked /b/{slug} link when set, else the website itself.
     */
    public function websiteLink(): ?string
    {
        if (! $this->canAccessCustomUrl() || blank($this->website)) {
            return null;
        }

        return filled($this->custom_slug) ? route('business.link', $this->custom_slug) : $this->website;
    }

    public function canAccessCustomUrl(): bool
    {
        if ($this->user?->is_admin) {
            return true;
        }

        return SubscriptionGating::canAccessCustomUrl($this);
    }

    public function canAccessDescription(): bool
    {
        if ($this->user?->is_admin) {
            return true;
        }

        return SubscriptionGating::canAccessDescription($this);
    }

    public function canAccessLeadForms(): bool
    {
        if ($this->user?->is_admin) {
            return true;
        }

        return SubscriptionGating::canAccessLeadForms($this);
    }

    public function canAccessSpotlight(): bool
    {
        if ($this->user?->is_admin) {
            return true;
        }

        return SubscriptionGating::canAccessSpotlight($this);
    }

    /** Listings on a priority plan or flagged as featured get the Featured tag. */
    public function isFeatured(): bool
    {
        return $this->featured || $this->canAccessPrioritySearch();
    }

    public function canAccessPrioritySearch(): bool
    {
        if ($this->user?->is_admin) {
            return true;
        }

        return SubscriptionGating::canAccessPrioritySearch($this);
    }

    /**
     * Filter food listings by whether they offer free parking.
     *
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    public function scopeWithFreeParking(Builder $query, bool $hasFreeParking = true): Builder
    {
        return $query->where('free_parking', $hasFreeParking);
    }

    /**
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    public function scopeOfferingInsurance(Builder $query, string $type): Builder
    {
        return $query->whereJsonContains('insurance_types', $type);
    }

    /**
     * Adds a distance_miles column (great-circle, miles) and optionally limits to a radius.
     *
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    public function scopeNearby(Builder $query, float $latitude, float $longitude, ?int $miles = null): Builder
    {
        $rad = 0.017453292519943295;
        $distance = '(7917.5 * asin(sqrt(
            power(sin((businesses.latitude * ? - ?) / 2), 2)
            + cos(businesses.latitude * ?) * ? * power(sin((businesses.longitude * ? - ?) / 2), 2)
        )))';
        $bindings = [$rad, $latitude * $rad, $rad, cos($latitude * $rad), $rad, $longitude * $rad];

        $query->select('businesses.*')->selectRaw("{$distance} as distance_miles", $bindings);

        if ($miles !== null) {
            $query->whereNotNull('businesses.latitude')
                ->whereNotNull('businesses.longitude')
                ->whereRaw("{$distance} <= ?", [...$bindings, $miles]);
        }

        return $query;
    }

    /**
     * Closest first; listings without coordinates go last.
     *
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    public function scopeOrderedByDistance(Builder $query): Builder
    {
        return $query->orderByRaw('distance_miles IS NULL')->orderBy('distance_miles')->orderByDesc('featured');
    }

    // Scope for Tier Priority Sorting (Homepage Spotlight > Featured / Pro > Standard > Free)
    /**
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    public function scopeOrderedByTier(Builder $query, string $secondarySort = 'featured'): Builder
    {
        if ($secondarySort === 'name') {
            return $query->orderBy('name');
        }

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
            default => $query->orderByDesc('featured')->orderByDesc('created_at'),
        };
    }

    /**
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    /**
     * Unverified listings are only visible to their owner and admins.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->is_admin) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('businesses.is_verified', true);

            if ($user) {
                $q->orWhere('businesses.user_id', $user->id);
            }
        });
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
