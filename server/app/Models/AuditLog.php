<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name_snapshot',
        'action_at',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'request_method',
        'request_path'
    ];

    protected $casts = [
        'action_at' => 'datetime',
        'old_values' => 'array',
        'new_values' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForSubject($query, $type, $id = null)
    {
        $query->where('subject_type', $type);

        if (!$id !== null) {
            $query->where('subject_id', $id);
        }

        return $query;
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('action_at', [$from, $to]);
    }

    public function scopeSensitive($query)
    {
        return $query->whereIn('action', ['cancel', 'refund', 'verify', 'reject', 'reopen_period', 'change_role', 'discount']);
    }

    public static function log(
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null
    ): self {
        return static::create([
            'user_id' => Auth::id(),
            'user_name_snapshot' => Auth::user()?->name,
            'action_at' => now(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject?->getAttribute('bill_number')
                ?? $subject?->getAttribute('payment_number')
                ?? $subject?->getAttribute('name')
                ?? $subject?->getAttribute('email'),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_method' => request()->method(),
            'request_path' => request()->path()
        ]);
    }
}
