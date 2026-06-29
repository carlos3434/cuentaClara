<?php

namespace App\Services\Ai\Contracts;

use App\Models\Receipt;
use App\Services\Ai\ReceiptExtraction;

interface ReceiptVision
{
    /**
     * Read a receipt image and return structured extraction.
     *
     * Implementations should throw on transport/parse failure; the caller
     * (ValidateReceiptJob) treats a throw as "needs review", never a rejection.
     */
    public function extract(Receipt $receipt): ReceiptExtraction;
}
