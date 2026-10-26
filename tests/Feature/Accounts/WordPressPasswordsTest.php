<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Support\Auth\WordPressPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WordPressPasswordsTest extends TestCase
{
    /** Primer iz hashcat dokumentacije (phpass, lozinka "hashcat"). */
    private const PHPASS_HASHCAT = '$P$984478476IagS59wHZvyQMArzfx58u.';

    public function test_phpass_known_vector(): void
    {
        $this->assertTrue(WordPressPassword::check('hashcat', self::PHPASS_HASHCAT));
        $this->assertFalse(WordPressPassword::check('Hashcat', self::PHPASS_HASHCAT));
        $this->assertFalse(WordPressPassword::check('hashcat', '$P$984478476IagS59wHZvyQMArzfx58u/'));
    }

    public function test_phpass_round_trip_and_bad_settings(): void
    {
        $hash = WordPressPassword::phpassHash('lozinka123');

        $this->assertStringStartsWith('$P$B', $hash);
        $this->assertSame(34, strlen($hash));
        $this->assertTrue(WordPressPassword::check('lozinka123', $hash));
        $this->assertFalse(WordPressPassword::check('lozinka124', $hash));
        // Neispravan broj krugova ili so: nikad ne prolazi.
        $this->assertFalse(WordPressPassword::check('x', '$P$'.str_repeat('.', 31)));
        $this->assertSame('*0', WordPressPassword::phpassCrypt('x', '$X$Babcdefgh'));
    }

    public function test_wp68_bcrypt_over_hmac_sha384(): void
    {
        // Isto kao wp_hash_password() u WP 6.8: '$wp' + bcrypt(base64(hmac_sha384(lozinka, 'wp-sha384'))).
        $pre = base64_encode(hash_hmac('sha384', 'Šifra-2026!', 'wp-sha384', true));
        $hash = '$wp'.password_hash($pre, PASSWORD_BCRYPT, ['cost' => 4]);

        $this->assertStringStartsWith('$wp$2y$04$', $hash);
        $this->assertTrue(WordPressPassword::check('Šifra-2026!', $hash));
        $this->assertFalse(WordPressPassword::check('šifra-2026!', $hash));
        // Bez pre-heša (obična bcrypt provera) ne sme da prođe.
        $this->assertFalse(password_verify('Šifra-2026!', substr($hash, 3)));
        $this->assertTrue(WordPressPassword::check('abc', WordPressPassword::hashWp68('abc', 4)));
    }

    public function test_legacy_md5_and_plain_bcrypt(): void
    {
        $this->assertTrue(WordPressPassword::check('stara', md5('stara')));
        $this->assertFalse(WordPressPassword::check('nova', md5('stara')));
        $this->assertTrue(WordPressPassword::check('bcrypt', password_hash('bcrypt', PASSWORD_BCRYPT, ['cost' => 4])));
        $this->assertFalse(WordPressPassword::check('x', ''));
    }

    public function test_hasher_makes_argon2id_and_checks_wordpress_hashes(): void
    {
        $this->assertSame('argon2id', password_get_info(Hash::make('abc'))['algoName']);

        $this->assertTrue(Hash::check('hashcat', self::PHPASS_HASHCAT));
        $this->assertTrue(Hash::needsRehash(self::PHPASS_HASHCAT));
        $this->assertTrue(Hash::needsRehash(password_hash('a', PASSWORD_BCRYPT, ['cost' => 4])));
        $this->assertFalse(Hash::needsRehash(Hash::make('abc')));
        $this->assertTrue(Hash::isHashed(self::PHPASS_HASHCAT));
    }

    public function test_imported_hash_is_kept_by_model_cast(): void
    {
        $user = User::factory()->create(['password' => self::PHPASS_HASHCAT]);

        $this->assertSame(self::PHPASS_HASHCAT, DB::table('users')->where('id', $user->id)->value('password'));
    }

    public function test_web_login_with_phpass_rehashes_to_argon2id(): void
    {
        $user = User::factory()->create(['email' => 'wp@example.com', 'password' => self::PHPASS_HASHCAT]);

        $this->post('/prijava/', ['email' => 'wp@example.com', 'password' => 'hashcat'])
            ->assertRedirect(url_slash('moj-nalog'));

        $this->assertAuthenticatedAs($user);
        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertSame('argon2id', password_get_info($stored)['algoName']);
        $this->assertTrue(Hash::check('hashcat', $stored));
    }

    public function test_api_login_with_wp68_hash_rehashes_to_argon2id(): void
    {
        $hash = WordPressPassword::hashWp68('mobilna-lozinka', 4);
        $user = User::factory()->create(['email' => 'wp68@example.com', 'password' => $hash]);

        $this->postJson('/api/v1/auth/login', ['email' => 'wp68@example.com', 'password' => 'mobilna-lozinka'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertSame('argon2id', password_get_info($stored)['algoName']);
    }

    public function test_wrong_password_keeps_wordpress_hash(): void
    {
        $user = User::factory()->create(['email' => 'wp@example.com', 'password' => self::PHPASS_HASHCAT]);

        $this->post('/prijava/', ['email' => 'wp@example.com', 'password' => 'pogresna'])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(self::PHPASS_HASHCAT, DB::table('users')->where('id', $user->id)->value('password'));
    }
}
