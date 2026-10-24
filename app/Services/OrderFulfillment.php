<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Mail\OrderPaid;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\BumpCredits;
use App\Services\Payments\Effects;
use App\Services\Payments\FiscalReceiptIssuer;
use App\Services\Payments\IssuedReceipt;
use App\Services\Payments\PaymentConfirmation;
use App\Services\Payments\ProductCatalog;
use App\Support\Firms\FirmPackage;
use App\Support\Settings;
use App\Support\Stranice\Cenovnik;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Records payments, activates what was bought, and refunds. Every step runs in a transaction
 * with the order row locked and is idempotent: repeating a step that already happened does nothing.
 *
 * pending|cancelled → paid (markPaid: payment recorded, receipt numbers stored)
 * paid → activated (activate, called by markPaid right away): Payments\Effects applies the product
 *   (bump, bump pack, listing promotion periods, extra listing slots, firm package via FirmPackage::grant)
 * paid|activated → refunded (refund: pro-rata for unused days, document references the original receipt)
 */
class OrderFulfillment
{
    public function __construct(private readonly FiscalReceiptIssuer $receipts) {}

    /**
     * Records the payment and activates the order. Returns true when the payment was recorded
     * now, false when the order already was paid. A cancelled order can still be marked paid
     * (the transfer arrived late). If the feature cannot be activated (listing deleted, no firm
     * profile), the payment stays recorded with status "paid", the failure is audit-logged and
     * the admin can refund it or call activate() later.
     */
    public function markPaid(Order $order, ?User $admin = null, ?PaymentConfirmation $confirmation = null): bool
    {
        $admin ??= self::currentAdmin();
        $confirmation ??= new PaymentConfirmation;

        $paid = DB::transaction(function () use ($order, $admin, $confirmation) {
            $locked = $this->lockOrder($order);
            if ($locked->status->isPaid()) {
                return false;
            }

            $previous = $locked->status;
            $locked->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => $confirmation->paidAt ?? now(),
                'marked_paid_by' => $admin?->getKey(),
                'cancelled_at' => null,
                'cancelled_by' => null,
            ]);
            $receipt = $this->receipts->issueSale($locked, $confirmation);
            if ($receipt !== null) {
                $this->fillReceipt($locked, $receipt);
            }
            $locked->save();

            $this->audit($admin, 'order.paid', $locked, [
                'status' => [$previous->value, OrderStatus::Paid->value],
                'reference' => $locked->reference,
                'amount_rsd' => $locked->amount_rsd,
                'receipt_number' => $locked->receipt_number,
                'advance_receipt_number' => $locked->advance_receipt_number,
            ]);

            return true;
        });

        if ($paid) {
            try {
                $this->activate($order, $admin);
            } catch (OrderException $e) {
                $this->audit($admin, 'order.activation_failed', $order, ['reason' => $e->reason, 'message' => $e->getMessage()]);
            }
        }

        $order->refresh();

        return $paid;
    }

    /**
     * Applies the effect of a paid order. Returns false when it is not waiting for activation.
     *
     * @throws OrderException when the effect cannot be applied (nothing is changed then)
     */
    public function activate(Order $order, ?User $admin = null): bool
    {
        $admin ??= self::currentAdmin();

        $activated = DB::transaction(function () use ($order, $admin) {
            $locked = $this->lockOrder($order);
            if ($locked->status !== OrderStatus::Paid) {
                return false;
            }

            $product = Product::query()->findOrFail($locked->product_id);
            $user = User::withTrashed()->findOrFail($locked->user_id);
            $applied = Effects::apply($product, $user, $locked->listing_id, (int) $locked->getKey());
            $locked->forceFill([
                'status' => OrderStatus::Activated,
                'activated_at' => now(),
                'service_starts_at' => $applied['starts'],
                'service_ends_at' => $applied['ends'],
            ])->save();

            $this->audit($admin, 'order.activated', $locked, [
                'status' => [OrderStatus::Paid->value, OrderStatus::Activated->value],
                'effect' => $applied['effect'],
            ]);

            return true;
        });

        $order->refresh();

        if ($activated && $order->user) {
            Mail::to($order->user)->queue(new OrderPaid($order));
        }

        return $activated;
    }

    /** Receipt numbers entered after the payment was marked (manual fiscalisation). */
    public function recordReceipt(Order $order, PaymentConfirmation $confirmation, ?User $admin = null): bool
    {
        $admin ??= self::currentAdmin();

        $recorded = DB::transaction(function () use ($order, $confirmation, $admin) {
            $locked = $this->lockOrder($order);
            if (! $locked->status->isPaid()) {
                throw new OrderException('Račun se upisuje samo za plaćenu narudžbinu.', 'not_paid');
            }

            $receipt = $this->receipts->issueSale($locked, new PaymentConfirmation(
                paidAt: $locked->paid_at,
                receiptNumber: $confirmation->receiptNumber,
                advanceReceiptNumber: $confirmation->advanceReceiptNumber,
            ));
            if ($receipt === null) {
                return false;
            }

            $before = [$locked->receipt_number, $locked->advance_receipt_number];
            $this->fillReceipt($locked, $receipt);
            $locked->save();

            $this->audit($admin, 'order.receipt', $locked, [
                'receipt_number' => [$before[0], $locked->receipt_number],
                'advance_receipt_number' => [$before[1], $locked->advance_receipt_number],
            ]);

            return true;
        });

        $order->refresh();

        return $recorded;
    }

    /**
     * What a refund of this order returns now: the whole amount before activation; after it, the
     * share of whole unused days (promotions, extra listings, firm packages) or unused bumps of a
     * pack; nothing for a single bump (performed at once).
     */
    public function refundableAmount(Order $order): int
    {
        if ($order->status === OrderStatus::Paid) {
            return (int) $order->amount_rsd;
        }
        if ($order->status !== OrderStatus::Activated) {
            return 0;
        }
        $product = $order->product ?? Product::query()->find($order->product_id);
        $type = $product ? ProductCatalog::type($product) : null;

        if ($type === ProductCatalog::BUMP_PACK) {
            $pack = BumpCredits::ofOrder((int) $order->getKey());

            return $pack && $pack['granted'] > 0 ? intdiv((int) $order->amount_rsd * max(0, $pack['granted'] - $pack['used']), $pack['granted']) : 0;
        }
        if ($type === ProductCatalog::BUMP || $order->service_starts_at === null || $order->service_ends_at === null) {
            return 0;
        }

        return (int) floor($order->amount_rsd * Effects::unusedShare($order->service_starts_at, $order->service_ends_at));
    }

    /**
     * Marks a paid order refunded (the money is returned outside the system) and takes back the
     * unused part of the service. The amount is pro-rata (refundableAmount) unless the admin gives
     * one (e.g. the whole amount when the service failed on our side). The refund document
     * references the original receipt. Returns false when the order already was refunded.
     *
     * @throws OrderException when the order was never paid
     */
    public function refund(Order $order, ?string $refundReceiptNumber = null, ?string $note = null, ?User $admin = null, ?int $amountRsd = null): bool
    {
        $admin ??= self::currentAdmin();

        $refunded = DB::transaction(function () use ($order, $refundReceiptNumber, $note, $admin, $amountRsd) {
            $locked = $this->lockOrder($order);
            if ($locked->status === OrderStatus::Refunded) {
                return false;
            }
            if (! $locked->status->isPaid()) {
                throw new OrderException('Refundira se samo plaćena narudžbina.', 'not_paid');
            }

            $previous = $locked->status;
            $amount = $amountRsd !== null ? max(0, min((int) $locked->amount_rsd, $amountRsd)) : $this->refundableAmount($locked);
            if ($previous === OrderStatus::Activated) {
                Effects::revert($locked, Product::query()->findOrFail($locked->product_id));
            }
            $document = $this->receipts->issueRefund($locked, $refundReceiptNumber);
            $note = trim((string) $note);

            $locked->forceFill([
                'status' => OrderStatus::Refunded,
                'refund_amount_rsd' => $amount,
                'refunded_at' => now(),
                'refunded_by' => $admin?->getKey(),
                'refund_of_receipt' => $locked->receipt_number ?? $locked->advance_receipt_number,
                'refund_receipt_number' => $document?->number,
                'note' => $note !== '' ? trim(($locked->note ? $locked->note."\n" : '').$note) : $locked->note,
            ])->save();

            $this->audit($admin, 'order.refunded', $locked, [
                'status' => [$previous->value, OrderStatus::Refunded->value],
                'refund_of_receipt' => $locked->refund_of_receipt,
                'refund_receipt_number' => $locked->refund_receipt_number,
                'refund_receipt_type' => $document?->type->value,
                'refund_amount_rsd' => $amount,
            ]);

            return true;
        });

        $order->refresh();

        return $refunded;
    }

    /**
     * The free first year of one of the first firms ("Prvih 30 firmi", founding_firms_total). It is
     * never sold. Returns false when the firm already took a place; throws when all are taken.
     *
     * @throws OrderException
     */
    public function grantFoundingFreeYear(Firm $firm, ?User $admin = null): bool
    {
        $admin ??= self::currentAdmin();

        $granted = DB::transaction(function () use ($firm, $admin) {
            /** @var Firm $locked */
            $locked = Firm::query()->whereKey($firm->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->founding_free) {
                return false;
            }

            // Locks the counted rows too, so two admins cannot hand out the last place twice.
            $taken = Firm::query()->where('founding_free', true)->lockForUpdate()->count();
            if ($taken >= self::foundingTotal()) {
                throw new OrderException('Sva besplatna mesta za firme su popunjena.', 'founding_full');
            }

            FirmPackage::grantFreeYear($locked);
            $this->audit($admin, 'firm.free_year', $locked, [
                'founding_free' => [false, true],
                'package_ends_at' => $locked->package_ends_at?->toIso8601String(),
            ]);

            return true;
        });

        Cenovnik::forgetFounding();
        $firm->refresh();

        return $granted;
    }

    public static function foundingTotal(): int
    {
        return max(0, (int) Settings::get('founding_firms_total'));
    }

    public static function foundingLeft(): int
    {
        return max(0, self::foundingTotal() - Firm::query()->where('founding_free', true)->count());
    }

    private function lockOrder(Order $order): Order
    {
        /** @var Order|null $locked */
        $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();
        if ($locked === null) {
            throw new OrderException('Narudžbina ne postoji.', 'not_found');
        }

        return $locked;
    }

    private function fillReceipt(Order $order, IssuedReceipt $receipt): void
    {
        $order->forceFill([
            'receipt_type' => $receipt->type,
            'receipt_number' => $receipt->number ?? $order->receipt_number,
            'receipt_issued_at' => $receipt->number !== null ? $receipt->issuedAt : $order->receipt_issued_at,
            'advance_receipt_number' => $receipt->advanceNumber ?? $order->advance_receipt_number,
            'advance_receipt_issued_at' => $receipt->advanceIssuedAt ?? $order->advance_receipt_issued_at,
        ]);
    }

    private static function currentAdmin(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** @param  array<string, mixed>  $changes */
    private function audit(?User $actor, string $action, Model $subject, array $changes): void
    {
        AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'changes' => $changes,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
