<?php

namespace App\Support\Listing;

use App\Models\Firm;
use App\Models\Listing;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;

/** Everything the seller card, contact card and sticky bar need, read once per request. */
class Seller
{
    public function __construct(
        public readonly User $user,
        public readonly ?Firm $firm,
        public readonly string $name,
        public readonly bool $isFirm,
        public readonly ?string $logoUrl,
        public readonly string $initials,
        public readonly string $phone,
        public readonly int $activeCount,
        public readonly string $profileUrl,
        public readonly string $messageUrl,
    ) {}

    /** Cache key prefix of the per-category counts on the profile page. */
    public const TABS_KEY = 'listing.profile_tabs.';

    public static function forListing(Listing $listing): self
    {
        $user = $listing->user;
        $user->loadMissing('firm');
        $firm = $user->isFirm() ? $user->firm : null;
        $name = Text::displayName((string) ($firm?->name ?: $user->name));

        // Listing phone first, then the firm phone, then the account phone.
        $phone = Phone::normalize($listing->contact_phone)
            ?: Phone::normalize($firm?->phone)
            ?: Phone::normalize($user->phone);

        return new self(
            user: $user,
            firm: $firm,
            name: $name,
            isFirm: $user->isFirm(),
            logoUrl: $firm?->logoUrl() ?? $user->avatarUrl(),
            initials: Text::wordInitials($name),
            phone: $phone,
            activeCount: static::activeCount($user->id),
            profileUrl: $user->profileUrl(),
            messageUrl: static::messageUrl($listing),
        );
    }

    /** Active listings of a user; short cache, the number only needs to be roughly current. */
    public static function activeCount(int $userId): int
    {
        return (int) Cache::remember('listing.seller_count.'.$userId, 600,
            fn () => Listing::query()->active()->where('user_id', $userId)->count());
    }

    public static function forgetCount(int $userId): void
    {
        Cache::forget('listing.seller_count.'.$userId);
        Cache::forget(self::TABS_KEY.$userId);
    }

    /** Messages module: /moj-nalog/poruke/novo/?oglas=ID */
    public static function messageUrl(Listing $listing): string
    {
        return url_slash('moj-nalog/poruke/novo').'?oglas='.$listing->id;
    }

    /** The user's newest active listing, which a message from their profile is about; null when there is none. */
    public static function latestListingId(int $userId): ?int
    {
        $id = Listing::query()->active()->where('user_id', $userId)->latest('bumped_at')->latest('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    public static function profileMessageUrl(int $userId): ?string
    {
        $id = self::latestListingId($userId);

        return $id === null ? null : url_slash('moj-nalog/poruke/novo').'?oglas='.$id;
    }

    public function hasPhone(): bool
    {
        return $this->phone !== '';
    }

    public function masked(): string
    {
        return Phone::mask($this->phone, 4, true);
    }

    public function display(): string
    {
        return Phone::display($this->phone, true);
    }

    /** @return array{e164?: string, tel?: string, whatsapp?: string, viber?: string, sms?: string} */
    public function links(): array
    {
        return Phone::links($this->phone);
    }

    public function whatsapp(): bool
    {
        return $this->hasPhone() && ($this->firm ? $this->firm->messengerEnabled('whatsapp', $this->phone) : Phone::isMobile($this->phone));
    }

    public function viber(): bool
    {
        return $this->hasPhone() && ($this->firm ? $this->firm->messengerEnabled('viber', $this->phone) : Phone::isMobile($this->phone));
    }
}
