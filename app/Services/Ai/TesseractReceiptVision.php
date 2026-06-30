<?php

namespace App\Services\Ai;

use App\Models\Receipt;
use App\Services\Ai\Contracts\ReceiptVision;
use App\Services\Storage\ReceiptStorage;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * OCR-based receipt reader backed by the local Tesseract binary.
 *
 * This is the demo/default real reader: cheap, offline, deterministic. It only
 * shells out to tesseract and hands the text to ReceiptTextParser (which holds
 * all the layout knowledge and is unit-tested). Requires `tesseract-ocr` +
 * `tesseract-ocr-spa` on the host (installed in the Dockerfile).
 *
 * Runs only when AI_DRIVER=ocr; on any failure it throws, and the caller
 * (ValidateReceiptJob) treats that as "leave for human review", never reject.
 */
class TesseractReceiptVision implements ReceiptVision
{
    public function __construct(
        private readonly ReceiptStorage $storage,
        private readonly ReceiptTextParser $parser,
    ) {}

    public function extract(Receipt $receipt): ReceiptExtraction
    {
        $bytes = $this->storage->get($receipt->s3_key);
        if ($bytes === null) {
            throw new RuntimeException("Receipt image not found: {$receipt->s3_key}");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rcpt_');
        file_put_contents($tmp, $bytes);

        try {
            $bin = config('cuentaclara.ai.ocr.bin', 'tesseract');
            $lang = config('cuentaclara.ai.ocr.lang', 'spa');

            $result = Process::timeout(60)->run([$bin, $tmp, 'stdout', '-l', $lang]);

            if (! $result->successful()) {
                throw new RuntimeException('tesseract failed: '.$result->errorOutput());
            }

            $text = $result->output();
        } finally {
            @unlink($tmp);
        }

        return $this->parser->parse($text, $receipt->event);
    }
}
