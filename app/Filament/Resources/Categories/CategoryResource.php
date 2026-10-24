<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Categories\RelationManagers\ChildrenRelationManager;
use App\Filament\Resources\Categories\RelationManagers\FieldsRelationManager;
use App\Filament\Support\Options;
use App\Filament\Support\SlugInput;
use App\Filament\Support\StaffAccess;
use App\Models\Category;
use App\Services\Catalog;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CategoryResource extends Resource
{
    use StaffAccess;

    protected static bool $adminOnly = true;

    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Katalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'kategorija';

    protected static ?string $pluralModelLabel = 'kategorije';

    protected static ?string $navigationLabel = 'Kategorije';

    protected static ?string $recordTitleAttribute = 'name';

    /** Deleting a category with listings is blocked by the database; children move to the root. */
    public static function canDelete(Model $record): bool
    {
        return static::writable() && ! $record->listings()->withTrashed()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kategorija')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Naziv')->required()->maxLength(255)->live(onBlur: true)->afterStateUpdated(SlugInput::fillFrom()),
                    SlugInput::make('Javna adresa /oglasi/{slug}/. Promena menja adresu kategorije (dodajte preusmerenje sa stare).'),
                    Select::make('parent_id')
                        ->label('Nadređena kategorija')
                        ->placeholder('Glavna kategorija')
                        ->options(function (?Category $record) {
                            $options = Options::categories();
                            if ($record !== null) {
                                foreach (app(Catalog::class)->descendantIds($record) as $id) {
                                    unset($options[$id]);
                                }
                            }

                            return $options;
                        })
                        ->searchable(),
                    TextInput::make('sort')->label('Redosled')->integer()->minValue(0)->default(0),
                    Toggle::make('is_active')->label('Aktivna')->default(true)->helperText('Neaktivna kategorija se ne prikazuje na sajtu, ni njene potkategorije.'),
                    FileUpload::make('icon_path')
                        ->label('Ikonica')
                        ->helperText('SVG, PNG ili WebP, kvadrat.')
                        ->disk('public')
                        ->directory('kategorije')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/webp'])
                        ->maxSize(512),
                    Textarea::make('description')->label('Opis')->rows(3)->maxLength(2000)->columnSpanFull(),
                ]),
            Section::make('SEO')
                ->columns(1)
                ->schema([
                    TextInput::make('seo_title')
                        ->label('Naslov za pretraživače')
                        ->helperText('Do 60 znakova. Prazno = naslov po šablonu („{Fraza} Loznica – {broj} oglasa”).')
                        ->maxLength(255),
                    Textarea::make('seo_description')
                        ->label('Opis za pretraživače')
                        ->helperText('Do 155 znakova. Prazno = opis po šablonu.')
                        ->rows(2)
                        ->maxLength(320),
                    TextInput::make('seo_h1')
                        ->label('Naslov strane (H1)')
                        ->helperText('Prazno = naziv kategorije.')
                        ->maxLength(255),
                    Textarea::make('seo_intro')
                        ->label('Uvodni tekst iznad oglasa')
                        ->helperText('Kratak tekst za kupce i pretraživače. Prazno = bez uvoda.')
                        ->rows(4)
                        ->maxLength(3000),
                    Textarea::make('seo_faq')
                        ->label('Česta pitanja')
                        ->helperText('Pitanje u jednom redu, odgovor u sledećem; između parova prazan red.')
                        ->rows(8)
                        ->maxLength(10000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $catalog = app(Catalog::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent')->withCount('children'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('icon')
                    ->label('')
                    ->state(fn (Category $record) => $record->iconUrl())
                    ->imageSize(28),
                TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable()
                    ->description(fn (Category $record) => $record->parent ? 'u: '.$record->parent->name : null),
                TextColumn::make('slug')->label('Slug')->searchable()->toggleable(),
                TextColumn::make('children_count')->label('Potkategorije'),
                TextColumn::make('active_count')
                    ->label('Aktivni oglasi')
                    ->state(fn (Category $record) => $catalog->count($record)),
                IconColumn::make('is_active')->label('Aktivna')->boolean(),
                TextColumn::make('sort')->label('Redosled')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('parent')
                    ->label('Nivo')
                    ->options(fn () => ['root' => 'Glavne kategorije'] + Options::categories())
                    ->default('root')
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'root' => $query->whereNull('parent_id'),
                        default => $query->where('parent_id', (int) $data['value']),
                    }),
                TernaryFilter::make('is_active')->label('Aktivna'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            ChildrenRelationManager::class,
            FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
