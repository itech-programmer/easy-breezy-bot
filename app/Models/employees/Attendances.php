<?php

namespace App\Models\employees;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Attendances extends Model
{
    public function employee()
    {
        return $this->belongsTo(User::class);
    }
}
