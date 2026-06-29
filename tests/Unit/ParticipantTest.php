<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_is_none_without_receipts(): void
    {
        $this->assertSame('none', Participant::factory()->create()->badge());
    }

    /**
     * The badge reflects the participant's LATEST receipt.
     */
    public function test_badge_maps_latest_receipt_status(): void
    {
        $this->assertSame('pending', $this->participantWithReceipts(['submitted'])->badge());
        $this->assertSame('pending', $this->participantWithReceipts(['needs_review'])->badge());
        $this->assertSame('confirmed', $this->participantWithReceipts(['validated'])->badge());
        $this->assertSame('confirmed', $this->participantWithReceipts(['cash'])->badge());
        $this->assertSame('review', $this->participantWithReceipts(['rejected'])->badge());
    }

    public function test_badge_follows_the_most_recent_receipt_when_multiple_exist(): void
    {
        // validated first, then rejected on re-review → latest wins.
        $this->assertSame('review', $this->participantWithReceipts(['validated', 'rejected'])->badge());
        // submitted, then validated → confirmed.
        $this->assertSame('confirmed', $this->participantWithReceipts(['submitted', 'validated'])->badge());
    }

    public function test_session_token_is_hidden_from_array(): void
    {
        $participant = Participant::factory()->create();

        $this->assertArrayNotHasKey('session_token', $participant->toArray());
        $this->assertNotEmpty($participant->session_token); // still accessible internally
    }

    /**
     * @param  list<string>  $statuses  receipts created in order (oldest first)
     */
    private function participantWithReceipts(array $statuses): Participant
    {
        $event = Event::factory()->create();
        $participant = Participant::factory()->for($event)->create();

        foreach ($statuses as $status) {
            Receipt::factory()->for($event)->for($participant)->create(['status' => $status]);
        }

        return $participant->fresh();
    }
}
