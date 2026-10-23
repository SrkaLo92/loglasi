<?php

namespace Tests\Feature\Seo;

class TrailingSlashTest extends SeoTestCase
{
    public function test_path_without_slash_redirects_with_slash(): void
    {
        $this->get('/o-nama')->assertStatus(301)->assertTarget('/o-nama/');
    }

    public function test_query_is_preserved(): void
    {
        $this->get('/oglasi/automobili?cena-do=5000&utm_source=x')
            ->assertStatus(301)
            ->assertTarget('/oglasi/automobili/?cena-do=5000&utm_source=x');
    }

    public function test_head_request_is_redirected(): void
    {
        $this->call('HEAD', '/kontakt')->assertStatus(301);
    }

    public function test_post_is_not_redirected(): void
    {
        $this->assertNotSame(301, $this->post('/kontakt')->getStatusCode());
    }

    public function test_skipped_paths(): void
    {
        foreach (['/api/v1/listings', '/admin', '/admin/oglasi', '/up', '/sitemap.xml', '/robots.txt', '/slika.jpg'] as $path) {
            $this->assertNotSame(301, $this->get($path)->getStatusCode(), $path);
        }
    }

    public function test_path_with_slash_and_root_are_untouched(): void
    {
        $this->get('/')->assertOk();
        $this->assertNotSame(301, $this->get('/o-nama/')->getStatusCode());
    }
}
