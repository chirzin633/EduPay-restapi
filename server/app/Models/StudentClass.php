<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentClass extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'class_name',
        'grade_level',
        'enrollment_date',
        'exit_date',
        'is_current'
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'exit_date' => 'date',
        'is_current' => 'boolean'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function scoopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}
