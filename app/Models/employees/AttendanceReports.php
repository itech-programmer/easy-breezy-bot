<?php

namespace App\Models\employees;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceReports extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'file_url',
        'type'
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendances::class);
    }

    public static function store_report(Attendances $attendance, $file_url, $report_type)
    {
        AttendanceReports::create([
            'attendance_id' => $attendance->id,
            'file_url' => $file_url,
            'type' => $report_type,
        ]);
    }
}
