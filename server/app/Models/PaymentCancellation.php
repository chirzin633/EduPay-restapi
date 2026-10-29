<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCancellation extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'payment_id',
        'requested_by',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime'
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
