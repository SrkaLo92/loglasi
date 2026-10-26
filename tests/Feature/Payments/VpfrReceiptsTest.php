<?php

namespace Tests\Feature\Payments;

use App\Enums\ReceiptType;
use App\Models\User;
use App\Services\OrderFulfillment;
use App\Services\Payments\PaymentConfirmation;
use App\Support\Settings;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Fiscal receipts through the V-PFR (driver vpfr), the retry command and the firm invoice page. */
class VpfrReceiptsTest extends PaymentsTestCase
{
    private const URL = 'https://vpfr.example.test';

    protected function setUp(): void
    {
        parent::setUp();
        Settings::set('payments.test_mode', false);
        config([
            'fiscal.driver' => 'vpfr',
            'fiscal.vpfr.url' => self::URL,
            'fiscal.vpfr.pac' => 'PAC123',
        ]);
    }

    private function fulfillment(): OrderFulfillment
    {
        return app(OrderFulfillment::class);
    }

    private function fakeProcessor(): void
    {
        Http::fake([
            self::URL.'/api/v3/invoices' => fn (Request $request) => Http::response([
                'invoiceNumber' => ($request['transactionType'] === 'Refund' ? 'REF-' : 'ABCD1234-ABCD1234-').$request->header('RequestId')[0],
                'sdcDateTime' => '2026-10-25T10:15:00.000+02:00',
                'verificationUrl' => 'https://suf.purs.gov.rs/v/?vl=test'.$request['transactionType'],
                'totalAmount' => $request['payment'][0]['amount'],
            ]),
            self::URL.'/api/v3/status' => Http::response(['sdcDateTime' => '2026-10-25T10:00:00', 'uid' => 'ABCD1234', 'gsc' => [],
                'currentTaxRates' => ['taxCategories' => [['name' => 'Bez PDV', 'taxRates' => [['label' => 'А', 'rate' => 0]]]]]]),
        ]);
    }

    public function test_private_sale_is_fiscalised_with_wire_transfer(): void
    {
        $this->fakeProcessor();
        $order = $this->order($this->seller(), 'listing_slots_3');

        $this->fulfillment()->markPaid($order);
        $order->refresh();

        $this->assertSame(ReceiptType::FiscalSale, $order->receipt_type);
        $this->assertSame('ABCD1234-ABCD1234-loglasi-'.$order->id.'-sale', $order->receipt_number);
        $this->assertSame('https://suf.purs.gov.rs/v/?vl=testSale', $order->receipt_url);
        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/api/v3/invoices'
            && $r->hasHeader('PAC', 'PAC123')
            && $r->hasHeader('RequestId', 'loglasi-'.$order->id.'-sale')
            && $r['invoiceType'] === 'Normal' && $r['transactionType'] === 'Sale'
            && $r['payment'] === [['amount' => (int) $order->amount_rsd, 'paymentType' => 'WireTransfer']]
            && $r['items'][0]['labels'] === ['А'] && $r['items'][0]['totalAmount'] === (int) $order->amount_rsd);

        $this->actingAs($order->user)->get(url_slash('moj-nalog/narudzbine/'.$order->id))
            ->assertOk()->assertSee('Proveri fiskalni račun')->assertSee('https://suf.purs.gov.rs/v/?vl=testSale', false);
    }

    public function test_refund_references_the_sale_receipt_and_uses_the_refunded_amount(): void
    {
        $this->fakeProcessor();
        $order = $this->order($this->seller(), 'listing_slots_3');
        $this->fulfillment()->markPaid($order);
        $sale = $order->fresh()->receipt_number;

        $this->fulfillment()->refund($order->fresh(), null, 'Greška', null, 50, '20:123456789');
        $order->refresh();

        $this->assertSame('REF-loglasi-'.$order->id.'-refund', $order->refund_receipt_number);
        $this->assertSame('https://suf.purs.gov.rs/v/?vl=testRefund', $order->refund_receipt_url);
        Http::assertSent(fn (Request $r) => $r['transactionType'] === 'Refund'
            && $r['buyerId'] === '20:123456789'
            && $r['referentDocumentNumber'] === $sale
            && is_string($r['referentDocumentDT'])
            && $r['payment'][0]['amount'] === 50);
    }

    public function test_refund_without_buyer_id_is_not_fiscalised(): void
    {
        $this->fakeProcessor();
        $order = $this->order($this->seller(), 'listing_slots_3');
        $this->fulfillment()->markPaid($order);

        $this->fulfillment()->refund($order->fresh(), null, 'Greška');

        $this->assertNull($order->fresh()->refund_receipt_number);
        Http::assertSentCount(1); // only the sale
    }

    public function test_processor_down_leaves_the_order_paid_and_the_command_issues_it_later(): void
    {
        Http::fake([self::URL.'/*' => Http::response(['message' => 'down'], 503)]);
        $order = $this->order($this->seller(), 'listing_slots_3');

        $this->fulfillment()->markPaid($order);
        $this->assertTrue($order->fresh()->status->isPaid());
        $this->assertNull($order->fresh()->receipt_number);

        Http::swap(new Factory);
        $this->fakeProcessor();
        $this->artisan('fiscal:issue')->assertSuccessful();

        $this->assertSame('ABCD1234-ABCD1234-loglasi-'.$order->id.'-sale', $order->fresh()->receipt_number);
    }

    public function test_a_typed_receipt_number_is_recorded_without_calling_the_processor(): void
    {
        Http::fake();
        $order = $this->order($this->seller(), 'listing_slots_3');

        $this->fulfillment()->markPaid($order, null, new PaymentConfirmation(receiptNumber: 'RUCNO-7'));

        $this->assertSame('RUCNO-7', $order->fresh()->receipt_number);
        Http::assertNothingSent();
    }

    public function test_manual_driver_never_calls_the_processor(): void
    {
        config(['fiscal.driver' => 'manual']);
        Http::fake();
        $order = $this->order($this->seller(), 'listing_slots_3');

        $this->fulfillment()->markPaid($order);
        $this->artisan('fiscal:issue')->assertSuccessful();

        $this->assertNull($order->fresh()->receipt_number);
        Http::assertNothingSent();
    }

    public function test_status_command_reports_the_processor(): void
    {
        $this->fakeProcessor();

        $this->artisan('fiscal:check')->expectsOutput('V-PFR odgovara.')->expectsOutput('UID: ABCD1234')->expectsOutput('Poreska oznaka А: Bez PDV 0%')->assertSuccessful();
    }

    public function test_firm_invoice_page_is_printable_and_only_for_the_owner(): void
    {
        Http::fake();
        $owner = $this->firmUser();
        $order = $this->order($owner, 'business_start_monthly', null, $this->firmBuyer());
        $this->fulfillment()->markPaid($order);
        $number = $order->fresh()->receipt_number;
        $this->assertSame('1/'.now()->format('Y'), $number);
        Http::assertNothingSent();

        $this->actingAs($owner)->get(url_slash('moj-nalog/narudzbine/'.$order->id.'/racun'))
            ->assertOk()->assertSee('Račun br. '.$number)->assertSee('Test DOO')->assertSee('100002887')
            ->assertSee('Obveznik nije u sistemu PDV-a.');
        $this->actingAs($owner)->get(url_slash('moj-nalog/narudzbine/'.$order->id))->assertSee('Preuzmi račun');
        $this->actingAs(User::factory()->create())->get(url_slash('moj-nalog/narudzbine/'.$order->id.'/racun'))->assertForbidden();
        $this->actingAs($owner)->get(url_slash('moj-nalog/narudzbine/'.$order->id.'/odobrenje'))->assertNotFound();

        $private = $this->order($this->seller(), 'listing_slots_3');
        $this->actingAs($private->user)->get(url_slash('moj-nalog/narudzbine/'.$private->id.'/racun'))->assertNotFound();
    }
}
