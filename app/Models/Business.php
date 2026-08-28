<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'tags' => 'array',
        'featured' => 'boolean',
        'rating' => 'decimal:1',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
