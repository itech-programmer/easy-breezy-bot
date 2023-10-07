<?php

namespace App\Models\employees;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reports extends Model
{
    use HasFactory;

    public function attendance()
    {
        return $this->belongsTo(Attendances::class);
    }
}
