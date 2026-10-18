<?php

namespace App\Models;

use App\Enums\LinkStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Jobs\GenerateLinkEmbeddingJob;
use App\Support\AffiliateUrlPolicy;
use App\Support\ProductRef;
use Illuminate\Support\Facades\Cache;

class Link extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'original_url',
        'slug',
        'sub_id',
        'product_ref',
        'status',
        'description',
        'og_title',
        'og_description',
        'og_image_url',
        'price',
        'discount_tag',
        'category',
        'is_in_catalog',
        'is_featured',
        'sort_order',
        'ai_captions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LinkStatus::class,
            'price' => 'decimal:2',
            'is_in_catalog' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'ai_captions' => 'array',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (Link $link) {
            // Keys for matching commission reports. An explicit product_ref (from the resolved
            // short link) wins; otherwise it follows the destination URL.
            if ($link->isDirty('original_url') && ! $link->isDirty('product_ref')) {
                $link->product_ref = ProductRef::fromUrl($link->original_url);
            }

            $link->sub_id = ProductRef::normalizeSubId($link->sub_id) ?? ProductRef::subIdFromUrl($link->original_url);
        });

        static::saved(function (Link $link) {
            // Single place for redirect cache invalidation (also clears a negative "missing" entry on create)
            Cache::forget(static::redirectCacheKey($link->slug));
            if ($link->wasChanged('slug') && $link->getOriginal('slug')) {
                Cache::forget(static::redirectCacheKey($link->getOriginal('slug')));
            }

            // Check if relevant fields for embedding have changed, or if it's newly created
            if ($link->wasRecentlyCreated || $link->wasChanged(['title', 'description', 'category', 'discount_tag'])) {
                GenerateLinkEmbeddingJob::dispatch($link->id);
            }
        });

        static::deleted(function (Link $link) {
            Cache::forget(static::redirectCacheKey($link->slug));
        });
    }

    /**
     * Cache key for the redirect lookup of a slug.
     */
    public static function redirectCacheKey(string $slug): string
    {
        return "links:redirect:{$slug}";
    }

    /**
     * The owner affiliate of the link.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Orders attributed to this link from imported commission reports.
     */
    public function conversions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Conversion::class);
    }

    /**
     * Raw clicks logged for this link.
     */
    public function clicks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LinkClick::class);
    }

    /**
     * Daily pre-aggregated click summaries.
     */
    public function summaries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LinkClickSummary::class);
    }

    /**
     * Scope query to only active links.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LinkStatus::ACTIVE->value);
    }

    /**
     * Scope query to links owned by a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope query to active links displayed in public catalog.
     */
    public function scopeInCatalog(Builder $query): Builder
    {
        return $query->where('is_in_catalog', true)
            ->where('status', LinkStatus::ACTIVE->value);
    }

    /**
     * Scope query to featured links.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope query by category if provided.
     */
    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        if (! $category || $category === 'all') {
            return $query;
        }

        return $query->where('category', $category);
    }

    /**
     * Accessor for full short URL.
     */
    protected function shortUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => url($this->slug),
        );
    }

    /**
     * Accessor for Indonesian Rupiah formatted price.
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price !== null ? 'Rp ' . number_format((float) $this->price, 0, ',', '.') : null,
        );
    }

    /**
     * Accessor for effective Open Graph title fallback.
     */
    protected function effectiveOgTitle(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->og_title ?: $this->title,
        );
    }

    /**
     * Accessor for effective Open Graph description fallback.
     */
    protected function effectiveOgDescription(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->og_description ?: ($this->description ?: self::defaultOgDescription($this->original_url)),
        );
    }

    /**
     * Store name of the destination, e.g. "Tokopedia"; null for affiliate networks and extra domains.
     */
    protected function platformLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => app(AffiliateUrlPolicy::class)->platformFor($this->original_url)['label'] ?? null,
        );
    }

    /**
     * Call-to-action for the buy button, e.g. "Beli di Shopee".
     */
    protected function buyLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->platform_label ? "Beli di {$this->platform_label}" : 'Lihat produk',
        );
    }

    /**
     * Share-preview description used when the owner didn't write one.
     */
    public static function defaultOgDescription(?string $originalUrl): string
    {
        $label = app(AffiliateUrlPolicy::class)->platformFor($originalUrl)['label'] ?? null;

        return $label
            ? "Lihat harga terbaru produk ini di {$label}."
            : 'Lihat harga terbaru produk ini.';
    }

    /**
     * Check if link is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === LinkStatus::ACTIVE;
    }
}
