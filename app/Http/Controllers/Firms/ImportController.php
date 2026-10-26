<?php

namespace App\Http\Controllers\Firms;

use App\Http\Controllers\Controller;
use App\Services\Admin\ListingImport;
use App\Support\Firms\FirmAccount;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/** /moj-nalog/firma/uvoz-oglasa/: a firm imports its listings from a CSV or Excel table. */
class ImportController extends Controller
{
    public function __construct(private readonly ListingImport $import) {}

    public function form(Request $request): View
    {
        return $this->page($request);
    }

    public function preview(Request $request): View
    {
        [$path, $name] = $this->file($request);

        try {
            return $this->page($request, preview: $this->import->preview($path, $name, $request->user()));
        } catch (Throwable $e) {
            return $this->page($request, error: $e->getMessage());
        }
    }

    public function store(Request $request): View
    {
        [$path, $name] = $this->file($request);

        try {
            return $this->page($request, report: $this->import->import($path, $name, $request->user(), $request->boolean('images', true)));
        } catch (Throwable $e) {
            return $this->page($request, error: $e->getMessage());
        }
    }

    /** @return array{0: string, 1: string} */
    private function file(Request $request): array
    {
        $request->validate(
            ['tabela' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx,xlsm'], 'images' => ['nullable', 'boolean']],
            [
                'tabela.required' => 'Izaberite tabelu sa oglasima.',
                'tabela.max' => 'Tabela može imati najviše 20 MB.',
                'tabela.mimes' => 'Tabela mora biti .csv ili .xlsx.',
            ],
        );
        $file = $request->file('tabela');

        return [(string) $file->getRealPath(), (string) $file->getClientOriginalName()];
    }

    private function page(Request $request, ?array $preview = null, ?array $report = null, ?string $error = null): View
    {
        $user = $request->user();
        $firm = FirmAccount::firmFor($user->loadMissing('firm'));
        Gate::authorize('update', $firm);

        Seo::get()->page('Uvoz oglasa')->noindex();

        return view('firms.account.import', [
            'user' => $user,
            'firm' => $firm,
            'help' => $this->import->columnHelp(),
            'preview' => $preview,
            'report' => $report,
            'error' => $error,
        ]);
    }
}
