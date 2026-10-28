<?php

namespace App\Filament\Support;

use App\Models\AuditLog;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Izvoz (Excel)" header action: the table's rows with the current filters, search and sort,
 * as a CSV that Excel opens directly (UTF-8 BOM, semicolons). Admins only; every export is audited.
 *
 *   CsvExport::action('korisnici', ['ID' => fn ($u) => $u->id, 'Ime' => fn ($u) => $u->name])
 */
class CsvExport
{
    public const MAX_ROWS = 50000;

    /** @param  array<string, Closure(Model): mixed>  $columns */
    public static function action(string $name, array $columns, array $with = []): Action
    {
        return Action::make('export')
            ->label('Izvoz (Excel)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn () => (bool) auth()->user()?->isAdmin())
            ->action(function ($livewire) use ($name, $columns, $with): StreamedResponse {
                $query = $livewire->getFilteredSortedTableQuery()->with($with)->limit(self::MAX_ROWS);
                AuditLog::record('export.'.$name, null, ['rows' => (clone $query)->count()]);

                return response()->streamDownload(function () use ($query, $columns) {
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, array_keys($columns), ';');
                    foreach ($query->lazy(500) as $row) {
                        fputcsv($out, array_map(fn (Closure $get) => self::cell($get($row)), $columns), ';');
                    }
                    fclose($out);
                }, $name.'-'.now()->format('Y-m-d-Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    private static function cell(mixed $value): string
    {
        $value = match (true) {
            $value instanceof \BackedEnum && method_exists($value, 'label') => $value->label(),
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            is_bool($value) => $value ? 'da' : 'ne',
            default => (string) $value,
        };

        // Excel would run a value that starts with =, +, - or @ as a formula.
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
