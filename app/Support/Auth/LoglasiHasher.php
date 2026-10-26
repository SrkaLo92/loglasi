<?php

namespace App\Support\Auth;

use Illuminate\Hashing\Argon2IdHasher;

/**
 * New passwords are hashed with argon2id. Hashes carried over from WordPress (phpass, "$wp$2y$", MD5)
 * and old bcrypt still work, and are hashed again after a successful login (rehash_on_login).
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
        // Carried-over hashes are kept as they are until the first login.
        if ($this->isLegacy((string) $value)) {
            return true;
        }

        return parent::verifyConfiguration($value);
    }

    /** Everything that is not argon2id: WordPress hashes and bcrypt. */
    private function isLegacy(string $hash): bool
    {
        return WordPressPassword::isWordPressHash($hash)
            || password_get_info($hash)['algoName'] === 'bcrypt';
    }
}
