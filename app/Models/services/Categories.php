<?php

namespace App\Models\services;

use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
//    protected $fillable = ['parent_id', 'name'];

    public function child()
    {
        return $this->hasMany(Categories::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Categories::class, 'parent_id');

    }
}
