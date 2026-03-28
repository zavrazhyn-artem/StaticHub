<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class StaticGroup extends Model
{
    use HasFactory;

    protected $table = 'statics';

    protected $fillable = [
        'name',
        'slug',
        'region',
        'server',
        'owner_id',
    ];

    /**
     * Get the owner of the static.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the members of the static.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'static_user', 'static_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include statics the user belongs to.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('member', function (Builder $builder) {
            if (auth()->check()) {
                $builder->whereHas('members', function ($query) {
                    $query->where('user_id', auth()->id());
                });
            }
        });
    }
}
