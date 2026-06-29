<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Receipt;
use App\Services\Ai\FakeReceiptVision;
use Tests\TestCase;

class FakeReceiptVisionTest extends TestCase
{
    public function test_extraction_matches_the_event_share(): void
    {
        $event = new Event([
            'share_cents' => 4000,
            'recipient_name' => 'Caro',
            'accepted_methods' => ['plin', 'yape'],
        ]);
        $receipt = new Receipt();
        $receipt->setRelation('event', $event);

        $x = (new FakeReceiptVision())->extract($receipt);

        $this->assertTrue($x->isReceipt);
        $this->assertSame(4000, $x->amountCents);          // = share → auto-validates
        $this->assertSame('PEN', $x->currency);
        $this->assertSame('Caro', $x->recipient);
        $this->assertSame('plin', $x->method);             // first accepted method
        $this->assertGreaterThanOrEqual(0.85, $x->confidence);
    }

    public function test_defaults_method_when_none_accepted(): void
    {
        $event = new Event(['share_cents' => 1000, 'recipient_name' => 'X', 'accepted_methods' => []]);
        $receipt = new Receipt();
        $receipt->setRelation('event', $event);

        $this->assertSame('yape', (new FakeReceiptVision())->extract($receipt)->method);
    }
}
