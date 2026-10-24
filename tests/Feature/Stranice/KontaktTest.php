<?php

namespace Tests\Feature\Stranice;

use App\Mail\KontaktPoruka;
use App\Support\Settings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class KontaktTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('kontakt:127.0.0.1');
    }

    /** Forma otvorena pre $secondsAgo sekundi. */
    private function token(int $secondsAgo = 30): string
    {
        return Crypt::encryptString((string) (now()->getTimestamp() - $secondsAgo));
    }

    private function valid(array $override = []): array
    {
        return array_merge([
            'ime' => 'Marko Marković',
            'kontakt' => 'marko@primer.rs',
            'tema' => 'Plaćanje i isticanje',
            'poruka' => 'Zanima me kako da istaknem oglas za bicikl.',
            'sajt' => '',
            '_t' => $this->token(),
        ], $override);
    }

    private function send(array $data)
    {
        return $this->from(url_slash('kontakt'))->post('/kontakt/', $data);
    }

    public function test_form_renders_with_labels_honeypot_and_time_token(): void
    {
        $this->get('/kontakt/')
            ->assertOk()
            ->assertSee('<label for="k-ime">Ime</label>', false)
            ->assertSee('<label for="k-kontakt">E-mail ili telefon</label>', false)
            ->assertSee('Odgovaramo na e-mail ili vas zovemo.')
            ->assertSee('<option value="">— Izaberite —</option>', false)
            ->assertSee('aria-label="Kontakt forma"', false)
            ->assertDontSee('name="saglasnost"', false)
            ->assertSee('name="sajt"', false)
            ->assertSee('name="_t"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('Pošalji poruku')
            ->assertSee('Kontakt podaci')
            ->assertSee('mailto:'.Settings::get('contact_email'), false);
    }

    public function test_valid_message_is_queued_to_contact_email_from_settings(): void
    {
        Settings::set('contact_email', 'urednik@loglasi.rs');

        $this->send($this->valid())
            ->assertRedirect(url_slash('kontakt').'#poruka-poslata')
            ->assertSessionHas('kontakt_poslato', true)
            ->assertSessionHasNoErrors();

        Mail::assertQueued(KontaktPoruka::class, function (KontaktPoruka $mail) {
            return $mail->hasTo('urednik@loglasi.rs')
                && $mail->hasReplyTo('marko@primer.rs')
                && $mail->data['telefon'] === ''
                && $mail->data['tema'] === 'Plaćanje i isticanje'
                && str_contains($mail->envelope()->subject, 'Plaćanje i isticanje');
        });
        Mail::assertNothingSent();
    }

    public function test_success_message_is_shown_after_redirect(): void
    {
        $this->followingRedirects()->send($this->valid())
            ->assertOk()
            ->assertSee('Hvala, poruka je poslata. Javićemo vam se uskoro.');
    }

    public function test_mail_text_contains_message_without_html_entities(): void
    {
        $mail = new KontaktPoruka([
            'ime' => 'Ana "Anči" Jovanović', 'email' => '', 'telefon' => '+381651234567',
            'tema' => 'Nešto drugo', 'poruka' => 'Pitanje & odgovor <b>', 'ip' => '127.0.0.1',
        ]);

        $mail->assertSeeInText('Ana "Anči" Jovanović');
        $mail->assertSeeInText('Pitanje & odgovor <b>');
        $mail->assertSeeInText('E-mail ili telefon: +381651234567');
        $mail->assertSeeInText('Pošiljalac je ostavio telefon');
        $this->assertSame([], $mail->envelope()->replyTo);
    }

    public function test_required_fields_are_validated_in_serbian(): void
    {
        $this->send(['_t' => $this->token()])
            ->assertRedirect(url_slash('kontakt'))
            ->assertSessionHasErrors(['ime', 'kontakt', 'tema', 'poruka']);

        $errors = session('errors')->getBag('default');
        $this->assertSame('Ovo polje je obavezno.', $errors->first('ime'));
        Mail::assertNothingQueued();
    }

    public function test_invalid_email_phone_topic_and_short_message(): void
    {
        $this->send($this->valid(['kontakt' => 'marko@', 'tema' => 'Bilo šta', 'poruka' => 'Kratko']))
            ->assertSessionHasErrors([
                'kontakt' => 'Unesite ispravnu adresu e-pošte ili broj telefona, npr. 65 123 4567.',
                'tema' => 'Izaberite temu sa liste.',
                'poruka' => 'Tekst je prekratak.',
            ]);
        $this->send($this->valid(['poruka' => str_repeat('a', 2001)]))->assertSessionHasErrors(['poruka' => 'Tekst je predugačak.']);
        $this->send($this->valid(['kontakt' => '123']))->assertSessionHasErrors('kontakt');

        Mail::assertNothingQueued();
    }

    public function test_errors_are_rendered_next_to_fields(): void
    {
        $this->followingRedirects()->send($this->valid(['kontakt' => 'marko@']))
            ->assertSee('aria-describedby="pomoc-kontakt greska-kontakt"', false)
            ->assertSee('id="greska-kontakt"', false)
            ->assertSee('Neka polja nisu dobro popunjena.')
            ->assertSee('value="Marko Marković"', false);
    }

    public function test_phone_instead_of_email_is_stored_in_e164(): void
    {
        $this->send($this->valid(['kontakt' => '065 123 4567']))->assertSessionHasNoErrors();

        Mail::assertQueued(KontaktPoruka::class, fn ($mail) => $mail->data['telefon'] === '+381651234567' && $mail->data['email'] === '');
    }

    public function test_honeypot_pretends_success_and_sends_nothing(): void
    {
        $this->send($this->valid(['sajt' => 'https://spam.example']))
            ->assertRedirect(url_slash('kontakt').'#poruka-poslata')
            ->assertSessionHas('kontakt_poslato', true);

        Mail::assertNothingQueued();
    }

    public function test_too_fast_submission_is_dropped(): void
    {
        $this->send($this->valid(['_t' => $this->token(0)]))
            ->assertSessionHas('kontakt_poslato', true);

        Mail::assertNothingQueued();
    }

    public function test_missing_or_forged_time_token_is_rejected(): void
    {
        $this->send($this->valid(['_t' => '']))->assertSessionHasErrors('forma');
        $this->send($this->valid(['_t' => 'lazni-token']))->assertSessionHasErrors('forma');

        Mail::assertNothingQueued();
    }

    public function test_expired_form_is_rejected(): void
    {
        $this->send($this->valid(['_t' => $this->token(13 * 3600)]))
            ->assertSessionHasErrors(['forma' => 'Forma je istekla. Proverite podatke i pošaljite poruku ponovo.']);

        Mail::assertNothingQueued();
    }

    public function test_rate_limit_three_messages_per_ten_minutes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->send($this->valid())->assertSessionHasNoErrors();
        }

        $this->send($this->valid())->assertSessionHasErrors('forma');
        $this->assertStringContainsString('Poslali ste više poruka za kratko vreme', session('errors')->first('forma'));

        Mail::assertQueued(KontaktPoruka::class, 3);

        $this->travel(11)->minutes();
        $this->send($this->valid(['_t' => $this->token()]))->assertSessionHasNoErrors();
        Mail::assertQueued(KontaktPoruka::class, 4);
    }
}
