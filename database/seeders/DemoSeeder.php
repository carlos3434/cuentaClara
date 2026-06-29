<?php

namespace Database\Seeders;

use App\Enums\DecidedBy;
use App\Enums\EventStatus;
use App\Enums\ParticipantStatus;
use App\Enums\ReasonCode;
use App\Enums\ReceiptStatus;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\Participant;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Realistic demo data for screenshots / local exploration.
 *   php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder
 * Login: demo@cuentaclara.test / password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk(config('cuentaclara.receipts_disk'));

        $user = User::updateOrCreate(
            ['email' => 'demo@cuentaclara.test'],
            ['name' => 'Caro (demo)', 'password' => 'password'],
        );

        // Admin account to explore the platform panel.
        // Login: admin@cuentaclara.test / password
        User::updateOrCreate(
            ['email' => 'admin@cuentaclara.test'],
            ['name' => 'Admin (demo)', 'password' => 'password', 'role' => \App\Enums\UserRole::Admin],
        );

        $event = Event::create([
            'user_id' => $user->id,
            'slug' => Event::generateSlug(),
            'name' => 'BBQ Cumpleaños Caro',
            'event_date' => now()->addDays(7)->toDateString(),
            'total_cents' => 48000,
            'headcount' => 12,
            'share_cents' => 4000,
            'recipient_name' => 'Caro',
            'recipient_handle' => '999 888 777',
            'accepted_methods' => ['yape', 'plin'],
            'pay_deadline' => now()->addDays(5)->toDateString(),
            'status' => EventStatus::Active,
        ]);

        $put = function (string $folder, array $lines) use ($disk, $event): string {
            $key = "events/{$event->id}/{$folder}/".Str::random(20).'.jpg';
            $disk->put($key, $this->voucherImage($lines));

            return $key;
        };

        $this->paidParticipant($event, 'Ana Torres', $put('receipts', ['YAPE', 'S/ 40.00', 'A: Caro', '24 jun']));
        $this->paidParticipant($event, 'Beto Quispe', $put('receipts', ['PLIN', 'S/ 40.00', 'A: Caro', '25 jun']));

        // Needs review: amount doesn't match the S/ 40 share.
        $lucia = Participant::create([
            'event_id' => $event->id, 'name' => 'Lucía Ramos',
            'session_token' => Str::random(48), 'status' => ParticipantStatus::Pending,
        ]);
        Receipt::create([
            'event_id' => $event->id, 'participant_id' => $lucia->id,
            's3_key' => $put('receipts', ['YAPE', 'S/ 30.00', 'A: Caro', '24 jun']),
            'original_filename' => 'voucher.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 12345,
            'status' => ReceiptStatus::NeedsReview, 'reason_code' => ReasonCode::AmountMismatch,
            'extracted_amount_cents' => 3000, 'extracted_currency' => 'PEN',
            'extracted_date' => now()->subDay()->toDateString(), 'extracted_method' => 'yape',
            'extracted_recipient' => 'Caro', 'confidence' => 0.72,
            'ai_explanation' => 'Monto menor al esperado.', 'decided_by' => DecidedBy::Ai, 'decided_at' => now(),
        ]);

        // Hasn't paid yet.
        Participant::create([
            'event_id' => $event->id, 'name' => 'Marco Díaz',
            'session_token' => Str::random(48), 'status' => ParticipantStatus::Pending,
        ]);

        EventExpense::create([
            'event_id' => $event->id,
            's3_key' => $put('expenses', ['GASTO', 'Alquiler', 'cancha', 'S/ 480']),
            'original_filename' => 'gasto.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 12345,
            'note' => 'Alquiler de cancha',
        ]);

        $this->command?->info("DEMO_SLUG={$event->slug}");
    }

    private function paidParticipant(Event $event, string $name, string $key): void
    {
        $p = Participant::create([
            'event_id' => $event->id, 'name' => $name,
            'session_token' => Str::random(48), 'status' => ParticipantStatus::Paid,
        ]);
        Receipt::create([
            'event_id' => $event->id, 'participant_id' => $p->id, 's3_key' => $key,
            'original_filename' => 'voucher.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 12345,
            'status' => ReceiptStatus::Validated, 'extracted_amount_cents' => 4000, 'extracted_currency' => 'PEN',
            'extracted_date' => now()->subDay()->toDateString(), 'extracted_method' => 'yape',
            'extracted_recipient' => 'Caro', 'confidence' => 0.95,
            'ai_explanation' => 'Voucher reconocido.', 'decided_by' => DecidedBy::Ai, 'decided_at' => now(),
        ]);
    }

    /**
     * Generate a simple placeholder voucher JPEG (GD) so screenshots show images.
     *
     * @param  list<string>  $lines
     */
    private function voucherImage(array $lines): string
    {
        $im = imagecreatetruecolor(360, 480);
        imagefilledrectangle($im, 0, 0, 360, 480, imagecolorallocate($im, 13, 148, 136));
        $white = imagecolorallocate($im, 255, 255, 255);
        $y = 70;
        foreach ($lines as $line) {
            imagestring($im, 5, 40, $y, $line, $white);
            $y += 50;
        }
        ob_start();
        imagejpeg($im, null, 85);
        $data = ob_get_clean();
        imagedestroy($im);

        return $data;
    }
}
