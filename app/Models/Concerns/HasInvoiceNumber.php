<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

trait HasInvoiceNumber
{
    public static function bootHasInvoiceNumber(): void
    {
        static::creating(function (self $model): void {
            $model->invoice ??= $model->nextInvoiceNumber();
        });
    }

    abstract protected function invoicePrefix(): string;

    /**
     * Nomor dokumen dengan reset harian: PREFIX-YYMMDD-01, 02, ...
     */
    protected function nextInvoiceNumber(): string
    {
        $base = $this->invoicePrefix().'-'.now()->format('ymd').'-';

        $last = DB::table($this->getTable())
            ->where('invoice', 'like', $base.'%')
            ->lockForUpdate()
            ->orderByDesc('invoice')
            ->value('invoice');

        $number = $last ? (int) substr($last, -2) : 0;

        return $base.str_pad((string) ($number + 1), 2, '0', STR_PAD_LEFT);
    }
}
