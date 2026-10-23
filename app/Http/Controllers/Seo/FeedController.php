<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Support\Seo;
use App\Support\Seo\Url;
use Illuminate\Http\Response;
use XMLWriter;

/** RSS /feed/ sa najnovijim oglasima; 200 + X-Robots-Tag: noindex, van sitemapa. */
class FeedController extends Controller
{
    public function __invoke(): Response
    {
        $listings = Seo::activeListings()->with('location:id,name')
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit((int) config('seo.feed_items', 30))
            ->get(['id', 'slug', 'title', 'description', 'price', 'currency', 'location_id', 'published_at', 'created_at']);

        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->startElement('channel');
        $xml->writeElement('title', 'loglasi.rs – najnoviji oglasi');
        $xml->writeElement('link', Url::canonical('/'));
        $xml->writeElement('description', config('seo.tagline'));
        $xml->writeElement('language', 'sr');

        foreach ($listings as $listing) {
            $url = Url::canonical('/oglas/'.$listing->slug.'/');
            $xml->startElement('item');
            $xml->writeElement('title', $listing->title.' – '.$listing->priceLabel());
            $xml->writeElement('link', $url);
            $xml->writeElement('guid', $url);
            $xml->writeElement('description', Seo::excerpt((string) $listing->description, 300).($listing->location ? ' ('.$listing->location->name.')' : ''));
            $xml->writeElement('pubDate', ($listing->published_at ?? $listing->created_at)->toRssString());
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();

        return response($xml->outputMemory(), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }
}
