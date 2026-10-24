<?php

namespace App\Filament\Resources\Fields;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Filament\Resources\Fields\Pages\CreateField;
use App\Filament\Resources\Fields\Pages\EditField;
use App\Filament\Resources\Fields\Pages\ListFields;
use App\Filament\Resources\Fields\RelationManagers\CategoriesRelationManager;
use App\Filament\Support\Options;
use App\Filament\Support\StaffAccess;
use App\Models\Field;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/** Extra listing fields (brand, mileage, area...). */
class FieldResource extends Resource
{
    use StaffAccess;

    protected static bool $adminOnly = true;

    protected static ?string $model = Field::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'polje';

    protected static ?string $pluralModelLabel = 'polja';

    protected static ?string $navigationLabel = 'Dodatna polja';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        $hasOptions = fn (Get $get) => in_array($get('type'), [FieldType::Select->value, FieldType::Multiselect->value, FieldType::Select, FieldType::Multiselect], true);

        return $schema->components([
            Section::make('Polje')
                ->columns(2)
                ->schema([
                    TextInput::make('label')->label('Naziv')->required()->maxLength(255),
                    TextInput::make('key')
                        ->label('Ključ')
                        ->helperText('Za API i pretragu (f[ključ]=...). Mala slova, cifre i donja crta. Ne menjajte ga posle uvoza.')
                        ->required()->maxLength(64)
                        ->rule('regex:/^[a-z][a-z0-9_]*$/')
                        ->unique(ignoreRecord: true),
                    Select::make('type')->label('Vrsta')->options(Options::enum(FieldType::class))->required()->live(),
                    Select::make('filter')
                        ->label('Filter u pretrazi')
                        ->options(Options::enum(FieldFilter::class))
                        ->default(FieldFilter::None->value)
                        ->required(),
                    TextInput::make('unit')->label('Jedinica')->helperText('Na primer km, m², cm³, kW (uz kW se prikazuju i KS).')->maxLength(16),
                    Select::make('depends_on')
                        ->label('Zavisi od polja')
                        ->helperText('Na primer Model zavisi od Marke: nude se samo modeli izabrane marke. Svaka opcija tada ima roditelja.')
                        ->options(fn (?Field $record) => Field::query()->where('type', FieldType::Select->value)
                            ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                            ->orderBy('label')->pluck('label', 'key')->all())
                        ->visible($hasOptions)
                        ->live(),
                    TextInput::make('sort')->label('Redosled')->integer()->minValue(0)->default(0),
                ]),
            Section::make('Opcije')
                ->visible($hasOptions)
                ->schema([
                    Repeater::make('options')
                        ->label('Opcije izbora')
                        ->visible(fn (Get $get) => $get('options_text') === null)
                        ->schema([
                            TextInput::make('value')->label('Vrednost')->required()->maxLength(64)->rule('regex:/^[a-z0-9_-]+$/')->helperText('Mala slova, bez razmaka.'),
                            TextInput::make('label')->label('Naziv')->required()->maxLength(255),
                            Select::make('parent')
                                ->label('Roditelj')
                                ->visible(fn (Get $get) => filled($get('../../depends_on')))
                                ->options(fn (Get $get) => self::parentOptions($get('../../depends_on')))
                                ->searchable()
                                ->required(fn (Get $get) => filled($get('../../depends_on'))),
                        ])
                        ->columns(3)
                        ->reorderable()
                        ->addActionLabel('Dodaj opciju')
                        ->defaultItems(0),
                    // Long lists (car models) are edited as text, one option per line: the repeater would be too slow.
                    Textarea::make('options_text')
                        ->label('Opcije izbora (jedna po redu)')
                        ->helperText('Oblik: vrednost | Naziv | roditelj (roditelj samo kad polje zavisi od drugog). Primer: audi-a4 | A4 | audi')
                        ->visible(fn (Get $get) => $get('options_text') !== null)
                        ->rows(25),
                ]),
        ]);
    }

    /** Large option lists are shown as text (Filament repeater gets slow above this). */
    public const TEXT_OPTIONS_FROM = 150;

    /** @return array<string, string> */
    public static function parentOptions(?string $key): array
    {
        if (! $key) {
            return [];
        }

        return collect((array) Field::query()->where('key', $key)->value('options'))
            ->filter(fn ($o) => is_array($o) && isset($o['value']))
            ->mapWithKeys(fn ($o) => [(string) $o['value'] => (string) ($o['label'] ?? $o['value'])])->all();
    }

    /** @param  list<array<string, string>>  $options */
    public static function optionsToText(array $options): string
    {
        return collect($options)->map(fn ($o) => implode(' | ', array_filter([
            (string) ($o['value'] ?? ''), (string) ($o['label'] ?? ''), (string) ($o['parent'] ?? ''),
        ], 'strlen')))->implode("\n");
    }

    /**
     * @return list<array{value: string, label: string, parent?: string}>
     *
     * @throws ValidationException
     */
    public static function textToOptions(string $text, bool $withParent): array
    {
        $out = [];
        $seen = [];
        foreach (preg_split('/\R/u', $text) as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $value = $parts[0] ?? '';
            $label = $parts[1] ?? '';
            if (! preg_match('/^[a-z0-9_-]+$/', $value) || $label === '' || isset($seen[$value]) || ($withParent && ($parts[2] ?? '') === '')) {
                throw ValidationException::withMessages(['data.options_text' => 'Red '.($i + 1).' nije ispravan: „'.mb_substr($line, 0, 80).'”.']);
            }
            $seen[$value] = true;
            $out[] = ['value' => $value, 'label' => $label] + ($withParent ? ['parent' => $parts[2]] : []);
        }

        return $out;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('categories'))
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('label')->label('Naziv')->searchable()->sortable(),
                TextColumn::make('key')->label('Ključ')->searchable(),
                TextColumn::make('type')->label('Vrsta')->formatStateUsing(fn (FieldType $state) => $state->label()),
                TextColumn::make('filter')->label('Filter')->formatStateUsing(fn (FieldFilter $state) => $state->label()),
                TextColumn::make('unit')->label('Jedinica')->placeholder('—'),
                TextColumn::make('categories_count')->label('Kategorije'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Vrsta')->options(Options::enum(FieldType::class)),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [CategoriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFields::route('/'),
            'create' => CreateField::route('/create'),
            'edit' => EditField::route('/{record}/edit'),
        ];
    }
}
