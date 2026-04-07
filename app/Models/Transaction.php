<?php

namespace App\Models;

use App\Builders\TransactionBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $static_id
 * @property int $amount
 * @property string $type
 * @property string $period_key
 * @property string|null $description
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read StaticGroup $static
 * @method static TransactionBuilder query()
 * @method static TransactionBuilder<static>|Transaction byType(string $type)
 * @method static TransactionBuilder<static>|Transaction forStatic(int $staticId)
 * @method static TransactionBuilder<static>|Transaction inPeriod(string $periodKey)
 * @method static TransactionBuilder<static>|Transaction newModelQuery()
 * @method static TransactionBuilder<static>|Transaction newQuery()
 * @method static TransactionBuilder<static>|Transaction recent(int $limit = 10)
 * @method static TransactionBuilder<static>|Transaction sumAmount()
 * @method static TransactionBuilder<static>|Transaction whereAmount($value)
 * @method static TransactionBuilder<static>|Transaction whereCreatedAt($value)
 * @method static TransactionBuilder<static>|Transaction whereDescription($value)
 * @method static TransactionBuilder<static>|Transaction whereId($value)
 * @method static TransactionBuilder<static>|Transaction whereStaticId($value)
 * @method static TransactionBuilder<static>|Transaction whereType($value)
 * @method static TransactionBuilder<static>|Transaction whereUserId($value)
 * @method static TransactionBuilder<static>|Transaction wherePeriodKey($value)
 * @mixin \Eloquent
 */
class Transaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'static_id',
        'amount',
        'type',
        'period_key',
        'description',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'created_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): TransactionBuilder
    {
        return new TransactionBuilder($query);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function static(): BelongsTo
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }
}
