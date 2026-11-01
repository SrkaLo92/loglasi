<?php

namespace App\Filament\Resources\Info;

use App\Filament\Resources\Info\Pages\ManageInfoBusinesses;
use App\Filament\Support\StaffAccess;
use App\Models\Firm;
use App\Models\InfoBusiness;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/** Business directory of the info portal. "Pozvana" marks firms that got the invitation mail. */
class InfoBusinessResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = InfoBusiness::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Info';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'firma u imeniku';

    protected static ?string $pluralModelLabel = 'imenik firmi';

    protected static ?string $navigationLabel = 'Imenik firmi';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Naziv')->required()->maxLength(160),
            TextInput::make('category')->label('Delatnost')->maxLength(80)
                ->datalist(fn () => InfoBusiness::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all()),
            TextInput::make('address')->label('Adresa')->maxLength(160),
            Select::make('location_id')->label('Mesto iz liste')->options(fn () => Location::query()->orderBy('name')->pluck('name', 'id'))->searchable()->placeholder('—'),
            TextInput::make('place')->label('Mesto (prikaz)')->maxLength(80),
            TextInput::make('phone')->label('Telefoni (odvojeni sa ;)')->maxLength(120),
            TextInput::make('email')->label('Mejl')->email()->maxLength(190),
            TextInput::make('website')->label('Sajt')->url()->maxLength(255),
            TextInput::make('facebook')->label('Facebook')->url()->maxLength(255),
            TextInput::make('instagram')->label('Instagram')->url()->maxLength(255),
            TextInput::make('viber')->label('Viber broj')->tel()->maxLength(40),
            Textarea::make('opening_hours')->label('Radno vreme (svaki red posebno, npr. Pon–Pet 08–20)')->rows(3),
            Textarea::make('description')->label('Opis')->rows(3)->columnSpanFull(),
            Select::make('firm_id')->label('Povezan nalog firme na loglasi.rs')
                ->options(fn () => Firm::query()->orderBy('name')->pluck('name', 'id'))->searchable()->placeholder('—'),
            Select::make('status')->label('Status')->options(['listed' => 'Prikazana', 'hidden' => 'Skrivena'])->default('listed')->required(),
            Toggle::make('featured')->label('Istaknuta trajno (partner)'),
            DateTimePicker::make('featured_until')->label('Istaknuta do (plaćeno)')->seconds(false),
            FileUpload::make('gallery')->label('Galerija (do 6 slika)')->image()->multiple()->maxFiles(6)->reorderable()
                ->disk('public')->directory('info-firme')->maxSize(4096)->columnSpanFull(),
            TextInput::make('source')->label('Izvor podataka')->maxLength(255),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Naziv')->searchable()->wrap(),
                TextColumn::make('category')->label('Delatnost')->searchable()->placeholder('—'),
                TextColumn::make('place')->label('Mesto')->placeholder('—'),
                TextColumn::make('phone')->label('Telefon')->placeholder('—')->toggleable(),
                TextColumn::make('email')->label('Mejl')->placeholder('—')->toggleable(),
                IconColumn::make('firm_id')->label('Ima nalog')->boolean()->getStateUsing(fn (InfoBusiness $r) => $r->firm_id !== null),
                IconColumn::make('featured')->label('Istaknuta')->boolean()->getStateUsing(fn (InfoBusiness $r) => $r->isFeatured()),
                TextColumn::make('invited_at')->label('Pozvana')->date('j. n. Y.')->placeholder('—')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state) => $state === 'listed' ? 'Prikazana' : 'Skrivena')
                    ->color(fn (string $state) => $state === 'listed' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(['listed' => 'Prikazana', 'hidden' => 'Skrivena']),
                TernaryFilter::make('invited_at')->label('Pozvana')->nullable(),
                TernaryFilter::make('email')->label('Ima mejl')->nullable(),
            ])
            ->recordActions([
                Action::make('open')->label('Otvori')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (InfoBusiness $record) => $record->url(), true)->visible(fn (InfoBusiness $record) => $record->status === 'listed'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('invited')->label('Označi kao pozvane')->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()->modalDescription('Samo beleška da je firma dobila poziv. Mejl se ne šalje odavde.')
                    ->action(fn (Collection $records) => $records->each(fn (InfoBusiness $b) => $b->update(['invited_at' => now()]))),
                BulkAction::make('hide')->label('Sakrij')->icon(Heroicon::OutlinedEyeSlash)
                    ->action(fn (Collection $records) => $records->each(fn (InfoBusiness $b) => $b->update(['status' => 'hidden']))),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInfoBusinesses::route('/')];
    }
}
