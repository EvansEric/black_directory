<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReviewVerification extends Model
{
    protected $fillable = [
        'business_id', 'user_id', 'status', 'transaction_hash', 'transaction_reference',
        'evidence', 'evidence_type', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_reference' => 'encrypted',
            'evidence' => 'encrypted',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<BusinessReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(BusinessReview::class);
    }
}
