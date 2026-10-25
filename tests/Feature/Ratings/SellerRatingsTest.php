<?php

namespace Tests\Feature\Ratings;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\SellerRating;
use App\Models\User;
use App\Notifications\NewRatingNotification;
use App\Services\Messages\Messenger;
use App\Services\Ratings\SellerRatings;
use App\Support\Auth\AccountDeletion;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

/** Who may rate a seller, how ratings are shown on the profile and listing, the seller's reply and report, the admin. */
class SellerRatingsTest extends TestCase
{
    use ActsAsStaff;

    private User $seller;

    private User $buyer;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        $this->seller = User::factory()->create(['name' => 'Petar Prodavac']);
        $this->buyer = User::factory()->create(['name' => 'Marko Kupić']);
        $this->listing = Listing::factory()->for($this->seller)->create(['title' => 'Bicikl Capriolo']);
    }

    /** A conversation; the seller answered unless $answered is false. */
    private function conversation(bool $answered = true, ?User $buyer = null): Conversation
    {
        $messenger = app(Messenger::class);
        $message = $messenger->start($buyer ?? $this->buyer, $this->listing, 'Da li je još dostupan?');
        $conversation = $message->conversation;
        if ($answered) {
            $messenger->reply($this->seller, $conversation, 'Jeste, javite se.');
        }

        return $conversation->fresh();
    }

    private function rate(Conversation $conversation, int $score = 5, ?string $comment = 'Sve po dogovoru.')
    {
        return $this->actingAs($this->buyer)->post(url_slash('moj-nalog/poruke/'.$conversation->id.'/ocena'), ['score' => $score, 'comment' => $comment]);
    }

    public function test_buyer_rates_after_the_seller_answered(): void
    {
        $conversation = $this->conversation();

        $this->rate($conversation)->assertRedirect(url_slash('moj-nalog/poruke/'.$conversation->id).'#ocena')->assertSessionHas('status');

        $rating = SellerRating::query()->sole();
        $this->assertSame(5, $rating->score);
        $this->assertSame($this->listing->id, $rating->listing_id);
        $this->assertSame(1, $this->seller->fresh()->rating_count);
        $this->assertEquals(5.0, (float) $this->seller->fresh()->rating_avg);
        Notification::assertSentTo($this->seller, NewRatingNotification::class);
    }

    public function test_no_rating_before_the_seller_answered_and_never_by_the_seller(): void
    {
        $conversation = $this->conversation(answered: false);
        $this->rate($conversation)->assertSessionHasErrors(['score'], null, 'ocena');
        $this->assertSame(0, SellerRating::query()->count());

        $answered = $this->conversation();
        $this->actingAs($this->seller)->post(url_slash('moj-nalog/poruke/'.$answered->id.'/ocena'), ['score' => 1])
            ->assertSessionHasErrors(['score'], null, 'ocena');
        $this->actingAs(User::factory()->create())->post(url_slash('moj-nalog/poruke/'.$answered->id.'/ocena'), ['score' => 1])
            ->assertNotFound();
        $this->assertSame(0, SellerRating::query()->count());
    }

    public function test_unverified_buyer_cannot_rate(): void
    {
        $buyer = User::factory()->create();
        $conversation = $this->conversation(buyer: $buyer);
        $buyer->forceFill(['email_verified_at' => null])->save();

        $this->assertSame('Potvrdite e-poštu da biste ocenili prodavca.', app(SellerRatings::class)->blocker($buyer, $conversation));
    }

    public function test_one_rating_per_buyer_and_seller_editable_for_a_while(): void
    {
        $conversation = $this->conversation();
        $this->rate($conversation, 2, 'Kasnio je.');
        $this->rate($conversation, 4, 'Ipak u redu.')->assertSessionHas('status', 'Ocena je izmenjena.');

        $this->assertSame(1, SellerRating::query()->count());
        $this->assertSame(4, SellerRating::query()->sole()->score);
        Notification::assertSentToTimes($this->seller, NewRatingNotification::class, 1);

        $this->travel(SellerRatings::EDIT_DAYS + 1)->days();
        $this->rate($conversation, 1)->assertSessionHasErrors(['score'], null, 'ocena');
        $this->assertSame(4, SellerRating::query()->sole()->score);
    }

    public function test_score_must_be_one_to_five(): void
    {
        $conversation = $this->conversation();
        $this->rate($conversation, 6)->assertSessionHasErrors(['score'], null, 'ocena');
        $this->rate($conversation, 0)->assertSessionHasErrors(['score'], null, 'ocena');
        $this->assertSame(0, SellerRating::query()->count());
    }

    public function test_conversation_shows_the_rating_card_to_the_buyer_only(): void
    {
        $conversation = $this->conversation();

        $this->actingAs($this->buyer)->get(url_slash('moj-nalog/poruke/'.$conversation->id))->assertOk()
            ->assertSee('Ocenite prodavca')->assertSee('name="score"', false);
        $this->actingAs($this->seller)->get(url_slash('moj-nalog/poruke/'.$conversation->id))->assertOk()
            ->assertDontSee('Ocenite prodavca');
    }

    public function test_profile_listing_and_ratings_page_show_ratings_with_escaped_comment(): void
    {
        $this->rate($this->conversation(), 4, '<script>alert(1)</script> Dobar dogovor');

        $this->get($this->seller->profileUrl())->assertOk()->assertSee('Ocene kupaca')->assertSee('Marko K.')
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(url_slash('oglas/'.$this->listing->slug))->assertOk()->assertSee('4,0')->assertSee('1 ocena');
        $this->get(url_slash('korisnik/'.$this->seller->slug.'/ocene'))->assertOk()
            ->assertSee('Ocene kupaca: Petar Prodavac')->assertSee('noindex', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_seller_replies_and_reports_others_cannot(): void
    {
        $this->rate($this->conversation(), 1, 'Nije se javio.');
        $rating = SellerRating::query()->sole();

        $this->actingAs($this->buyer)->post(url_slash('ocene/'.$rating->id.'/odgovor'), ['reply' => 'Hak'])->assertNotFound();
        $this->actingAs($this->seller)->post(url_slash('ocene/'.$rating->id.'/odgovor'), ['reply' => 'Javio sam se dva puta.'])->assertSessionHas('status');
        $this->actingAs($this->seller)->post(url_slash('ocene/'.$rating->id.'/prijava'), ['reason' => 'Nismo se dogovarali.'])->assertSessionHas('status');

        $rating->refresh();
        $this->assertSame('Javio sam se dva puta.', $rating->reply);
        $this->assertNotNull($rating->reported_at);
        $this->get(url_slash('korisnik/'.$this->seller->slug.'/ocene'))->assertSee('Odgovor prodavca')->assertSee('Javio sam se dva puta.');
    }

    public function test_hidden_rating_leaves_the_average(): void
    {
        $this->rate($this->conversation(), 1);
        $rating = SellerRating::query()->sole();

        app(SellerRatings::class)->setVisible($rating, false, User::factory()->admin()->create());

        $this->assertSame(0, $this->seller->fresh()->rating_count);
        $this->assertNull($this->seller->fresh()->rating_avg);
        $this->assertNull($rating->fresh()->reported_at);
        $this->get(url_slash('korisnik/'.$this->seller->slug.'/ocene'))->assertSee('Još nema ocena');
    }

    public function test_api_flow(): void
    {
        $conversation = $this->conversation();
        Sanctum::actingAs($this->buyer);

        $this->getJson('/api/v1/conversations/'.$conversation->id.'/rating')->assertOk()
            ->assertJson(['can_rate' => true, 'reason' => null, 'rating' => null]);
        $this->putJson('/api/v1/conversations/'.$conversation->id.'/rating', ['score' => 9])->assertStatus(400);
        $this->putJson('/api/v1/conversations/'.$conversation->id.'/rating', ['score' => 4, 'comment' => 'OK'])->assertCreated()
            ->assertJsonPath('score', 4)->assertJsonPath('rater_name', 'Marko K.')->assertJsonPath('can_reply', false);
        $this->putJson('/api/v1/conversations/'.$conversation->id.'/rating', ['score' => 5])->assertOk()->assertJsonPath('score', 5);

        $this->getJson('/api/v1/users/'.$this->seller->id.'/ratings')->assertOk()
            ->assertJsonPath('summary.count', 1)->assertJsonPath('summary.average', 5)->assertJsonPath('items.0.comment', null)
            ->assertJsonPath('next_page', null);
        $this->getJson('/api/v1/listings/'.$this->listing->id)->assertOk()->assertJsonPath('seller.rating', ['average' => 5, 'count' => 1]);

        $rating = SellerRating::query()->sole();
        $this->postJson('/api/v1/ratings/'.$rating->id.'/reply', ['text' => 'Hak'])->assertNotFound();
        Sanctum::actingAs($this->seller);
        $this->postJson('/api/v1/ratings/'.$rating->id.'/reply', ['text' => 'Hvala!'])->assertOk()->assertJsonPath('reply', 'Hvala!')->assertJsonPath('can_reply', true);
        $this->postJson('/api/v1/ratings/'.$rating->id.'/report', [])->assertStatus(400);
        $this->postJson('/api/v1/ratings/'.$rating->id.'/report', ['reason' => 'Lažna'])->assertStatus(202);
        $this->getJson('/api/v1/conversations/'.$conversation->id.'/rating')->assertOk()->assertJsonPath('can_rate', false);

        $this->getJson('/api/v1/conversations/'.$conversation->id.'/rating')->assertOk();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/conversations/'.$conversation->id.'/rating')->assertNotFound();
    }

    public function test_admin_sees_reported_ratings(): void
    {
        $this->rate($this->conversation(), 1);
        $this->signIn($this->admin())->get('/admin/seller-ratings')->assertOk()->assertSee('Petar Prodavac');
        $this->signIn($this->buyer)->get('/admin/seller-ratings')->assertForbidden();
    }

    public function test_deleting_the_rater_removes_the_rating_from_the_average(): void
    {
        $this->rate($this->conversation(), 5);
        $this->assertSame(1, $this->seller->fresh()->rating_count);

        AccountDeletion::delete($this->buyer);

        $this->assertSame(0, SellerRating::query()->count());
        $this->assertSame(0, $this->seller->fresh()->rating_count);
    }
}
