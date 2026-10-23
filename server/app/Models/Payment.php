<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_number',
        'student_id',
        'recorded_by',
        'verified_by',
        'payment_method',
        'total_amount',
        'payment_date',
        'reference_number',
        'notes',
        'attachment_path',
        'status',
        'verified_at',
        'verification_notes',
        'rejection_reason',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_date' => 'date',
        'verified_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorderByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'verified';
    }

    public function getTotalAllocatedAttribute(): string
    {
        if (!$this->isConfirmed()) {
            return '0.00';
        }

        return number_format(
            $this->allocations()->sum('allocated_amount'),
            2,
            '.',
            ''
        );
    }

    public function scoopePendingVerification($query)
    {
        return $query->where('status', 'pending_verification');
    }

    public function scoopeConfirmed($query)
    {
        return $query->where('status', 'verified');
    }
}
