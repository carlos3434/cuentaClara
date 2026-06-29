<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $totalCents = $this->faker->numberBetween(5000, 200000);
        $headcount = $this->faker->numberBetween(2, 20);

        return [
            'user_id' => null,
            'slug' => Str::lower(Str::random(10)),
            'name' => $this->faker->sentence(3),
            'event_date' => now()->addDays(7)->toDateString(),
            'total_cents' => $totalCents,
            'headcount' => $headcount,
            'share_cents' => intdiv($totalCents, $headcount),
            'recipient_name' => $this->faker->name(),
            'recipient_handle' => $this->faker->numerify('9########'),
            'accepted_methods' => ['yape', 'plin'],
            'pay_deadline' => now()->addDays(5)->toDateString(),
            'status' => 'active',
        ];
    }
}
