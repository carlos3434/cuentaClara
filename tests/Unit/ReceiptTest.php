<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_ai_fields(): void
    {
        $receipt = Receipt::factory()->create([
            'extracted_amount_cents' => 4000,
            'extracted_date' => '2026-06-24',
            'confidence' => 0.72,
            'ai_raw' => ['driver' => 'fake', 'n' => 1],
            'decided_at' => now(),
        ]);
        $receipt->refresh();

        $this->assertIsInt($receipt->extracted_amount_cents);
        $this->assertInstanceOf(Carbon::class, $receipt->extracted_date);
        $this->assertIsFloat($receipt->confidence);
        $this->assertSame(['driver' => 'fake', 'n' => 1], $receipt->ai_raw);
        $this->assertInstanceOf(Carbon::class, $receipt->decided_at);
    }

    public function test_belongs_to_event_and_participant(): void
    {
        $event = Event::factory()->create();
        $participant = Participant::factory()->for($event)->create();
        $receipt = Receipt::factory()->for($event)->for($participant)->create();

        $this->assertTrue($receipt->event->is($event));
        $this->assertTrue($receipt->participant->is($participant));
    }
}
