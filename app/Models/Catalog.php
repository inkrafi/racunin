<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Catalog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'username',
        'display_name',
        'bio',
        'avatar_url',
        'theme',
        'social_links',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Owner user/affiliate of this catalog.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor for full public catalog URL.
     */
    protected function publicUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => url('@' . $this->username),
        );
    }

    /**
     * Available catalog themes: an accent colour (buttons, links, chips) and a
     * soft wash of it (backgrounds). Applied as CSS variables on the public page.
     *
     * @return array<string, array{name: string, accent: string, wash: string}>
     */
    public static function themes(): array
    {
        return [
            'ramuan' => ['name' => 'Racun', 'accent' => '#E0186C', 'wash' => '#FFE4EF'],
            'plum' => ['name' => 'Ungu', 'accent' => '#5B1A7A', 'wash' => '#F3E8FB'],
            'orange' => ['name' => 'Jingga', 'accent' => '#C4471D', 'wash' => '#FCEEE7'],
            'indigo' => ['name' => 'Nila', 'accent' => '#4338CA', 'wash' => '#EEF0FD'],
            'emerald' => ['name' => 'Hijau', 'accent' => '#0E7C5A', 'wash' => '#E4F2EC'],
            'slate' => ['name' => 'Grafit', 'accent' => '#1F2933', 'wash' => '#ECEFF1'],
        ];
    }

    /**
     * Accessor for the selected theme's colours (falls back to ramuan).
     */
    protected function themeConfig(): Attribute
    {
        return Attribute::make(
            get: fn () => static::themes()[$this->theme] ?? static::themes()['ramuan'],
        );
    }
}
