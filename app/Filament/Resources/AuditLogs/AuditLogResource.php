<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Support\StaffAccess;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** Audit log, read-only. */
class AuditLogResource extends Resource
{
    use StaffAccess;

    protected static bool $adminOnly = true;

    protected static bool $readOnly = true;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'zapis';

    protected static ?string $pluralModelLabel = 'dnevnik';

    protected static ?string $navigationLabel = 'Dnevnik';

    /** Readable names of the most common actions; others are shown as they are. */
    public const ACTIONS = [
        'admin.login' => 'Prijava u admin',
        'listing.approve' => 'Oglas odobren',
        'listing.reject' => 'Oglas odbijen',
        'listing.feature' => 'Oglas promovisan (besplatno)',
        'listing.expire' => 'Oglas istekao (admin)',
        'listing.unfeature' => 'Promocije skinute',
        'listing.bump' => 'Oglas podignut',
        'listing.update' => 'Oglas izmenjen',
        'listing.delete' => 'Oglas obrisan',
        'listing.restore' => 'Oglas vraćen',
        'user.verify_phone' => 'Telefon potvrđen',
        'user.unverify_phone' => 'Potvrda telefona skinuta',
        'user.block' => 'Nalog blokiran',
        'user.unblock' => 'Nalog odblokiran',
        'user.make_firm' => 'Nalog pretvoren u firmu',
        'user.make_private' => 'Nalog vraćen na privatno lice',
        'user.grant_biznis' => 'Biznis paket uključen (staro)',
        'user.revoke_biznis' => 'Biznis paket isključen (staro)',
        'firm.grant_package' => 'Paket firme dodeljen',
        'firm.free_year' => 'Prvih 30 firmi: besplatna godina',
        'firm.end_package' => 'Paket firme prekinut',
        'user.change_role' => 'Uloga promenjena',
        'user.update' => 'Nalog izmenjen',
        'firm.update' => 'Firma izmenjena',
        'order.paid' => 'Narudžbina plaćena',
        'order.activated' => 'Usluga uključena',
        'order.activation_failed' => 'Usluga nije uključena',
        'order.receipt' => 'Broj računa upisan',
        'order.refunded' => 'Novac vraćen',
        'order.cancelled' => 'Narudžbina otkazana (kupac)',
        'order.cancelled_by_admin' => 'Narudžbina otkazana (admin)',
        'order.auto_cancelled' => 'Narudžbina otkazana (rok istekao)',
        'order.mark_paid' => 'Narudžbina plaćena (staro)',
        'order.cancel' => 'Narudžbina otkazana (staro)',
        'report.resolve' => 'Prijava rešena',
        'report.dismiss' => 'Prijava odbačena',
        'settings.update' => 'Podešavanja izmenjena',
        'seo.sitemap_rebuild' => 'Sitemap napravljen',
    ];

    public static function actionLabel(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('Vreme')->dateTime('j. n. Y. H:i:s'),
            TextEntry::make('user.name')->label('Ko')->placeholder('Sistem'),
            TextEntry::make('action')->label('Akcija')->formatStateUsing(fn (string $state) => static::actionLabel($state).' ('.$state.')'),
            TextEntry::make('subject')->label('Na čemu')->state(fn (AuditLog $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : '—'),
            TextEntry::make('ip')->label('IP adresa')->placeholder('—'),
            KeyValueEntry::make('changes')
                ->label('Izmene')
                ->state(fn (AuditLog $r) => collect((array) $r->changes)->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->all())
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,name'))
            ->defaultSort('id', 'desc')
            ->paginationMode(PaginationMode::Simple)
            ->columns([
                TextColumn::make('created_at')->label('Vreme')->dateTime('j. n. Y. H:i'),
                TextColumn::make('user.name')->label('Ko')->placeholder('Sistem'),
                TextColumn::make('action')->label('Akcija')->formatStateUsing(fn (string $state) => static::actionLabel($state))->description(fn (AuditLog $r) => $r->action),
                TextColumn::make('subject')->label('Na čemu')->state(fn (AuditLog $r) => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : '—'),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Akcija')
                    ->options(fn () => Cache::remember('admin.audit.actions', 600, fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action')
                        ->mapWithKeys(fn ($a) => [$a => static::actionLabel($a)])->all()))
                    ->searchable(),
                SelectFilter::make('user_id')
                    ->label('Ko')
                    ->options(fn () => User::query()->whereIn('role', ['admin', 'moderator'])->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('subject_type')
                    ->label('Vrsta')
                    ->options([
                        'App\\Models\\Listing' => 'Oglas',
                        'App\\Models\\User' => 'Korisnik',
                        'App\\Models\\Firm' => 'Firma',
                        'App\\Models\\Order' => 'Narudžbina',
                        'App\\Models\\Report' => 'Prijava',
                        'App\\Models\\Category' => 'Kategorija',
                        'App\\Models\\Field' => 'Polje',
                        'App\\Models\\Product' => 'Usluga',
                        'App\\Models\\Setting' => 'Podešavanje',
                    ]),
                Filter::make('period')
                    ->label('Period')
                    ->schema([
                        DatePicker::make('from')->label('Od'),
                        DatePicker::make('until')->label('Do'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($w, $d) => $w->where('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($w, $d) => $w->where('created_at', '<', Carbon::parse($d)->addDay()))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
