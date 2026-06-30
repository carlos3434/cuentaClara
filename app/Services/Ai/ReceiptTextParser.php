<?php

namespace App\Services\Ai;

use App\Models\Event;

/**
 * Turns raw OCR text from a Peruvian payment voucher (Yape, Plin via
 * BBVA/Interbank/Scotiabank, BCP transfer) into a structured extraction.
 *
 * Pure and deterministic — no I/O — so it is fully unit-tested against real
 * voucher layouts. The OCR itself lives in TesseractReceiptVision; this class
 * only parses the text it produces. Everything here is an *assist* for the
 * organizer; the money decision stays with the ReceiptRuleEngine / a human.
 */
class ReceiptTextParser
{
    /** Spanish month tokens (first 3 letters) → month number. */
    private const MONTHS = [
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'set' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
    ];

    private const SUCCESS_MARKERS = [
        'yapeaste', 'pago exitoso', 'operacion exitosa', 'operación exitosa',
        'pagaste con plin', 'importe enviado', 'enviado a', 'de operacion',
        'de operación', 'envio a contactos', 'envío a contactos',
    ];

    public function parse(string $text, ?Event $event = null): ReceiptExtraction
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
        $lower = mb_strtolower($text);

        $isReceipt = $this->looksLikeReceipt($lower);
        $amountCents = $this->amountCents($text);
        $date = $this->date($text, $event);
        $method = $this->method($lower);
        $recipient = $this->recipient($lines);
        $operation = $this->operation($text);

        $found = array_filter([$amountCents, $date, $method, $recipient, $operation], fn ($v) => $v !== null);
        $confidence = $isReceipt ? min(0.95, 0.5 + 0.1 * count($found)) : 0.2;

        return new ReceiptExtraction(
            isReceipt: $isReceipt,
            amountCents: $amountCents,
            currency: 'PEN',
            date: $date,
            method: $method,
            recipient: $recipient,
            confidence: round($confidence, 2),
            explanation: '[ocr] '.($isReceipt ? 'Comprobante leído por OCR.' : 'No se reconoció como comprobante.'),
            operation: $operation,
            raw: ['driver' => 'ocr', 'text' => $text],
        );
    }

    private function looksLikeReceipt(string $lower): bool
    {
        foreach (self::SUCCESS_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        // A bare "S/" amount is still a decent signal.
        return (bool) preg_match('/s\/\s*\d/i', $lower);
    }

    /**
     * The payment amount in cents. Ignores S/ 0.00 (commission) and keeps the
     * largest remaining amount — the importe is always the prominent one.
     */
    private function amountCents(string $text): ?int
    {
        // "S/" is often misread by OCR as "5/", "$/" or "8/".
        if (! preg_match_all('/[S5$8]\s*\/\s*([0-9][0-9.,]*)/i', $text, $m)) {
            return null;
        }

        $best = null;
        foreach ($m[1] as $raw) {
            $cents = $this->toCents($raw);
            if ($cents === null || $cents === 0) {
                continue; // skip unparsable and zero (commission)
            }
            if ($best === null || $cents > $best) {
                $best = $cents;
            }
        }

        return $best;
    }

    private function toCents(string $raw): ?int
    {
        $raw = trim($raw, " \t.,");
        if ($raw === '') {
            return null;
        }

        // Drop thousands separators (commas), keep the last dot as decimal.
        $normalized = str_replace(',', '', $raw);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (int) round(((float) $normalized) * 100);
    }

    /**
     * First "DD <month> [YYYY]" found, in any of the bank layouts. The year is
     * optional (Scotiabank omits it) and defaults to the event's year / today.
     */
    private function date(string $text, ?Event $event): ?string
    {
        $pattern = '/(?<![\d.,])(\d{1,2})\s*(?:de\s+)?'
            .'(ene(?:ro)?|feb(?:rero)?|mar(?:zo)?|abr(?:il)?|may(?:o)?|jun(?:io)?|jul(?:io)?'
            .'|ago(?:sto)?|sep(?:tiembre)?|set(?:iembre)?|oct(?:ubre)?|nov(?:iembre)?|dic(?:iembre)?)'
            // not followed by another letter (rejects "Martes"), but a digit is
            // fine — OCR often drops the space, e.g. "Jun2026".
            .'(?![a-zñáéíóúü])\.?,?\s*(?:de\s+)?(\d{4})?/iu';

        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $day = (int) $m[1];
        $month = self::MONTHS[mb_strtolower(mb_substr($m[2], 0, 3))] ?? null;
        if ($month === null || $day < 1 || $day > 31) {
            return null;
        }

        $year = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : $this->defaultYear($event);

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function defaultYear(?Event $event): int
    {
        if ($event && $event->pay_deadline) {
            return (int) $event->pay_deadline->format('Y');
        }

        return (int) date('Y');
    }

    private function method(string $lower): ?string
    {
        if (str_contains($lower, 'yapeaste') || str_contains($lower, 'yape')) {
            return 'yape';
        }
        if (str_contains($lower, 'plin')) {
            return 'plin';
        }
        if (preg_match('/\b(bcp|interbank|bbva|scotiabank|transferencia|cuenta)\b/', $lower)) {
            return 'bank_transfer';
        }

        return null;
    }

    /**
     * Best-effort recipient name. Label-driven first (Enviado a / Contacto /
     * Pagaste con Plin), then the line right after the amount (Yape layout).
     */
    private function recipient(array $lines): ?string
    {
        foreach ($lines as $i => $line) {
            $l = mb_strtolower($line);

            // "Enviado a:" → name on the same or next line.
            if (str_starts_with($l, 'enviado a')) {
                $inline = trim(preg_replace('/^enviado a:?/i', '', $line));

                return $this->cleanName($inline !== '' ? $inline : ($lines[$i + 1] ?? ''));
            }

            // "Contacto  Diego ... •8197" (value inline or on the next line)
            if (str_starts_with($l, 'contacto')) {
                $inline = trim(preg_replace('/^contacto:?/i', '', $line));

                return $this->cleanName($inline !== '' ? $inline : ($lines[$i + 1] ?? ''));
            }

            // "Pagaste con Plin" → name on the next line.
            if (str_contains($l, 'pagaste con plin')) {
                return $this->cleanName($lines[$i + 1] ?? '');
            }

            // Yape: the line right after the "S/ 7.70" amount is the name.
            if (preg_match('/^s\/\s*[0-9]/i', $line)) {
                $candidate = $lines[$i + 1] ?? '';
                if ($this->looksLikeName($candidate)) {
                    return $this->cleanName($candidate);
                }
            }
        }

        return null;
    }

    private function looksLikeName(string $s): bool
    {
        $s = trim($s);

        // Mostly letters/spaces, at least two characters, not a known label.
        return $s !== ''
            && (bool) preg_match('/^[\p{L}*][\p{L} .*]+$/u', $s)
            && ! preg_match('/datos|transacci|importe|destino|comisi/iu', $s);
    }

    private function cleanName(string $s): ?string
    {
        // Drop trailing account/phone fragments: "•8197", "- Plin", "*** 062".
        $s = preg_replace('/[•·]\s*\d+.*$/u', '', $s);
        $s = preg_replace('/\s*[-–]\s*plin.*$/iu', '', $s);
        $s = preg_replace('/\*+\s*\d*.*$/u', '', $s);
        $s = trim($s, " \t-–•·");

        return $s !== '' ? $s : null;
    }

    /**
     * Operation / constancia number under any of the label variants.
     */
    private function operation(string $text): ?string
    {
        // Label-driven, value on the same or the next line. The "°" in "N°" is
        // often OCR'd as "*". The value must contain a digit (rejects picking
        // up a following label word like "Origen").
        $label = '/(?:n(?:ro|[uú]mero)?|c[oó]digo)\.?\s*[°*]?\s*de\s+operaci[oó]n'
            .':?[ \t]*\n?[ \t]*((?=[A-Za-z0-9.\-]*\d)[A-Za-z0-9][A-Za-z0-9.\-]{3,})/iu';

        if (preg_match($label, $text, $m)) {
            return trim($m[1], '.-');
        }

        // Column layouts (Scotiabank) split the label from its value by many
        // lines; fall back to a dotted operation number like 784.444.018.0481.
        if (preg_match('/\b\d{1,4}(?:\.\d{2,4}){2,}\b/', $text, $m)) {
            return trim($m[0], '.-');
        }

        return null;
    }
}
