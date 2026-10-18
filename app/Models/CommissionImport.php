<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionImport extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'platform',
        'filename',
        'rows_total',
        'rows_imported',
        'rows_matched',
    ];

    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }
}
