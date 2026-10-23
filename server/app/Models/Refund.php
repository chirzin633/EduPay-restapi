<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'refund_number',
        'payment_id',
        'requested_by',
        'refund_amount',
        'reason',
        'refund_method',
        'recipient_account_name',
        'recipient_account_number',
        'attachment_path',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'completed_by',
        'completed_at',
        'completion_reference',
        'completion_notes'
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];


    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }

    public static function getMaxRefundableAmount(int $paymentId): float
    {
        $payment = Payment::findOrFail($paymentId);

        $totalRefunded = static::where('payment_id', $paymentId)
            ->whereNotIn('status', ['rejected'])
            ->sum('refund_amount');

        return max(0, (float)$payment->total_amount - (float)$totalRefunded);
    }

    public function scopePendingAction($query)
    {
        return $query->whereIn('status', ['pending_approval', 'approved', 'processed']);
    }
}
