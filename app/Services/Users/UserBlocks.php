<?php

namespace App\Services\Users;

use App\Models\User;
use App\Services\Messages\Messenger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One user blocks another (user_blocks). The blocked user cannot write to the blocker (and the blocker
 * cannot write to them until unblocking); conversations with them leave the blocker's inbox and unread count,
 * and their listings leave the blocker's search and home page. The blocked user is not told.
 */
class UserBlocks
{
    /** @var array<int, list<int>> blocked ids per blocker, for this request */
    private array $memo = [];

    public function block(User $blocker, int $blockedId): void
    {
        if ($blocker->id === $blockedId) {
            return;
        }
        DB::table('user_blocks')->insertOrIgnore(['blocker_id' => $blocker->id, 'blocked_id' => $blockedId, 'created_at' => now()]);
        $this->changed($blocker->id);
    }

    public function unblock(User $blocker, int $blockedId): void
    {
        DB::table('user_blocks')->where('blocker_id', $blocker->id)->where('blocked_id', $blockedId)->delete();
        $this->changed($blocker->id);
    }

    public function hasBlocked(int $blockerId, int $blockedId): bool
    {
        return in_array($blockedId, $this->blockedIds($blockerId), true);
    }

    /** @return list<int> users that $blockerId blocked (empty for guests) */
    public function blockedIds(User|int|null $blocker): array
    {
        $id = $blocker instanceof User ? $blocker->id : $blocker;
        if (! $id) {
            return [];
        }

        return $this->memo[$id] ??= DB::table('user_blocks')->where('blocker_id', $id)->pluck('blocked_id')->map(fn ($v) => (int) $v)->all();
    }

    /** @return Collection<int, object{id: int, name: string, slug: ?string, blocked_at: ?string}> newest first */
    public function list(User $blocker): Collection
    {
        return DB::table('user_blocks')
            ->join('users', 'users.id', '=', 'user_blocks.blocked_id')
            ->where('user_blocks.blocker_id', $blocker->id)
            ->orderByDesc('user_blocks.created_at')
            ->get(['users.id', 'users.name', 'users.slug', 'users.deleted_at', 'user_blocks.created_at as blocked_at']);
    }

    private function changed(int $blockerId): void
    {
        unset($this->memo[$blockerId]);
        app(Messenger::class)->forgetUnread($blockerId);
    }
}
