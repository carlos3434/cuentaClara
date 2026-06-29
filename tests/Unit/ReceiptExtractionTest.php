<?php

namespace Tests\Unit;

use App\Services\Ai\ReceiptExtraction;
use PHPUnit\Framework\TestCase;

class ReceiptExtractionTest extends TestCase
{
    public function test_maps_a_full_model_payload(): void
    {
        $json = [
            'is_payment_receipt' => true,
            'amount' => ['value' => 40.50, 'currency' => 'PEN', 'confidence' => 0.94],
            'payment_date' => ['value' => '2026-06-24', 'confidence' => 0.9],
            'recipient' => ['name' => 'Caro Rojas', 'confidence' => 0.8],
            'payment_method' => ['value' => 'yape', 'confidence' => 0.97],
            'overall_confidence' => 0.86,
            'explanation' => 'Yape de S/ 40.50 a Caro.',
        ];

        $x = ReceiptExtraction::fromModelJson($json);

        $this->assertTrue($x->isReceipt);
        $this->assertSame(4050, $x->amountCents);
        $this->assertSame('PEN', $x->currency);
        $this->assertSame('2026-06-24', $x->date);
        $this->assertSame('Caro Rojas', $x->recipient);
        $this->assertSame('yape', $x->method);
        $this->assertSame(0.86, $x->confidence);
        $this->assertSame('Yape de S/ 40.50 a Caro.', $x->explanation);
        $this->assertSame($json, $x->raw);
    }

    public function test_converts_decimal_amount_to_integer_cents(): void
    {
        $this->assertSame(1999, ReceiptExtraction::fromModelJson(['amount' => ['value' => 19.99]])->amountCents);
        $this->assertSame(4000, ReceiptExtraction::fromModelJson(['amount' => ['value' => 40]])->amountCents);
    }

    public function test_null_amount_yields_null_cents(): void
    {
        $this->assertNull(ReceiptExtraction::fromModelJson(['amount' => ['value' => null]])->amountCents);
        $this->assertNull(ReceiptExtraction::fromModelJson([])->amountCents);
    }

    public function test_missing_fields_default_safely(): void
    {
        $x = ReceiptExtraction::fromModelJson([]);

        $this->assertFalse($x->isReceipt);
        $this->assertNull($x->amountCents);
        $this->assertNull($x->date);
        $this->assertNull($x->method);
        $this->assertNull($x->recipient);
        $this->assertSame(0.0, $x->confidence);
        $this->assertSame('', $x->explanation);
    }
}
