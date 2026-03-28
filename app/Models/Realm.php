<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['id', 'name', 'slug', 'region', 'timezone', 'is_online'])]
class Realm extends Model
{
    public $incrementing = false;
    protected $keyType = 'int';
}
