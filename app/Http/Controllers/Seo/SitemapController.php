<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Support\Seo\Sitemap;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(private Sitemap $sitemap) {}

    public function index(): Response
    {
        return $this->xml('index');
    }

    public function part(string $part): Response
    {
        return $this->xml($part);
    }

    public function listings(int $page): Response
    {
        return $this->xml('oglasi-'.$page);
    }

    private function xml(string $name): Response
    {
        $xml = $this->sitemap->get($name);
        abort_if($xml === null, 404);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(int) config('seo.sitemap.max_age', 3600),
        ]);
    }
}
