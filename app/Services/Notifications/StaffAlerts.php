<?php

namespace App\Services\Notifications;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Mail\Admin\NewListingMail;
use App\Models\Listing;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;

/** Mail to the admins about events on the site (setting admin_new_listing_mail). */
class StaffAlerts
{
    public const NEW_LISTING_SETTING = 'admin_new_listing_mail';

    /** A user submitted a new listing (waiting for approval or published right away). */
    public function newListing(Listing $listing): void
    {
        if (! Settings::get(self::NEW_LISTING_SETTING, true)) {
            return;
        }
        $admins = User::query()->where('role', Role::Admin)->where('status', UserStatus::Active)
            ->whereKeyNot($listing->user_id)->get(['id', 'name', 'email']);

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email, $admin->name)->queue(new NewListingMail($listing));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
