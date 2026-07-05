<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id', 'partner_id', 'discount_percent', 'phone', 'address', 'status',
    ];

    protected function casts(): array
    {
        return ['discount_percent' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
