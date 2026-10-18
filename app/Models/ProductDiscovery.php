<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDiscovery extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_input',
        'uniqueness_score',
        'emotional_score',
        'viral_potential',
        'target_audience',
        'pitch_angle',
        'reasoning',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
