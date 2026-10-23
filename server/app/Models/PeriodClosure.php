<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodClosure extends Model
{
    protected $fillable = [
        'academic_year_id',
        'semester_id',
        'period_label',
        'period_type',
        'closed_at',
        'closed_by',
        'closed_reason',
        'is_reopened',
        'reopened_at',
        'reopened_by',
        'reopening_reason',
        'total_bills_amount',
        'total_paid_amount',
        'outstanding_amount',
        'total_transactions_count',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'is_reopened' => 'boolean',
        'total_bills_amount' => 'decimal:2',
        'total_paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isCurrentlyClosed(): bool
    {
        return !$this->is_reopened;
    }

    public function scopeActiveClosure($query)
    {
        return $query->where('is_reopened', false);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('period_type', $type);
    }

    public static function isPeriodClosed(
        int $academicYearId,
        ?int $semesterId = null,
        ?string $periodLabel = null
    ): bool {
        return static::where('academic_year_id', $academicYearId)
            ->when($semesterId, fn($q) => $q->where('semester_id', $semesterId))
            ->when($periodLabel, fn($q) => $q->where('period_label', $periodLabel))
            ->where('is_reopened', false)
            ->exists();
    }
}
