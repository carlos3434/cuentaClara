<?php

namespace Tests\Unit;

use App\Models\Receipt;
use App\Services\Ai\AnthropicReceiptVision;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AnthropicReceiptVisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cuentaclara.ai.anthropic', [
            'api_key' => 'test-key',
            'model' => 'claude-opus-4-8',
            'version' => '2023-06-01',
            'base_url' => 'https://api.anthropic.com',
        ]);
        Storage::fake(config('cuentaclara.receipts_disk'));
    }

    private function receipt(): Receipt
    {
        Storage::disk(config('cuentaclara.receipts_disk'))->put('events/1/receipts/v.jpg', 'IMG');

        return new Receipt(['s3_key' => 'events/1/receipts/v.jpg', 'mime_type' => 'image/jpeg']);
    }

    private function fakeModelText(string $text): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => $text]]], 200),
        ]);
    }

    public function test_sends_a_vision_request_and_parses_the_json_response(): void
    {
        $this->fakeModelText('Aquí tienes: {"is_payment_receipt":true,"amount":{"value":40,"currency":"PEN"},'
            .'"payment_date":{"value":"2026-06-24"},"recipient":{"name":"Caro"},'
            .'"payment_method":{"value":"yape"},"overall_confidence":0.91,"explanation":"ok"} listo.');

        $x = (new AnthropicReceiptVision())->extract($this->receipt());

        $this->assertTrue($x->isReceipt);
        $this->assertSame(4000, $x->amountCents);
        $this->assertSame('Caro', $x->recipient);
        $this->assertSame(0.91, $x->confidence);

        Http::assertSent(function ($request) {
            $content = $request['messages'][0]['content'];

            return str_contains($request->url(), '/v1/messages')
                && $request['model'] === 'claude-opus-4-8'
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && collect($content)->firstWhere('type', 'image') !== null;
        });
    }

    public function test_throws_when_api_key_is_missing(): void
    {
        config()->set('cuentaclara.ai.anthropic.api_key', '');

        $this->expectException(RuntimeException::class);
        (new AnthropicReceiptVision())->extract($this->receipt());
    }

    public function test_throws_when_image_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        // s3_key points nowhere (nothing put on the fake disk).
        (new AnthropicReceiptVision())->extract(new Receipt(['s3_key' => 'missing.jpg']));
    }

    public function test_throws_when_response_has_no_json(): void
    {
        $this->fakeModelText('Lo siento, no pude leer el comprobante.');

        $this->expectException(RuntimeException::class);
        (new AnthropicReceiptVision())->extract($this->receipt());
    }
}
