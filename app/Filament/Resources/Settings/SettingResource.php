<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Support\StaffAccess;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every settings row as key/value, grouped by prefix (page_text, home, payment...).
 * Fallback for keys that have no field on the "Podešavanja" page (e.g. home page sections).
 * Values are JSON; a plain text is saved as a string.
 */
class SettingResource extends Resource
{
    use StaffAccess;

    protected static bool $adminOnly = true;

    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'podešavanje';

    protected static ?string $pluralModelLabel = 'sva podešavanja';

    protected static ?string $navigationLabel = 'Sva podešavanja (ključ/vrednost)';

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?string $slug = 'setting-keys';

    public static function prefix(string $key): string
    {
        return (string) preg_split('/[._]/', $key, 2)[0];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->label('Ključ')
                ->helperText('Na primer home.hero_title ili page_text.faq.naslov.')
                ->required()->maxLength(191)
                ->rule('regex:/^[a-z0-9_]+(\.[a-z0-9_]+)*$/')
                ->unique(ignoreRecord: true)
                ->disabledOn('edit'),
            Textarea::make('value')
                ->label('Vrednost')
                ->helperText('Običan tekst ili JSON (niz, objekat, broj, true/false).')
                ->rows(8)
                ->formatStateUsing(fn ($state) => is_string($state) ? $state : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                ->dehydrateStateUsing(fn (?string $state) => static::decode($state))
                ->maxLength(65000),
        ])->columns(1);
    }

    /** JSON when it parses as an array, object, number or boolean; otherwise the text itself. */
    public static function decode(?string $state): mixed
    {
        $state = (string) $state;
        $trim = trim($state);
        if ($trim === '') {
            return null;
        }
        if (preg_match('/^(\[|\{|-?\d|true$|false$|null$)/', $trim)) {
            $decoded = json_decode($trim, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $state;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->defaultGroup(Group::make('key')
                ->label('Grupa')
                ->getKeyFromRecordUsing(fn (Setting $r) => static::prefix($r->key))
                ->getTitleFromRecordUsing(fn (Setting $r) => static::prefix($r->key))
                ->collapsible())
            ->columns([
                TextColumn::make('key')->label('Ključ')->searchable()->fontFamily('mono'),
                TextColumn::make('value')
                    ->label('Vrednost')
                    ->state(fn (Setting $r) => is_string($r->value) ? $r->value : json_encode($r->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('updated_at')->label('Izmenjeno')->dateTime('j. n. Y. H:i'),
            ])
            ->filters([
                SelectFilter::make('prefix')
                    ->label('Grupa')
                    ->options(fn () => Setting::query()->pluck('key')->map(fn ($k) => static::prefix($k))->unique()->sort()->mapWithKeys(fn ($p) => [$p => $p])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->where('key', 'like', str_replace(['%', '_'], ['\%', '\_'], $data['value']).'%') : $query),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSettings::route('/')];
    }
}
