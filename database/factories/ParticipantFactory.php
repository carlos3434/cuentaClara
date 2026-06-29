<?php

namespace Database\Factories;

use App\Enums\ParticipantStatus;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->firstName(),
            'session_token' => Str::random(48),
            'status' => ParticipantStatus::Pending,
        ];
    }
}
