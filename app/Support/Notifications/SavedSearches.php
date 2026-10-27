<?php

namespace App\Support\Notifications;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Saving a normalized search for a user (button on /oglasi/, or after login for a guest).
 * The returned model has wasRecentlyCreated = false when the same search already existed.
 */
class SavedSearches
{
    /** Session key of a guest's search, saved on the first signed-in request (SavePendingSearch). */
    public const PENDING = 'notifications.pending_search';

    public function __construct(private readonly SavedSearchQuery $queries) {}

    /**
     * @param  array  $query  output of SavedSearchQuery::normalize()
     *
     * @throws ValidationException when the user reached the limit
     */
    public function save(User $user, array $query, ?string $name = null): SavedSearch
    {
        $hash = SavedSearchQuery::hash($query);

        $existing = $user->savedSearches()->where('query_hash', $hash)->first();
        if ($existing !== null) {
            return $existing;
        }

        $max = self::max();
        if ($user->savedSearches()->count() >= $max) {
            throw ValidationException::withMessages([
                'keyword' => 'Možete sačuvati najviše '.$max.' pretraga. Obrišite neku koju više ne koristite.',
            ]);
        }

        // createOrFirst: a double click saves one row (unique user_id + query_hash).
        return $user->savedSearches()->createOrFirst(['query_hash' => $hash], [
            'name' => self::cleanName($name) ?? $this->queries->describe($query),
            'query' => $query,
            'notify' => true,
        ]);
    }

    /** @param  array{name?: ?string, notify?: bool}  $data  validated fields; an empty name goes back to the description */
    public function update(SavedSearch $search, array $data): void
    {
        if (array_key_exists('name', $data)) {
            $search->name = self::cleanName($data['name']) ?? $this->queries->describe((array) $search->query);
        }
        if (array_key_exists('notify', $data)) {
            $search->notify = (bool) $data['notify'];
        }
        $search->save();
    }

    public static function cleanName(mixed $name): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $name)));

        return $name === '' ? null : mb_substr($name, 0, 80);
    }

    public static function max(): int
    {
        return (int) config('notifications.saved_searches.max_per_user', 20);
    }
}
