<?php

namespace App\Support\Auth;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Ratings\SellerRatings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deletes an account on the user's request (site and app).
 *
 * Deleted: listings (soft, so the old URLs return 410), conversations and messages, favourites, saved
 * searches, ratings (given and received), devices, tokens, the firm profile and the avatar. The user row stays without personal data
 * (name, email, phone, password), so orders and bookkeeping stay correct.
 * The same email can register again later.
 */
class AccountDeletion
{
    public static function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            ApiTokens::revokeAll($user);
            $user->devices()->delete();
            $user->favorites()->detach();
            DB::table('saved_searches')->where('user_id', $user->id)->delete();

            Conversation::query()
                ->where('buyer_id', $user->id)
                ->orWhere('seller_id', $user->id)
                ->delete();

            // Ratings the user gave and got; the averages of the rated sellers are recomputed after commit.
            $rated = DB::table('seller_ratings')->where('rater_id', $user->id)->pluck('seller_id')->all();
            DB::table('seller_ratings')->where('rater_id', $user->id)->orWhere('seller_id', $user->id)->delete();
            DB::afterCommit(fn () => array_map(fn ($id) => app(SellerRatings::class)->refresh((int) $id), $rated));

            $user->listings()->delete();
            $user->firm()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            Avatar::delete($user);

            $user->forceFill([
                'name' => 'Obrisan korisnik',
                'email' => 'obrisan-'.$user->id.'-'.Str::lower(Str::random(8)).'@obrisan.invalid',
                'phone' => null,
                'phone_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'notification_settings' => null,
                'status' => UserStatus::Blocked,
            ])->save();

            $user->delete();
        });
    }
}
