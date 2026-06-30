<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\Ai\ReceiptTextParser;
use Tests\TestCase;

/**
 * The parser is pure (no I/O), so it is tested directly with OCR text
 * transcribed from the real voucher templates in resources/payments-examples.
 */
class ReceiptTextParserTest extends TestCase
{
    private function parser(): ReceiptTextParser
    {
        return new ReceiptTextParser();
    }

    public function test_yape(): void
    {
        $text = <<<'TXT'
        7:09
        ¡Yapeaste! Compartir
        S/ 7.70
        Diego Alejandro Villanueva
        28 jun. 2026 | 07:09 p. m.
        DATOS DE LA TRANSACCIÓN
        Nro. de celular *** *** 197
        Destino Plin
        Nro. de operación 2287273
        TXT;

        $x = $this->parser()->parse($text);

        $this->assertTrue($x->isReceipt);
        $this->assertSame(770, $x->amountCents);
        $this->assertSame('2026-06-28', $x->date);
        $this->assertSame('yape', $x->method);
        $this->assertSame('Diego Alejandro Villanueva', $x->recipient);
        $this->assertSame('2287273', $x->operation);
    }

    public function test_plin_bbva_ignores_the_commission_amount(): void
    {
        $text = <<<'TXT'
        Envío a contactos
        Operación exitosa
        28 junio 2026, 19:22
        Importe enviado
        S/ 7.70
        Entidad de destino Plin
        Comisión S/ 0.00
        Número de operación 624C83DB0076
        Tipo de operación Envío a contactos
        Contacto Diego alejandro villanueva •8197
        Cuenta de origen Cuenta sueldo 00110057000292370025
        TXT;

        $x = $this->parser()->parse($text);

        $this->assertSame(770, $x->amountCents); // not the S/ 0.00 commission
        $this->assertSame('2026-06-28', $x->date);
        $this->assertSame('plin', $x->method);
        $this->assertSame('Diego alejandro villanueva', $x->recipient);
        $this->assertSame('624C83DB0076', $x->operation);
    }

    public function test_plin_interbank_value_on_the_next_line(): void
    {
        $text = <<<'TXT'
        Interbank
        plin
        ¡Pago exitoso!
        S/ 16.00
        Enviado a:
        KAROL TOLEDO
        920 757 062 - Plin
        Comisión:
        GRATIS
        Fecha y hora:
        27 Jun 2026   07:48 PM
        Código de operación:
        01353140
        TXT;

        $x = $this->parser()->parse($text);

        $this->assertSame(1600, $x->amountCents);
        $this->assertSame('2026-06-27', $x->date);
        $this->assertSame('plin', $x->method);
        $this->assertSame('KAROL TOLEDO', $x->recipient);
        $this->assertSame('01353140', $x->operation);
    }

    public function test_plin_scotiabank_without_a_year_defaults_to_event_year(): void
    {
        $text = <<<'TXT'
        plin
        Pagaste con Plin
        Karol Tol***
        S/ 16.00
        Fecha y hora    28 jun., 12:11 p. m.
        Origen    Cuenta Sueldo
        *** ***6859
        Destino    *** *** 062
        Plin
        Comisión    Gratis
        N° de operación    784.444.018.0481
        TXT;

        $event = new Event(['pay_deadline' => '2026-07-15']);
        $x = $this->parser()->parse($text, $event);

        $this->assertSame(1600, $x->amountCents);
        $this->assertSame('2026-06-28', $x->date); // year filled from the event
        $this->assertSame('plin', $x->method);
        $this->assertSame('Karol Tol', $x->recipient);
        $this->assertSame('784.444.018.0481', $x->operation);
    }

    public function test_bcp_transfer(): void
    {
        $text = <<<'TXT'
        BCP
        ¡Operación exitosa!
        S/ 16.00
        Martes 16 Junio 2026 - 04:08 pm.
        Enviado a
        KAROL JOHANA TOLEDO PERALES
        *** **7 062
        PLIN
        Comisión    Gratis
        Desde    Cuenta Pagos
        **** 5037
        Número de operación    04056425
        TXT;

        $x = $this->parser()->parse($text);

        $this->assertSame(1600, $x->amountCents);
        $this->assertSame('2026-06-16', $x->date);
        $this->assertSame('KAROL JOHANA TOLEDO PERALES', $x->recipient);
        $this->assertSame('04056425', $x->operation);
    }

    public function test_garbage_is_not_a_receipt(): void
    {
        $x = $this->parser()->parse("una foto cualquiera\nsin datos de pago");

        $this->assertFalse($x->isReceipt);
        $this->assertNull($x->amountCents);
        $this->assertLessThan(0.5, $x->confidence);
    }
}
