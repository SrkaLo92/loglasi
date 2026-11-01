<?php

namespace App\Filament\Resources\Info;

use App\Filament\Resources\Info\Pages\ManageInfoAdInquiries;
use App\Filament\Support\StaffAccess;
use App\Models\InfoAdInquiry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Advertising inquiries sent from the "Oglašavanje" page. */
class InfoAdInquiryResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = InfoAdInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = 'Info';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'upit za oglašavanje';

    protected static ?string $pluralModelLabel = 'upiti za oglašavanje';

    protected static ?string $navigationLabel = 'Upiti za oglašavanje';

    public static function getNavigationBadge(): ?string
    {
        $count = InfoAdInquiry::query()->whereNull('handled_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company')->label('Firma')->searchable()->wrap(),
                TextColumn::make('interest')->label('Zanima ih')->badge()->formatStateUsing(fn (?string $state) => InfoAdInquiry::INTERESTS[$state] ?? $state),
                TextColumn::make('phone')->label('Telefon')->copyable()->placeholder('—'),
                TextColumn::make('email')->label('Mejl')->copyable()->placeholder('—'),
                TextColumn::make('message')->label('Poruka')->limit(80)->wrap()->placeholder('—'),
                TextColumn::make('created_at')->label('Stiglo')->since()->sortable(),
                TextColumn::make('handled_at')->label('Rešeno')->dateTime('j. n. H:i')->placeholder('—'),
            ])
            ->filters([TernaryFilter::make('handled_at')->label('Rešeno')->nullable()->default(false)])
            ->recordActions([
                Action::make('handled')->label('Rešeno')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (InfoAdInquiry $r) => $r->handled_at === null)
                    ->action(fn (InfoAdInquiry $r) => $r->update(['handled_at' => now()])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInfoAdInquiries::route('/')];
    }
}
