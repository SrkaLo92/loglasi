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
            'email' => 'marko@primer.rs',
            'telefon' => '065 123 4567',
            'tema' => 'Pitanje o oglasu',
            'poruka' => 'Zanima me kako da istaknem oglas za bicikl.',
            'saglasnost' => '1',
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
            ->assertSee('<label for="k-ime">Ime i prezime</label>', false)
            ->assertSee('<label for="k-email">E-pošta</label>', false)
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
                && $mail->data['telefon'] === '+381651234567'
                && $mail->data['tema'] === 'Pitanje o oglasu'
                && str_contains($mail->envelope()->subject, 'Pitanje o oglasu');
        });
        Mail::assertNothingSent();
    }

    public function test_success_message_is_shown_after_redirect(): void
    {
        $this->followingRedirects()->send($this->valid())
            ->assertOk()
            ->assertSee('Hvala na poruci. Poslata je');
    }

    public function test_mail_text_contains_message_without_html_entities(): void
    {
        $mail = new KontaktPoruka([
            'ime' => 'Ana "Anči" Jovanović', 'email' => 'ana@primer.rs', 'telefon' => '',
            'tema' => 'Nešto drugo', 'poruka' => 'Pitanje & odgovor <b>', 'ip' => '127.0.0.1',
        ]);

        $mail->assertSeeInText('Ana "Anči" Jovanović');
        $mail->assertSeeInText('Pitanje & odgovor <b>');
        $mail->assertSeeInText('Telefon: nije unet');
    }

    public function test_required_fields_are_validated_in_serbian(): void
    {
        $this->send(['_t' => $this->token()])
            ->assertRedirect(url_slash('kontakt'))
            ->assertSessionHasErrors(['ime', 'email', 'tema', 'poruka', 'saglasnost']);

        $errors = session('errors')->getBag('default');
        $this->assertSame('Ovo polje je obavezno.', $errors->first('ime'));
        Mail::assertNothingQueued();
    }

    public function test_invalid_email_phone_topic_and_short_message(): void
    {
        $this->send($this->valid(['email' => 'marko@', 'telefon' => '123', 'tema' => 'Bilo šta', 'poruka' => 'Kratko']))
            ->assertSessionHasErrors([
                'email' => 'Unesite ispravnu adresu e-pošte.',
                'telefon' => 'Unesite ispravan broj telefona, npr. 65 123 4567.',
                'tema' => 'Izaberite temu sa liste.',
            ])
            ->assertSessionHasErrors('poruka');

        Mail::assertNothingQueued();
    }

    public function test_errors_are_rendered_next_to_fields(): void
    {
        $this->followingRedirects()->send($this->valid(['email' => 'marko@']))
            ->assertSee('aria-invalid="true" aria-describedby="greska-email"', false)
            ->assertSee('Neka polja nisu dobro popunjena.')
            ->assertSee('value="Marko Marković"', false);
    }

    public function test_phone_is_optional(): void
    {
        $this->send($this->valid(['telefon' => '']))->assertSessionHasNoErrors();

        Mail::assertQueued(KontaktPoruka::class, fn ($mail) => $mail->data['telefon'] === '');
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
