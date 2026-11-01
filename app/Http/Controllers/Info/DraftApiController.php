<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use App\Support\Info\InfoToday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Drafts for the editor. The news routine runs off the server, reads the local outlets and posts a
 * short summary in our own words with the source link; nothing is published here, every draft lands
 * in "Na čekanju". A link that is already known answers "duplicate" and creates nothing.
 */
class DraftApiController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = (string) config('info.drafts_token');
        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['error' => ['code' => 'unauthorized', 'message' => 'Neispravan token.']], 401);
        }

        $data = $request->validate([
            'kind' => ['nullable', 'in:post,water,road'],
            'type' => ['nullable', 'in:news,event,notice,story,guide'],
            'title' => ['required_unless:kind,water,road', 'nullable', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:3000'],
            'topic' => ['nullable', 'string', 'max:32'],
            'place' => ['nullable', 'string', 'max:80'],
            'source_name' => ['required', 'string', 'max:120'],
            'source_url' => ['required', 'url', 'max:700'],
            'event_starts_at' => ['nullable', 'date'],
            'event_ends_at' => ['nullable', 'date'],
            'event_venue' => ['nullable', 'string', 'max:160'],
            'starts_at' => ['required_if:kind,water,road', 'nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'needs_check' => ['nullable', 'boolean'],
        ]);

        // Water and road notices are facts from the utility: stored hidden until the editor shows them.
        if (in_array($data['kind'] ?? 'post', ['water', 'road'], true)) {
            $hash = sha1($data['kind'].'|'.InfoPost::normalizeUrl($data['source_url']).'|'.$data['starts_at']);
            $notice = InfoServiceNotice::query()->firstOrCreate(['external_hash' => $hash], [
                'kind' => $data['kind'],
                'place' => $data['place'] ?? null,
                'area' => $data['summary'],
                'starts_at' => Carbon::parse($data['starts_at'], 'Europe/Belgrade'),
                'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at'], 'Europe/Belgrade') : null,
                'source_name' => $data['source_name'],
                'source_url' => $data['source_url'],
                'published' => false,
            ]);

            return response()->json(['status' => $notice->wasRecentlyCreated ? 'created' : 'duplicate', 'id' => $notice->id], $notice->wasRecentlyCreated ? 201 : 200);
        }

        $hash = sha1(InfoPost::normalizeUrl($data['source_url']));
        if ($existing = InfoPost::query()->where('source_hash', $hash)->first()) {
            return response()->json(['status' => 'duplicate', 'id' => $existing->id]);
        }

        $post = InfoPost::query()->create([
            'type' => $data['type'] ?? 'news',
            'status' => 'pending',
            'origin' => 'automation',
            'title' => $data['title'],
            'summary' => $data['summary'],
            'topic' => isset($data['topic'], config('info.topics')[$data['topic']]) ? $data['topic'] : null,
            'place' => $data['place'] ?? null,
            'source_name' => $data['source_name'],
            'source_url' => $data['source_url'],
            'event_starts_at' => isset($data['event_starts_at']) ? Carbon::parse($data['event_starts_at'], 'Europe/Belgrade') : null,
            'event_ends_at' => isset($data['event_ends_at']) ? Carbon::parse($data['event_ends_at'], 'Europe/Belgrade') : null,
            'event_venue' => $data['event_venue'] ?? null,
            'needs_check' => (bool) ($data['needs_check'] ?? false),
        ]);

        return response()->json(['status' => 'created', 'id' => $post->id], 201);
    }

    public function today(): JsonResponse
    {
        abort_unless(config('info.enabled'), 404);

        return response()->json(['data' => InfoToday::get()]);
    }
}
