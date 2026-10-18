<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkClickSummary extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'link_id',
        'user_id',
        'date',
        'total_clicks',
        'unique_clicks',
        'mobile_clicks',
        'desktop_clicks',
        'tablet_clicks',
        'top_channel',
        'channel_breakdown',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_clicks' => 'integer',
            'unique_clicks' => 'integer',
            'mobile_clicks' => 'integer',
            'desktop_clicks' => 'integer',
            'tablet_clicks' => 'integer',
            'channel_breakdown' => 'array',
        ];
    }

    /**
     * Target link of this summary.
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    /**
     * Affiliate user who owns the link.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
