<?php

namespace Tests\Unit;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('shareCases')]
    public function test_share_for_uses_floor_division(int $total, int $headcount, int $expected): void
    {
        $this->assertSame($expected, Event::shareFor($total, $headcount));
    }

    public static function shareCases(): array
    {
        return [
            'even split' => [48000, 12, 4000],
            'remainder floors down' => [10000, 3, 3333],
            'single participant' => [5000, 1, 5000],
            'zero headcount is safe' => [5000, 0, 0],
        ];
    }

    public function test_generate_slug_is_short_lowercase_and_unique(): void
    {
        $slugs = collect(range(1, 50))->map(fn () => Event::generateSlug());

        $this->assertCount(50, $slugs->unique(), 'slugs should be unique');
        $slugs->each(function (string $slug) {
            $this->assertSame(10, strlen($slug));
            $this->assertSame(strtolower($slug), $slug);
            $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $slug);
        });
    }

    public function test_route_key_is_the_slug(): void
    {
        $this->assertSame('slug', (new Event())->getRouteKeyName());
    }

    public function test_casts_money_dates_and_methods(): void
    {
        $event = Event::factory()->create([
            'total_cents' => 48000,
            'accepted_methods' => ['yape', 'plin'],
        ]);
        $event->refresh();

        $this->assertIsInt($event->total_cents);
        $this->assertSame(['yape', 'plin'], $event->accepted_methods);
        $this->assertInstanceOf(Carbon::class, $event->event_date);
        $this->assertInstanceOf(Carbon::class, $event->pay_deadline);
    }
}
