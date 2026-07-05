<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerLevel extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name', 'level', 'discount_percent', 'commission_percent', 'sponsor_bonus_percent', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'discount_percent' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'sponsor_bonus_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
