<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Bill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bill_number',
        'student_id',
        'bill_type_id',
        'academic_year_id',
        'semester_id',
        'student_name_snapshot',
        'class_name_snapshot',
        'grade_level_snapshot',
        'period_label',
        'due_date',
        'original_amount',
        'discount_amount',
        'final_amount',
        'paid_amount',
        'discount_reason',
        'discount_by',
        'status',
        'published_at',
        'created_by',
        'cancelled_by',
        'cancellation_reason'
    ];

    protected $casts = [
        'due_date' => 'date',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function billType(): BelongsTo
    {
        return $this->belongsTo(BillType::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function discountByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Hitung sisa tagihan yang belum lunas
    public function getRemainingAmountAttribute()
    {
        return number_format($this->final_amount - $this->paid_amount, 2, '.', '');
    }

    // cek apakah tagihan masih memiliki saldo yang bisa dibayar
    public function hasBalance()
    {
        return ($this->final_amount - $this->paid_amount) > 0 && !in_array($this->status, ['cancelled']);
    }

    // Scope untuk tagihan yang belum lunas (untuk dashboard/alokasi pembayaran)
    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['unpaid', 'partial'])
            ->where('final_amount', '>', DB::raw('paid_amount'));
    }

    // Scope untuk tagihan terlambat
    public function scoopeOverdue($query)
    {
        return $query->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', now()->toDateString());
    }
}
