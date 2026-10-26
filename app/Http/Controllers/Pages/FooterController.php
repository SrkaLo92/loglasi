<?php

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use App\Support\Home\FooterSections;
use Illuminate\Http\JsonResponse;

/** Newest listings for the footer column, loaded by the page after it renders. */
class FooterController extends Controller
{
    public function latest(): JsonResponse
    {
        return response()->json(['data' => FooterSections::latest()])
            ->header('Cache-Control', 'public, max-age=60')
            ->header('X-Robots-Tag', 'noindex');
    }
}
