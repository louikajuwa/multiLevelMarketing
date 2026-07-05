<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id', 'partner_level_id', 'discount_override_percent',
        'sponsor_id', 'parent_id', 'path', 'depth',
        'direct_referral_count', 'total_downline_count', 'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_override_percent' => 'decimal:2',
            'depth' => 'integer',
            'direct_referral_count' => 'integer',
            'total_downline_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(PartnerLevel::class, 'partner_level_id');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'sponsor_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Partner::class, 'parent_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    /** Descendants via the materialized path (path LIKE this.path + id + %). */
    public function scopeDescendantsOf(Builder $q, Partner $partner): Builder
    {
        return $q->where('path', 'like', $partner->path.$partner->id.'/%');
    }
}
