<?php

namespace App\Filament\Support;

use App\Exceptions\OrderException;
use DomainException;
use Filament\Notifications\Notification;

/** Runs an admin service call and shows the result as a notification. */
class Notify
{
    public static function run(callable $callback, string $success): bool
    {
        try {
            $callback();
        } catch (DomainException|OrderException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title($success)->success()->send();

        return true;
    }
}
