<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillTypeTariff extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bill_type_id',
        'grade_level',
        'class_name',
        'amount',
        'is_active'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function billType(): BelongsTo
    {
        return $this->belongsTo(BillType::class);
    }
}