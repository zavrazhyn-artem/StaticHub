<?php

namespace App\Models;

use App\Builders\TransactionBuilder;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'static_id',
        'amount',
        'type',
        'week_number',
        'description',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'week_number' => 'integer',
        'created_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): TransactionBuilder
    {
        return new TransactionBuilder($query);
    }

    public static function query(): TransactionBuilder
    {
        return parent::query();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function static()
    {
        return $this->belongsTo(StaticGroup::class, 'static_id');
    }
}
