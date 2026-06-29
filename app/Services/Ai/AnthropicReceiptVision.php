<?php

namespace App\Services\Ai;

use App\Models\Receipt;
use App\Services\Ai\Contracts\ReceiptVision;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real receipt reader backed by Claude vision (Messages API).
 *
 * Uses Laravel's HTTP client against the Messages API rather than the PHP SDK
 * so the wire contract is explicit (this path runs only when AI_DRIVER=anthropic
 * and a key is configured; it is not exercised by the test suite). The model
 * only *extracts* — the verdict is decided by ReceiptRuleEngine. See docs/06.
 */
class AnthropicReceiptVision implements ReceiptVision
{
    private const SYSTEM = <<<'TXT'
        Eres un lector de comprobantes de pago peruanos (Yape, Plin, transferencias).
        Extrae SOLO lo que se ve en la imagen. Devuelve EXCLUSIVAMENTE un objeto JSON
        con esta forma, sin texto adicional:
        {
          "is_payment_receipt": bool,
          "amount": { "value": number|null, "currency": "PEN", "confidence": number },
          "payment_date": { "value": "YYYY-MM-DD"|null, "confidence": number },
          "recipient": { "name": string|null, "confidence": number },
          "payment_method": { "value": "yape"|"plin"|"bank_transfer"|"cash"|"other"|null, "confidence": number },
          "overall_confidence": number,
          "explanation": string
        }
        No inventes valores; usa null y confianza baja cuando no sea legible.
        TXT;

    public function extract(Receipt $receipt): ReceiptExtraction
    {
        $config = config('cuentaclara.ai.anthropic');

        if (empty($config['api_key'])) {
            throw new RuntimeException('Anthropic API key not configured.');
        }

        $image = Storage::disk(config('cuentaclara.receipts_disk'))->get($receipt->s3_key);
        if ($image === null) {
            throw new RuntimeException("Receipt image not found: {$receipt->s3_key}");
        }

        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'anthropic-version' => $config['version'],
            'content-type' => 'application/json',
        ])->timeout(60)->post(rtrim($config['base_url'], '/').'/v1/messages', [
            'model' => $config['model'],
            'max_tokens' => 1024,
            'system' => self::SYSTEM,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $receipt->mime_type ?: 'image/jpeg',
                            'data' => base64_encode($image),
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Lee este comprobante y devuelve el JSON solicitado.',
                    ],
                ],
            ]],
        ])->throw();

        $text = collect($response->json('content', []))
            ->firstWhere('type', 'text')['text'] ?? '';

        $json = $this->parseJson($text);

        return ReceiptExtraction::fromModelJson($json);
    }

    /**
     * Pull the first JSON object out of the model's text response.
     */
    private function parseJson(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('Model response did not contain JSON.');
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Model response JSON was invalid.');
        }

        return $decoded;
    }
}
