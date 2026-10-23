<?php

namespace App\Support\Auth;

use Illuminate\Hashing\Argon2IdHasher;

/**
 * Nove lozinke se hešuju sa argon2id. Heševi preneti iz WordPress-a (phpass, "$wp$2y$", MD5)
 * i stari bcrypt i dalje važe, a posle uspešne prijave se ponovo hešuju (rehash_on_login).
 */
class LoglasiHasher extends Argon2IdHasher
{
    public function info($hashedValue)
    {
        if (WordPressPassword::isWordPressHash((string) $hashedValue)) {
            return ['algo' => 'wordpress', 'algoName' => 'wordpress', 'options' => []];
        }

        return parent::info($hashedValue);
    }

    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = [])
    {
        $hashedValue = (string) $hashedValue;
        if ($hashedValue === '') {
            return false;
        }

        if ($this->isLegacy($hashedValue)) {
            return WordPressPassword::check((string) $value, $hashedValue);
        }

        return parent::check($value, $hashedValue, $options);
    }

    public function needsRehash($hashedValue, array $options = [])
    {
        if ($this->isLegacy((string) $hashedValue)) {
            return true;
        }

        return parent::needsRehash($hashedValue, $options);
    }

    public function verifyConfiguration($value)
    {
        // Preneti heševi se čuvaju kakvi jesu, do prve prijave.
        if ($this->isLegacy((string) $value)) {
            return true;
        }

        return parent::verifyConfiguration($value);
    }

    /** Sve što nije argon2id: WordPress heševi i bcrypt. */
    private function isLegacy(string $hash): bool
    {
        return WordPressPassword::isWordPressHash($hash)
            || password_get_info($hash)['algoName'] === 'bcrypt';
    }
}
