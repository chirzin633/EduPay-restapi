<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id_internal',
        'nis',
        'nisn',
        'full_name',
        'gender',
        'birth_date',
        'parent_name',
        'parent_phone',
        'address',
        'status',
        'notes'
    ];

    protected $casts = [
        'birth_date' => 'date'
    ];

    public function studentClasses(): HasMany
    {
        return $this->hasMany(StudentClass::class);
    }

    public function currentClass()
    {
        return $this->hasOne(StudentClass::class)->where('is_current', true)->latest('enrollment_date');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function canRecieveBills(): bool
    {
        return in_array($this->status, ['active']);
    }
}
