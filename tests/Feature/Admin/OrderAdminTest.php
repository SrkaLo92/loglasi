<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\OrderFulfillment;
use App\Services\Payments\BuyerDetails;
use App\Support\Payments\PaymentMode;
use App\Support\Settings;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\Feature\Payments\PaymentsTestCase;

/** The orders screen drives the payments module (OrderFulfillment, OrderService). */
class OrderAdminTest extends PaymentsTestCase
{
    use ActsAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        // Real mode: receipt numbers come from the admin (manual fiscalisation).
        Settings::set(PaymentMode::TEST_MODE, false);
    }

    private function featuredOrder(string $code = 'featured_7'): Order
    {
        $seller = $this->seller();

        return $this->order($seller, $code, $this->listingOf($seller, ['featured_until' => null]), $this->firmBuyer());
    }

    public function test_mark_paid_from_panel_records_receipt_and_activates(): void
    {
        $order = $this->featuredOrder();
        $this->signIn($admin = $this->admin());

        Livewire::test(ListOrders::class)
            ->callTableAction('markPaid', $order, ['paid_at' => now()->toDateString(), 'receipt_number' => 'F-2026-17', 'note' => 'Uplata 24. 10.'])
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Activated, $order->status);
        $this->assertSame('F-2026-17', $order->receipt_number);
        $this->assertSame($admin->id, $order->marked_paid_by);
        $this->assertStringContainsString('Uplata 24. 10.', (string) $order->note);
        $this->assertTrue($order->listing->refresh()->featured_until->isFuture());
        $this->assertSame($admin->id, AuditLog::where('action', 'order.paid')->where('subject_id', $order->id)->value('user_id'));
    }

    public function test_paid_date_cannot_be_in_the_future(): void
    {
        $order = $this->featuredOrder();
        $this->signIn($this->admin());

        Livewire::test(ListOrders::class)
            ->callTableAction('markPaid', $order, ['paid_at' => now()->addDays(2)->toDateString()])
            ->assertHasTableActionErrors(['paid_at']);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_receipt_number_can_be_entered_later(): void
    {
        $order = $this->featuredOrder();
        app(OrderFulfillment::class)->markPaid($order, $this->admin());
        $this->assertNull($order->refresh()->receipt_number);
        $this->signIn($this->admin());

        Livewire::withQueryParams(['tab' => 'all'])->test(ListOrders::class)
            ->callTableAction('recordReceipt', $order, ['receipt_number' => 'F-2026-18'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('F-2026-18', $order->refresh()->receipt_number);
    }

    public function test_cancel_then_late_payment_is_still_accepted(): void
    {
        $order = $this->featuredOrder();
        $this->signIn($this->admin());

        Livewire::test(ListOrders::class)->callTableAction('cancel', $order, ['note' => 'Nije uplaćeno.']);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.cancelled_by_admin', 'subject_id' => $order->id]);

        Livewire::withQueryParams(['tab' => 'all'])->test(ListOrders::class)
            ->assertTableActionHidden('cancel', $order)
            ->callTableAction('markPaid', $order, ['paid_at' => now()->toDateString()]);

        $this->assertSame(OrderStatus::Activated, $order->refresh()->status);
    }

    public function test_refund_defaults_to_the_pro_rata_amount(): void
    {
        $order = $this->pay($this->featuredOrder('top_7'));
        $this->travel(2)->days();
        $expected = app(OrderFulfillment::class)->refundableAmount($order->fresh());
        $this->assertGreaterThan(0, $expected);
        $this->assertLessThan($order->amount_rsd, $expected);
        $this->signIn($this->admin());

        Livewire::withQueryParams(['tab' => 'all'])->test(ListOrders::class)
            ->mountTableAction('refund', $order)
            ->assertTableActionDataSet(['amount_rsd' => $expected])
            ->setTableActionData(['reason' => 'Kupac je prodao auto.', 'refund_receipt_number' => 'KO-1'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame($expected, (int) $order->refund_amount_rsd);
        $this->assertSame('KO-1', $order->refund_receipt_number);
        $this->assertStringContainsString('Kupac je prodao auto.', (string) $order->note);
    }

    public function test_refund_needs_a_reason(): void
    {
        $order = $this->pay($this->featuredOrder());
        $this->signIn($this->admin());

        Livewire::withQueryParams(['tab' => 'all'])->test(ListOrders::class)
            ->callTableAction('refund', $order, ['reason' => '', 'amount_rsd' => 10])
            ->assertHasTableActionErrors(['reason']);

        $this->assertSame(OrderStatus::Activated, $order->refresh()->status);
    }

    public function test_consumer_consent_time_is_shown_in_the_orders_list(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(14, 5));
        Settings::set(PaymentMode::PRIVATE_PAYMENTS, true);
        $seller = $this->seller();
        $order = $this->order($seller, 'featured_7', $this->listingOf($seller), BuyerDetails::private('198.51.100.4'));
        $this->signIn($this->admin());

        Livewire::test(ListOrders::class)
            ->assertTableColumnVisible('consent_at')
            ->assertTableColumnFormattedStateSet('consent_at', '3. 11. 2026. 14:05', $order)
            ->assertSee('IP 198.51.100.4');
    }

    public function test_test_mode_banner(): void
    {
        $this->signIn($this->admin());
        $this->get('/admin/orders')->assertOk()->assertDontSee('TEST REŽIM');

        Settings::set(PaymentMode::TEST_MODE, true);
        $this->get('/admin/orders')->assertOk()->assertSee('TEST REŽIM');
    }

    public function test_moderator_has_no_access_to_orders(): void
    {
        $this->signIn($this->moderator());

        $this->get('/admin/orders')->assertForbidden();
    }
}
