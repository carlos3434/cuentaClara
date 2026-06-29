<?php

namespace Database\Factories;

use App\Enums\ReceiptStatus;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'participant_id' => Participant::factory(),
            's3_key' => 'events/1/receipts/'.$this->faker->uuid().'.jpg',
            'original_filename' => 'voucher.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(50_000, 2_000_000),
            'status' => ReceiptStatus::Submitted,
            'note' => null,
        ];
    }
}
