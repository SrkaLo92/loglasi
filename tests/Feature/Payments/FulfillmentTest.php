<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Enums\ReceiptType;
use App\Exceptions\OrderException;
use App\Mail\OrderPaid;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use App\Services\OrderFulfillment;
use App\Services\Payments\BumpCredits;
use App\Services\Payments\PaymentConfirmation;
use App\Support\Firms\FirmPackage;
use App\Support\ListingLimits;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;

class FulfillmentTest extends PaymentsTestCase
{
    private function fulfillment(): OrderFulfillment
    {
        return app(OrderFulfillment::class);
    }

    public function test_bump_sets_bumped_at_now_and_is_idempotent(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user);
        $order = $this->pay($this->order($user, 'bump', $listing));

        $this->assertSame(OrderStatus::Activated, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertEqualsWithDelta(now()->timestamp, $listing->fresh()->bumped_at->timestamp, 5);

        $this->travel(2)->hours();
        $this->assertFalse($this->fulfillment()->markPaid($order));
        $this->assertFalse($this->fulfillment()->activate($order));
        $this->assertLessThan(now()->subHour()->timestamp, $listing->fresh()->bumped_at->timestamp);
        $this->assertSame(1, AuditLog::where('action', 'order.paid')->count());
        $this->assertSame(1, AuditLog::where('action', 'order.activated')->count());
        Mail::assertQueued(OrderPaid::class, 1);
    }

    public function test_promotions_extend_from_the_current_end(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user, ['featured_until' => now()->addDays(3)]);

        $this->pay($this->order($user, 'featured_7', $listing));
        $this->assertSame(now()->addDays(10)->toDateString(), $listing->fresh()->featured_until->toDateString());

        $this->pay($this->order($user, 'priority_3', $listing));
        $this->assertSame(now()->addDays(3)->toDateString(), $listing->fresh()->priority_until->toDateString());

        $this->pay($this->order($user, 'top_7', $listing));
        $this->assertSame(3, $listing->fresh()->promotionRank());

        $this->pay($this->order($user, 'premium_xl_home_7', $listing));
        $fresh = $listing->fresh();
        $this->assertTrue($fresh->isPremiumXl());
        $this->assertSame(now()->addDays(7)->toDateString(), $fresh->premium_home_until->toDateString());
    }

    public function test_bump_pack_adds_ten_bumps(): void
    {
        $user = $this->seller();
        $order = $this->pay($this->order($user, 'bump_10'));

        $this->assertSame(OrderStatus::Activated, $order->status);
        $this->assertSame(10, BumpCredits::remaining($user));
        $this->assertTrue(BumpCredits::consume($user));
        $this->assertSame(9, BumpCredits::remaining($user));
    }

    public function test_extra_listings_raise_the_private_limit_for_30_days(): void
    {
        $user = $this->seller();
        $base = (int) Settings::get('private_active_limit');

        $this->pay($this->order($user, 'listing_slots_10'));
        $this->assertSame($base + 10, ListingLimits::monthlyLimit($user));

        $this->pay($this->order($user, 'listing_slots_3'));
        $this->assertSame($base + 13, ListingLimits::monthlyLimit($user));

        $this->travel(31)->days();
        $this->assertSame($base, ListingLimits::monthlyLimit($user));
    }

    public function test_firm_package_uses_firm_package_grant(): void
    {
        $user = $this->firmUser();
        $order = $this->pay($this->order($user, 'business_pro_yearly', null, $this->firmBuyer()));

        $firm = $user->firm->fresh();
        $this->assertSame('pro', FirmPackage::code($firm));
        $this->assertSame('yearly', $firm->package_period);
        $this->assertSame(now()->addYear()->toDateString(), $order->service_ends_at->toDateString());
    }

    public function test_package_bought_in_the_free_year_starts_after_it(): void
    {
        $user = $this->firmUser();
        FirmPackage::grantFreeYear($user->firm);
        $freeEnds = $user->firm->fresh()->free_program_ends_at;

        $order = $this->pay($this->order($user->fresh(), 'business_start_monthly', null, $this->firmBuyer()));
        $firm = $user->firm->fresh();

        $this->assertSame(FirmPackage::FREE, FirmPackage::code($firm));
        $this->assertSame($freeEnds->toDateTimeString(), $order->service_starts_at->toDateTimeString());
        $this->assertSame($freeEnds->copy()->addMonth()->toDateTimeString(), $firm->package_ends_at->toDateTimeString());
    }

    public function test_account_becomes_a_firm_only_when_the_firm_is_approved(): void
    {
        $pending = $this->seller();
        Firm::factory()->for($pending)->create();
        $this->pay($this->order($pending, 'business_start_monthly', null, $this->firmBuyer()));
        $this->assertSame(AccountType::Private, $pending->fresh()->account_type);

        $approved = $this->seller();
        $firm = Firm::factory()->for($approved)->create();
        $firm->forceFill(['verified_at' => now()])->save();
        $this->pay($this->order($approved, 'business_start_monthly', null, $this->firmBuyer()));
        $this->assertSame(AccountType::Firm, $approved->fresh()->account_type);
    }

    public function test_deleted_listing_leaves_the_order_paid_and_logs_it(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user);
        $order = $this->order($user, 'top_7', $listing);
        $listing->delete();

        $order = $this->pay($order);

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(1, AuditLog::where('action', 'order.activation_failed')->count());
        Mail::assertNotQueued(OrderPaid::class);
    }

    public function test_cancelled_order_can_still_be_marked_paid(): void
    {
        $user = $this->seller();
        $order = $this->order($user, 'listing_slots_3');
        $order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()])->save();

        $this->assertSame(OrderStatus::Activated, $this->pay($order)->status);
        $this->assertNull($order->cancelled_at);
    }

    public function test_test_mode_issues_fake_receipts(): void
    {
        $order = $this->pay($this->order($this->seller(), 'listing_slots_3'));

        $this->assertTrue($order->is_test);
        $this->assertSame(ReceiptType::FiscalSale, $order->receipt_type);
        $this->assertSame(sprintf('TEST-%04d', $order->id), $order->receipt_number);
    }

    public function test_late_confirmation_records_an_advance_receipt_in_test_mode(): void
    {
        $order = $this->order($this->seller(), 'listing_slots_3');
        $this->fulfillment()->markPaid($order, null, new PaymentConfirmation(paidAt: now()->subDays(2)));

        $this->assertSame(sprintf('TEST-A-%04d', $order->id), $order->fresh()->advance_receipt_number);
    }

    public function test_manual_receipts_outside_test_mode(): void
    {
        Settings::set('payments.test_mode', false);
        config(['fiscal.invoice_auto_number' => false]);
        $user = $this->firmUser();
        $order = $this->order($user, 'business_start_monthly', null, $this->firmBuyer());

        $this->fulfillment()->markPaid($order, null, new PaymentConfirmation(receiptNumber: 'R-2026-15'));
        $order->refresh();
        $this->assertFalse($order->is_test);
        $this->assertSame(ReceiptType::Invoice, $order->receipt_type);
        $this->assertSame('R-2026-15', $order->receipt_number);

        $other = $this->order($this->firmUser(), 'business_start_monthly', null, $this->firmBuyer());
        $this->fulfillment()->markPaid($other);
        $this->assertNull($other->fresh()->receipt_number);
        $this->assertTrue($this->fulfillment()->recordReceipt($other, new PaymentConfirmation(receiptNumber: 'R-2026-16')));
        $this->assertSame('R-2026-16', $other->fresh()->receipt_number);
    }

    public function test_firm_invoices_are_numbered_per_year_outside_test_mode(): void
    {
        Settings::set('payments.test_mode', false);
        $first = $this->order($this->firmUser(), 'business_start_monthly', null, $this->firmBuyer());
        $second = $this->order($this->firmUser(), 'business_start_monthly', null, $this->firmBuyer());

        $this->fulfillment()->markPaid($first);
        $this->fulfillment()->markPaid($second);

        $year = now()->format('Y');
        $this->assertSame('1/'.$year, $first->fresh()->receipt_number);
        $this->assertSame('2/'.$year, $second->fresh()->receipt_number);
        $this->assertSame(ReceiptType::Invoice, $second->fresh()->receipt_type);

        $this->fulfillment()->refund($first->fresh(), null, 'Test');
        $this->assertSame('KO-1/'.$year, $first->fresh()->refund_receipt_number);
    }

    public function test_pro_rata_refund_of_a_promotion(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user);
        $order = $this->pay($this->order($user, 'featured_7', $listing)); // 199 RSD, 7 days

        $this->travel(2)->days();
        $this->travel(1)->hours();
        $this->assertSame((int) floor(199 * 4 / 7), $this->fulfillment()->refundableAmount($order));
        $this->assertTrue($this->fulfillment()->refund($order, null, 'Odustanak'));
        $order->refresh();

        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame(113, $order->refund_amount_rsd);
        $this->assertSame(sprintf('TEST-%04d', $order->id), $order->refund_of_receipt);
        $this->assertSame(sprintf('TEST-R-%04d', $order->id), $order->refund_receipt_number);
        $this->assertEqualsWithDelta(now()->timestamp, $listing->fresh()->featured_until->timestamp, 5);
        $this->assertFalse($this->fulfillment()->refund($order));
    }

    public function test_refund_rules_for_bumps_packs_slots_and_unactivated_orders(): void
    {
        $user = $this->seller();
        $bump = $this->pay($this->order($user, 'bump', $this->listingOf($user)));
        $this->assertSame(0, $this->fulfillment()->refundableAmount($bump));

        $pack = $this->pay($this->order($user, 'bump_10'));
        BumpCredits::consume($user);
        BumpCredits::consume($user);
        $this->assertSame(intdiv(299 * 8, 10), $this->fulfillment()->refundableAmount($pack));
        $this->fulfillment()->refund($pack);
        $this->assertSame(0, BumpCredits::remaining($user));

        $slots = $this->pay($this->order($user, 'listing_slots_10'));
        $this->fulfillment()->refund($slots);
        $this->assertSame((int) Settings::get('private_active_limit'), ListingLimits::monthlyLimit($user));

        $deleted = $this->listingOf($user);
        $paidOnly = $this->order($user, 'top_7', $deleted);
        $deleted->delete();
        $paidOnly = $this->pay($paidOnly); // stays paid
        $this->assertSame(399, $this->fulfillment()->refundableAmount($paidOnly));
        $this->fulfillment()->refund($paidOnly, null, null, null, 100);
        $this->assertSame(100, $paidOnly->fresh()->refund_amount_rsd);
    }

    public function test_pending_order_cannot_be_refunded(): void
    {
        $order = $this->order($this->seller(), 'listing_slots_3');

        $this->expectException(OrderException::class);
        $this->fulfillment()->refund($order);
    }

    public function test_free_year_respects_the_founding_counter(): void
    {
        Settings::set('founding_firms_total', 1);
        $first = $this->firmUser()->firm;
        $second = $this->firmUser()->firm;

        $this->assertTrue($this->fulfillment()->grantFoundingFreeYear($first));
        $this->assertFalse($this->fulfillment()->grantFoundingFreeYear($first->fresh()));
        $this->assertSame(FirmPackage::FREE, FirmPackage::code($first->fresh()));
        $this->assertSame(1, AuditLog::where('action', 'firm.free_year')->count());

        $this->expectException(OrderException::class);
        $this->fulfillment()->grantFoundingFreeYear($second);
    }

    public function test_only_admins_may_mark_paid(): void
    {
        $order = $this->order($this->seller(), 'listing_slots_3');

        $this->assertFalse(User::factory()->moderator()->create()->can('markPaid', $order));
        $this->assertTrue(User::factory()->admin()->create()->can('markPaid', $order));
        $this->assertFalse($order->user->can('markPaid', $order));
    }
}
