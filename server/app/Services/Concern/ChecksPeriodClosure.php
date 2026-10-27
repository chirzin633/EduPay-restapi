<?php

namespace App\Services\Concern;

use App\Models\PeriodClosure;
use Illuminate\Validation\ValidationException;

trait ChecksPeriodClosure
{
    protected function ensurePeriodIsOpen(
        int $academicYearId,
        ?int $semesterId = null,
        ?string $periodLabel = null
    ) {
        if (PeriodClosure::isPeriodClosed($academicYearId, $semesterId, $periodLabel)) {
            throw ValidationException::withMessages([
                'period' => "Periode {$periodLabel} sudah ditutup. Transaksi tidak dapat diproses. Hubungi kepala sekolah untuk pembukaan kembali."
            ]);
        }
    }
}
