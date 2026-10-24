<?php

namespace App\Http\Controllers\Posting\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ListingImages;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** POST /media: one image for a listing; attached later through image_ids. */
class MediaController extends Controller
{
    public function __construct(private readonly ListingImages $images) {}

    public function store(Request $request): Response
    {
        $file = $request->file('file');
        if (! $file || is_array($file)) {
            throw ValidationException::withMessages(['file' => 'Pošaljite sliku u polju „file”.']);
        }

        $image = $this->images->upload($file, $request->user());

        return ApiResponse::json($request, $this->images->toApi($image->refresh()), ApiResponse::CACHE_PRIVATE, 201);
    }
}
