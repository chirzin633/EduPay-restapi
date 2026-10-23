<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'billing_cycle',
        'default_amount',
        'is_active'
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function tariffs(): HasMany
    {
        return $this->hasMany(BillTypeTariff::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRecurring($query)
    {
        return $query->where('billing_cycle', 'recurring');
    }

    public function getAmountForStudent(?string $gradeLevel, ?string $className)
    {
        // Cari tarif paling spesifik dulu (kelas + tingkat)
        $tariff =  $this->tariffs()
            ->where('grade_level', $gradeLevel)
            ->where('class_name', $className)
            ->where('is_active', true)
            ->first();

        if ($tariff) {
            return $tariff->amount;
        }

        // Fallback ke tarif tingkat saja
        $tariff = $this->tarrifs()
            ->where('grade_lavel', $gradeLevel)
            ->whereNull('class_name')
            ->where('is_active', true)
            ->first();

        if ($tariff) {
            return $tariff->amount;
        }

        return $this->default_amount;
    }
}