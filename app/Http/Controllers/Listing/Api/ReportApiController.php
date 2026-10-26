<?php

namespace App\Http\Controllers\Listing\Api;

use App\Enums\ReportStatus;
use App\Http\Api\ApiError;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Support\Listing\ReportReasons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /reports: report a listing or a user (requires login).
 * POST /listings/{id}/report: report a listing, guests too (as on the site).
 * GET /report-reasons: the reasons with their labels.
 */
class ReportApiController extends Controller
{
    public function reasons(): Response
    {
        return response()->json(['data' => collect(ReportReasons::ALL)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values()->all()]);
    }

    public function listing(Request $request, int $id): Response
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(ReportReasons::keys())],
            'details' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Izaberite razlog prijave.',
            'reason.in' => 'Izaberite razlog prijave.',
            'details.string' => 'Opis mora biti tekst.',
            'details.max' => 'Opis može imati najviše 1.000 znakova.',
        ]);

        $reporter = auth('sanctum')->user();
        $reporter = $reporter instanceof User ? $reporter : null;

        $listing = Listing::query()->find($id);
        if ($listing === null || Gate::forUser($reporter)->denies('view', $listing)) {
            throw ApiError::notFound('Oglas ne postoji ili više nije aktivan.');
        }
        if ($reporter !== null && $listing->user_id === $reporter->id) {
            throw ValidationException::withMessages(['reason' => 'Ne možete prijaviti sopstveni oglas.']);
        }

        $duplicate = $reporter !== null && Report::query()
            ->where('reporter_id', $reporter->id)
            ->where('reportable_type', $listing->getMorphClass())
            ->where('reportable_id', $listing->id)
            ->where('status', ReportStatus::Open)
            ->exists();

        if (! $duplicate) {
            Report::create([
                'reporter_id' => $reporter?->id,
                'reportable_type' => $listing->getMorphClass(),
                'reportable_id' => $listing->id,
                'reason' => $data['reason'],
                'details' => isset($data['details']) ? (trim(strip_tags($data['details'])) ?: null) : null,
                'status' => ReportStatus::Open,
            ]);
        }

        return response()->noContent();
    }

    public function store(Request $request): Response
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['listing', 'user'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(ReportReasons::keys())],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'target_type.required' => 'Obavezno polje.',
            'target_type.in' => 'Dozvoljene vrednosti su listing i user.',
            'target_id.required' => 'Obavezno polje.',
            'target_id.*' => 'Mora biti pozitivan ceo broj.',
            'reason.required' => 'Obavezno polje.',
            'reason.in' => 'Nepoznat razlog prijave.',
            'note.max' => 'Napomena može imati najviše 1.000 znakova.',
            'note.string' => 'Napomena mora biti tekst.',
        ]);

        /** @var User $reporter */
        $reporter = $request->user();
        $target = $this->target($data['target_type'], (int) $data['target_id'], $reporter);

        $exists = Report::query()
            ->where('reporter_id', $reporter->id)
            ->where('reportable_type', $target->getMorphClass())
            ->where('reportable_id', $target->getKey())
            ->where('status', ReportStatus::Open)
            ->exists();

        if (! $exists) {
            Report::create([
                'reporter_id' => $reporter->id,
                'reportable_type' => $target->getMorphClass(),
                'reportable_id' => $target->getKey(),
                'reason' => $data['reason'],
                'details' => isset($data['note']) ? (trim(strip_tags($data['note'])) ?: null) : null,
                'status' => ReportStatus::Open,
            ]);
        }

        return response()->noContent();
    }

    private function target(string $type, int $id, User $reporter): Listing|User
    {
        if ($type === 'listing') {
            $listing = Listing::query()->with('user')->find($id);
            if ($listing === null || Gate::forUser($reporter)->denies('view', $listing)) {
                throw ApiError::notFound('Oglas ne postoji ili više nije aktivan.');
            }
            if ($listing->user_id === $reporter->id) {
                throw ValidationException::withMessages(['target_id' => 'Ne možete prijaviti sopstveni oglas.']);
            }

            return $listing;
        }

        $user = User::query()->find($id);
        if ($user === null) {
            throw ApiError::notFound('Korisnik ne postoji.');
        }
        if ($user->id === $reporter->id) {
            throw ValidationException::withMessages(['target_id' => 'Ne možete prijaviti sopstveni nalog.']);
        }

        return $user;
    }
}
