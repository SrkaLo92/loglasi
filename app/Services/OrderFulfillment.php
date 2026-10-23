<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Mail\OrderPaid;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\FiscalReceiptIssuer;
use App\Services\Payments\IssuedReceipt;
use App\Services\Payments\PaymentConfirmation;
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
 * paid → activated (activate, called by markPaid right away):
 * - featured_7 / featured_30: listing featured_until = max(now, featured_until) + product days
 * - bump: listing bumped_at = now (the one-bump-per-24-h rule is checked when ordering)
 * - biznis_12m: firm biznis_until = max(now, biznis_until) + product days (365); the account
 *   becomes a firm account only when the admin has approved the firm (firms.verified_at)
 * paid|activated → refunded (refund: the refund document references the original receipt)
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

            $effect = $this->applyEffect($locked);
            $locked->forceFill(['status' => OrderStatus::Activated, 'activated_at' => now()])->save();

            $this->audit($admin, 'order.activated', $locked, [
                'status' => [OrderStatus::Paid->value, OrderStatus::Activated->value],
                'effect' => $effect,
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
     * Marks a paid order refunded (the money is returned outside the system). The refund document
     * references the original receipt. The feature itself is not taken back automatically.
     * Returns false when the order already was refunded.
     *
     * @throws OrderException when the order was never paid
     */
    public function refund(Order $order, ?string $refundReceiptNumber = null, ?string $note = null, ?User $admin = null): bool
    {
        $admin ??= self::currentAdmin();

        $refunded = DB::transaction(function () use ($order, $refundReceiptNumber, $note, $admin) {
            $locked = $this->lockOrder($order);
            if ($locked->status === OrderStatus::Refunded) {
                return false;
            }
            if (! $locked->status->isPaid()) {
                throw new OrderException('Refundira se samo plaćena narudžbina.', 'not_paid');
            }

            $previous = $locked->status;
            $document = $this->receipts->issueRefund($locked, $refundReceiptNumber);
            $note = trim((string) $note);

            $locked->forceFill([
                'status' => OrderStatus::Refunded,
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
            ]);

            return true;
        });

        $order->refresh();

        return $refunded;
    }

    /**
     * Free Biznis package for one of the first founding firms (founding_firms_total, 30).
     * Returns false when the firm already has it; throws when all free places are taken.
     *
     * @throws OrderException
     */
    public function grantFoundingBiznis(Firm $firm, ?User $admin = null): bool
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

            $locked->forceFill(['founding_free' => true])->save();
            $effect = $this->extendBiznis($locked, 365);

            $this->audit($admin, 'firm.founding_biznis', $locked, ['founding_free' => [false, true], 'effect' => $effect]);

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

    /** @return array<string, mixed> what changed, for the audit log */
    private function applyEffect(Order $order): array
    {
        $product = Product::query()->findOrFail($order->product_id);

        return match ($product->code) {
            Product::FEATURED_7, Product::FEATURED_30 => $this->feature($order, $product),
            Product::BUMP => $this->bump($order),
            Product::BIZNIS_12M => $this->biznis($order, $product),
            default => throw new OrderException('Za ovu uslugu ne postoji automatska aktivacija.', 'unknown_product'),
        };
    }

    /** @return array<string, mixed> */
    private function feature(Order $order, Product $product): array
    {
        $listing = $this->lockListing($order);

        $days = (int) ($product->duration_days ?: ($product->code === Product::FEATURED_30 ? 30 : 7));
        $before = $listing->featured_until;
        $from = $before !== null && $before->isFuture() ? $before->copy() : now();

        $listing->forceFill(['featured_until' => $from->addDays($days)])->save();

        return [
            'listing_id' => $listing->getKey(),
            'featured_until' => [$before?->toIso8601String(), $listing->featured_until->toIso8601String()],
        ];
    }

    /** @return array<string, mixed> */
    private function bump(Order $order): array
    {
        $listing = $this->lockListing($order);

        $before = $listing->bumped_at;
        $listing->forceFill(['bumped_at' => now()])->save();

        return [
            'listing_id' => $listing->getKey(),
            'bumped_at' => [$before?->toIso8601String(), $listing->bumped_at->toIso8601String()],
        ];
    }

    /** @return array<string, mixed> */
    private function biznis(Order $order, Product $product): array
    {
        /** @var User|null $user */
        $user = User::withTrashed()->whereKey($order->user_id)->lockForUpdate()->first();
        if ($user === null) {
            throw new OrderException('Korisnik ne postoji.', 'user_missing');
        }

        $firm = Firm::query()->where('user_id', $user->getKey())->lockForUpdate()->first();
        if ($firm === null) {
            if (! $user->isFirm()) {
                throw new OrderException('Biznis paket može da se aktivira samo za firmu. Prvo otvorite profil firme za ovaj nalog.', 'firm_required');
            }
            // Firm account without a profile yet: start one with the account name.
            $firm = new Firm(['name' => $user->name]);
            $firm->forceFill(['user_id' => $user->getKey()])->save();
        }

        return $this->extendBiznis($firm, (int) ($product->duration_days ?: 365), $user);
    }

    /** @return array<string, mixed> */
    private function extendBiznis(Firm $firm, int $days, ?User $user = null): array
    {
        $before = $firm->biznis_until;
        $from = $before !== null && $before->isFuture() ? $before->copy() : now();
        $firm->forceFill(['biznis_until' => $from->addDays($days)])->save();

        $effect = [
            'firm_id' => $firm->getKey(),
            'biznis_until' => [$before?->toIso8601String(), $firm->biznis_until->toIso8601String()],
        ];

        // Only an approved firm turns the account into a firm account.
        $user ??= User::withTrashed()->find($firm->user_id);
        if ($user !== null && ! $user->isFirm() && $firm->verified_at !== null) {
            $user->forceFill(['account_type' => AccountType::Firm])->save();
            $effect['account_type'] = [AccountType::Private->value, AccountType::Firm->value];
        }

        return $effect;
    }

    /** @throws OrderException when the listing was deleted */
    private function lockListing(Order $order): Listing
    {
        /** @var Listing|null $listing */
        $listing = $order->listing_id === null ? null : Listing::query()->whereKey($order->listing_id)->lockForUpdate()->first();
        if ($listing === null) {
            throw new OrderException('Oglas za ovu narudžbinu je obrisan, usluga ne može da se uključi.', 'listing_missing');
        }

        return $listing;
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
