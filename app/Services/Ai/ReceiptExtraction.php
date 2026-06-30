<?php

namespace App\Services\Ai;

/**
 * What the vision model read off a receipt. This is *data only* — it carries
 * no verdict. ReceiptRuleEngine turns it into a decision.
 */
class ReceiptExtraction
{
    public function __construct(
        public readonly bool $isReceipt,
        public readonly ?int $amountCents,
        public readonly ?string $currency,
        public readonly ?string $date,        // ISO YYYY-MM-DD
        public readonly ?string $method,
        public readonly ?string $recipient,
        public readonly float $confidence,
        public readonly string $explanation,
        public readonly ?string $operation = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Build from the model's JSON object (see docs/06 extraction contract).
     */
    public static function fromModelJson(array $json): self
    {
        $amount = $json['amount']['value'] ?? null;

        return new self(
            isReceipt: (bool) ($json['is_payment_receipt'] ?? false),
            amountCents: $amount !== null ? (int) round(((float) $amount) * 100) : null,
            currency: $json['amount']['currency'] ?? null,
            date: $json['payment_date']['value'] ?? null,
            method: $json['payment_method']['value'] ?? null,
            recipient: $json['recipient']['name'] ?? null,
            confidence: (float) ($json['overall_confidence'] ?? 0.0),
            explanation: (string) ($json['explanation'] ?? ''),
            operation: $json['operation_number'] ?? $json['operation'] ?? null,
            raw: $json,
        );
    }
}
