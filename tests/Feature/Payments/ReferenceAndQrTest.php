<?php

namespace Tests\Feature\Payments;

use App\Support\Payments\BankAccount;
use App\Support\Payments\IpsQr;
use App\Support\Payments\Mod97;
use App\Support\Payments\PaymentDetails;
use App\Support\Payments\PaymentReference;
use App\Support\Payments\TaxId;
use App\Support\Settings;

class ReferenceAndQrTest extends PaymentsTestCase
{
    public function test_model_97_reference_is_built_from_the_order_id(): void
    {
        $this->assertSame('69000042', PaymentReference::forOrderId(42));
        $this->assertTrue(PaymentReference::isValid('69000042'));
        $this->assertFalse(PaymentReference::isValid('68000042'));
        $this->assertSame('97', PaymentReference::model());
        // ISO 7064 MOD 97-10: the number with its control digits moved to the end ≡ 1 (mod 97).
        $this->assertSame(1, Mod97::remainder('000042'.'69'));
    }

    public function test_reference_of_a_real_order_is_valid_and_unique(): void
    {
        $user = $this->seller();
        $a = $this->order($user, 'listing_slots_3');
        $b = $this->order($this->seller(), 'listing_slots_3');

        $this->assertSame(PaymentReference::forOrderId($a->id), $a->reference);
        $this->assertNotSame($a->reference, $b->reference);
        $this->assertTrue(PaymentReference::isValid($a->reference));
    }

    public function test_bank_account_is_normalized_and_checked(): void
    {
        $this->assertSame('160000000012345654', BankAccount::normalize(self::ACCOUNT));
        $this->assertSame('160000000012345654', BankAccount::normalize('160-123456-54'));
        $this->assertNull(BankAccount::normalize('160-0000000123456-55'));
        $this->assertNull(BankAccount::normalize('nije račun'));
        $this->assertSame(self::ACCOUNT, BankAccount::format('160000000012345654'));
    }

    public function test_pib_control_digit(): void
    {
        $this->assertTrue(TaxId::isValidPib('100002887'));
        $this->assertFalse(TaxId::isValidPib('100002888'));
        $this->assertFalse(TaxId::isValidPib('12345'));
        $this->assertTrue(TaxId::isValidMb('12345678'));
        $this->assertFalse(TaxId::isValidMb('1234567'));
    }

    public function test_ips_payload_follows_the_nbs_format(): void
    {
        $order = $this->order($this->seller(), 'listing_slots_10');
        $payload = PaymentDetails::forOrder($order)->ipsPayload();

        $this->assertSame(
            "K:PR|V:01|C:1|R:160000000012345654|N:PRIMALAC PR TEST\r\nKlupci, Loznica|I:RSD349,00|SF:221|S:TEST +10 oglasa|RO:97".$order->reference,
            $payload,
        );
        $this->assertStringNotContainsString('||', $payload);
        $this->assertStringEndsNotWith('|', $payload);
    }

    public function test_purpose_names_the_listing_and_drops_test_outside_test_mode(): void
    {
        Settings::set('payments.test_mode', false);
        Settings::set('payments.private_payments_enabled', true);
        $user = $this->seller();
        $listing = $this->listingOf($user);
        $order = $this->order($user, 'top_7', $listing);

        $this->assertFalse($order->is_test);
        $this->assertStringContainsString('|S:TOP oglas, oglas '.$listing->id.'|', PaymentDetails::forOrder($order)->ipsPayload());
    }

    public function test_long_values_are_cut_and_separators_removed(): void
    {
        $payload = IpsQr::payload('160000000012345654', str_repeat('A', 80)."|x\nRed 2\nRed 3\nRed 4", 6000, str_repeat('S', 50), '97', '69000042');

        preg_match('/\|N:(.*?)\|I:/s', $payload, $n);
        $this->assertLessThanOrEqual(70, mb_strlen($n[1]));
        $this->assertStringNotContainsString('Red 4', $payload);
        preg_match('/\|S:(.*?)\|RO:/', $payload, $s);
        $this->assertSame(35, mb_strlen($s[1]));
        $this->assertStringContainsString('|I:RSD6000,00|', $payload);
    }

    public function test_no_qr_without_a_valid_account(): void
    {
        $order = $this->order($this->seller(), 'listing_slots_3');
        Settings::set('payment_account', '160-0000000123456-55');

        $this->assertNull(PaymentDetails::forOrder($order)->ipsPayload());
        $this->assertTrue(PaymentDetails::forOrder($order)->ready());
    }

    public function test_svg_and_png_are_rendered_locally(): void
    {
        $payload = IpsQr::payload('160000000012345654', 'Primalac', 100, 'Test', '97', '69000042');

        $this->assertStringStartsWith('<svg', IpsQr::svg($payload));
        $this->assertStringStartsWith("\x89PNG", IpsQr::png($payload));
    }

    public function test_payment_code_setting_overrides_the_default(): void
    {
        $this->assertSame('221', PaymentDetails::paymentCode());
        Settings::set('payment_code', '289');
        $this->assertSame('289', PaymentDetails::paymentCode());
        Settings::set('payment_code', '999');
        $this->assertSame('221', PaymentDetails::paymentCode());
    }
}
