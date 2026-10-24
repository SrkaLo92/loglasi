<?php

namespace Tests\Feature\Seo;

use App\Support\Settings;
use Tests\TestCase;

class GoogleTagTest extends TestCase
{
    public function test_google_tag_loads_only_in_production_with_consent_denied_first(): void
    {
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com', false)->assertDontSee('id="lg-kolacici"', false);

        $this->app['env'] = 'production';

        $this->get('/')->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=GT-MKTJD4NV', false)
            ->assertSee("gtag('consent', 'default', {analytics_storage: 'denied'", false)
            ->assertSee('id="lg-kolacici"', false)
            ->assertSee('Prihvatam');
    }

    public function test_tag_id_comes_from_settings_and_must_be_valid(): void
    {
        $this->app['env'] = 'production';

        Settings::set('google_tag_id', 'G-ABC1234');
        $this->get('/')->assertSee('gtag/js?id=G-ABC1234', false);

        Settings::set('google_tag_id', '"><script>x</script>');
        $this->get('/')->assertDontSee('googletagmanager.com', false)->assertDontSee('<script>x</script>', false);

        Settings::set('google_tag_id', '');
        $this->get('/')->assertDontSee('googletagmanager.com', false);
    }

    public function test_search_console_verification_meta_from_settings(): void
    {
        $this->get('/')->assertDontSee('google-site-verification', false);

        Settings::set('google_site_verification', 'abc123XYZ');
        $this->get('/')->assertSee('<meta name="google-site-verification" content="abc123XYZ">', false);
    }
}
