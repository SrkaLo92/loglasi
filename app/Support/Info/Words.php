<?php

namespace App\Support\Info;

/** Serbian number agreement for the few counted phrases on the portal. */
final class Words
{
    public static function outages(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        $form = match (true) {
            $mod10 === 1 && $mod100 !== 11 => 'planirano isključenje',
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'planirana isključenja',
            default => 'planiranih isključenja',
        };

        return $n.' '.$form;
    }
}
