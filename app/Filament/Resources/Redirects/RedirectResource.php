<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Support\PathInput;
use App\Filament\Support\StaffAccess;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Redirects from old addresses (table `redirects`, checked before every request). */
class RedirectResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'preusmerenje';

    protected static ?string $pluralModelLabel = 'preusmerenja';

    protected static ?string $navigationLabel = 'Preusmerenja (301)';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            PathInput::make('from_path', 'Stara adresa')
                ->helperText('Putanja bez domena, npr. /stari-oglas/ ili /?p=123.')
                ->unique(ignoreRecord: true),
            PathInput::make('to_path', 'Nova adresa')
                ->helperText('Npr. /oglasi/automobili/.')
                ->different('from_path'),
            Select::make('status_code')
                ->label('Vrsta')
                ->options([301 => '301 trajno', 302 => '302 privremeno', 307 => '307 privremeno (isti metod)', 308 => '308 trajno (isti metod)'])
                ->default(301)
                ->required(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('from_path')->label('Stara adresa')->searchable()->wrap(),
                TextColumn::make('to_path')->label('Nova adresa')->searchable()->wrap(),
                TextColumn::make('status_code')->label('Kod')->badge()->color('gray'),
                TextColumn::make('hits')->label('Pogodaka')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('last_hit_at')->label('Poslednji pogodak')->dateTime('j. n. Y. H:i')->placeholder('—')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
