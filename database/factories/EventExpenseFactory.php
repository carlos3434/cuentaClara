<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventExpense>
 */
class EventExpenseFactory extends Factory
{
    protected $model = EventExpense::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            's3_key' => 'events/1/expenses/'.$this->faker->uuid().'.jpg',
            'original_filename' => 'gasto.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(50_000, 2_000_000),
            'note' => null,
        ];
    }
}
