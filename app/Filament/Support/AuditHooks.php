<?php

namespace App\Filament\Support;

use App\Models\AuditLog;
use App\Services\Catalog;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Every write from the panel's standard actions goes to the audit log.
 * Custom actions write through the admin services, which log by themselves.
 */
class AuditHooks
{
    /** Never written to the log. */
    public const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'updated_at', 'created_at'];

    public static function register(): void
    {
        CreateAction::configureUsing(fn (CreateAction $action) => $action->after(
            fn (Model $record) => AuditLog::record(static::action($record, 'create'), $record, static::attributes($record))
        ));

        EditAction::configureUsing(function (EditAction $action) {
            $before = [];
            $action
                ->before(function (Model $record) use (&$before) {
                    $before = $record->getOriginal();
                })
                ->after(function (Model $record, $livewire) use (&$before) {
                    // Pivot edits (category_field) skip model events.
                    if ($livewire instanceof RelationManager && $record->relationLoaded('pivot')) {
                        app(Catalog::class)->flush();
                        AuditLog::record(static::action($livewire->getOwnerRecord(), 'update_link'), $livewire->getOwnerRecord(), [
                            'related' => $record->getMorphClass().'#'.$record->getKey(),
                            'pivot' => static::clean($record->getRelation('pivot')->getAttributes()),
                        ]);

                        return;
                    }
                    $changes = static::diff($before, $record);
                    if ($changes !== []) {
                        AuditLog::record(static::action($record, 'update'), $record, $changes);
                    }
                });
        });

        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->after(
            fn (Model $record) => AuditLog::record(static::action($record, 'delete'), $record, static::attributes($record))
        ));

        RestoreAction::configureUsing(fn (RestoreAction $action) => $action->after(
            fn (Model $record) => AuditLog::record(static::action($record, 'restore'), $record)
        ));

        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action) => $action->after(
            fn (Collection $records) => $records->each(fn (Model $record) => AuditLog::record(static::action($record, 'delete'), $record, static::attributes($record)))
        ));

        RestoreBulkAction::configureUsing(fn (RestoreBulkAction $action) => $action->after(
            fn (Collection $records) => $records->each(fn (Model $record) => AuditLog::record(static::action($record, 'restore'), $record))
        ));

        // Category–field links: attach/detach skip model events, so the catalog cache is flushed here.
        AttachAction::configureUsing(fn (AttachAction $action) => $action->after(function (Model $record, $livewire) {
            app(Catalog::class)->flush();
            AuditLog::record(static::action($livewire->getOwnerRecord(), 'attach'), $livewire->getOwnerRecord(), [
                'related' => $record->getMorphClass().'#'.$record->getKey(),
            ]);
        }));

        DetachAction::configureUsing(fn (DetachAction $action) => $action->after(function (Model $record, $livewire) {
            app(Catalog::class)->flush();
            AuditLog::record(static::action($livewire->getOwnerRecord(), 'detach'), $livewire->getOwnerRecord(), [
                'related' => $record->getMorphClass().'#'.$record->getKey(),
            ]);
        }));

        DetachBulkAction::configureUsing(fn (DetachBulkAction $action) => $action->after(function (Collection $records, $livewire) {
            app(Catalog::class)->flush();
            AuditLog::record(static::action($livewire->getOwnerRecord(), 'detach'), $livewire->getOwnerRecord(), [
                'related' => $records->map(fn (Model $r) => $r->getMorphClass().'#'.$r->getKey())->values()->all(),
            ]);
        }));
    }

    /** "category.update", "redirect.delete"... */
    public static function action(Model $record, string $verb): string
    {
        return Str::snake(class_basename($record)).'.'.$verb;
    }

    /** @return array<string, mixed> */
    public static function attributes(Model $record): array
    {
        return static::clean($record->attributesToArray());
    }

    /**
     * Changed attributes as [old, new].
     *
     * @param  array<string, mixed>  $before
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function diff(array $before, Model $record): array
    {
        $out = [];
        foreach (static::clean($record->getChanges()) as $key => $new) {
            $out[$key] = [static::scalar($before[$key] ?? null), static::scalar($new)];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function clean(array $attributes): array
    {
        return array_map([static::class, 'scalar'], array_diff_key($attributes, array_flip(self::HIDDEN)));
    }

    private static function scalar(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
            is_string($value) && mb_strlen($value) > 500 => mb_substr($value, 0, 500).'…',
            default => $value,
        };
    }
}
