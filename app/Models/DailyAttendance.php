<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'employee_id',
        'emp_code',
        'attendance_date',
        'check_in',
        'check_out',
        'work_hours',
        'status',
        'is_late',
        'is_early_leave',
        'is_overtime',
        'notes',
        'raw_data'
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'is_late' => 'boolean',
        'is_early_leave' => 'boolean',
        'is_overtime' => 'boolean',
        'raw_data' => 'array'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function transactions()
    {
        return $this->hasMany(AttendanceTransaction::class);
    }
}