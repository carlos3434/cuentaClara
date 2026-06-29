<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function disk(): string
    {
        return config('cuentaclara.receipts_disk');
    }

    public function test_organizer_can_upload_an_expense_receipt(): void
    {
        Storage::fake($this->disk());
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->post("/events/{$event->slug}/expenses", [
            'image' => UploadedFile::fake()->image('cancha.jpg'),
            'note' => 'Alquiler de cancha',
        ]);

        $response->assertRedirect();

        $expense = EventExpense::firstOrFail();
        $this->assertSame($event->id, $expense->event_id);
        $this->assertSame('Alquiler de cancha', $expense->note);
        $this->assertNotNull($expense->s3_key);
        Storage::disk($this->disk())->assertExists($expense->s3_key);
    }

    public function test_expense_upload_requires_an_image(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/events/{$event->slug}/expenses", ['note' => 'no image'])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('event_expenses', 0);
    }

    public function test_non_image_files_are_rejected(): void
    {
        Storage::fake($this->disk());
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/events/{$event->slug}/expenses", [
                'image' => UploadedFile::fake()->create('cost.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('event_expenses', 0);
    }

    public function test_another_organizer_cannot_upload_to_your_event(): void
    {
        Storage::fake($this->disk());
        $event = Event::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->post("/events/{$event->slug}/expenses", [
                'image' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('event_expenses', 0);
    }

    public function test_guests_cannot_upload(): void
    {
        $event = Event::factory()->create();

        $this->post("/events/{$event->slug}/expenses", [
            'image' => UploadedFile::fake()->image('x.jpg'),
        ])->assertRedirect('/login');
    }

    public function test_owner_can_stream_expense_image_but_others_cannot(): void
    {
        Storage::fake($this->disk());
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        Storage::disk($this->disk())->put('events/1/expenses/e.jpg', 'binary');
        $expense = EventExpense::factory()->for($event)->create(['s3_key' => 'events/1/expenses/e.jpg']);

        $this->actingAs($owner)
            ->get("/events/{$event->slug}/expenses/{$expense->id}/image")
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get("/events/{$event->slug}/expenses/{$expense->id}/image")
            ->assertForbidden();
    }

    public function test_owner_can_delete_an_expense(): void
    {
        Storage::fake($this->disk());
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        Storage::disk($this->disk())->put('events/1/expenses/e.jpg', 'binary');
        $expense = EventExpense::factory()->for($event)->create(['s3_key' => 'events/1/expenses/e.jpg']);

        $this->actingAs($owner)
            ->delete("/events/{$event->slug}/expenses/{$expense->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('event_expenses', 0);
        Storage::disk($this->disk())->assertMissing('events/1/expenses/e.jpg');
    }

    public function test_review_page_lists_expenses(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventExpense::factory()->for($event)->create(['note' => 'Cancha']);

        $this->actingAs($owner)
            ->get("/events/{$event->slug}/review")
            ->assertInertia(fn (Assert $page) => $page
                ->has('expenses', 1)
                ->where('expenses.0.note', 'Cancha')
                ->has('expenses.0.image_url'));
    }
}
