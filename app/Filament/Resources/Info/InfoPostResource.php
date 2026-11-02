<?php

namespace App\Filament\Resources\Info;

use App\Filament\Resources\Info\Pages\ManageInfoPosts;
use App\Filament\Support\StaffAccess;
use App\Models\InfoPost;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/** Info portal posts. Drafts from the automation and from citizens wait in "Na čekanju" until an editor publishes them. */
class InfoPostResource extends Resource
{
    use StaffAccess;

    protected static ?string $model = InfoPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Info';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'objava';

    protected static ?string $pluralModelLabel = 'objave';

    protected static ?string $navigationLabel = 'Objave i Na čekanju';

    public static function getNavigationBadge(): ?string
    {
        $count = InfoPost::query()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Objava')->columns(['default' => 1, 'md' => 3])->schema([
                Select::make('type')->label('Vrsta')->options(InfoPost::TYPES)->default('news')->required()->live(),
                Select::make('status')->label('Status')->options(InfoPost::STATUSES)->default('pending')->required(),
                Select::make('topic')->label('Tema')->options(config('info.topics'))->placeholder('—'),
                TextInput::make('title')->label('Naslov')->required()->maxLength(160)->columnSpanFull(),
                Textarea::make('summary')->label('Sažetak (našim rečima, 2 do 4 rečenice)')->required()->rows(4)->maxLength(3000)->columnSpanFull()
                    ->helperText('Nikad ne prepisujte tekst izvora. Prazan red pravi novi pasus.'),
                Textarea::make('body')->label('Ceo tekst (samo za naše priče i vodiče)')->rows(10)->columnSpanFull(),
            ]),
            Section::make('Mesto i izvor')->columns(['default' => 1, 'md' => 2])->schema([
                Select::make('location_id')->label('Mesto iz liste')->options(fn () => Location::query()->orderBy('name')->pluck('name', 'id'))->searchable()->placeholder('—'),
                TextInput::make('place')->label('Mesto (kako se prikazuje)')->maxLength(80),
                TextInput::make('source_name')->label('Izvor (naziv)')->maxLength(120),
                TextInput::make('source_url')->label('Link ka originalu')->url()->maxLength(700)
                    ->unique(ignoreRecord: true, column: 'source_url'),
            ]),
            Section::make('Događaj')->columns(['default' => 1, 'md' => 3])
                ->visible(fn (Get $get) => $get('type') === 'event')
                ->schema([
                    DateTimePicker::make('event_starts_at')->label('Počinje')->seconds(false)->required(fn (Get $get) => $get('type') === 'event'),
                    DateTimePicker::make('event_ends_at')->label('Završava se')->seconds(false)->after('event_starts_at'),
                    TextInput::make('event_venue')->label('Mesto održavanja')->maxLength(160),
                ]),
            Section::make('Slika')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('video_url')->label('Naš video (YouTube, Instagram, Facebook ili TikTok link)')->url()->maxLength(500)
                    ->helperText('YouTube se pušta na stranici, ostali dobijaju dugme. Video se ne čuva na našem serveru.'),
                FileUpload::make('image_path')->label('Slika (samo naša ili sa dozvolom)')->image()->disk('public')
                    ->directory('info')->visibility('public')->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)->imageEditor(false),
                TextInput::make('image_credit')->label('Foto (potpis)')->maxLength(120)->placeholder('npr. Stalex'),
            ]),
            Section::make('Prikaz')->columns(['default' => 1, 'md' => 3])->schema([
                Toggle::make('featured')->label('Glavna priča na početnoj'),
                Toggle::make('sponsored')->label('Sponzorisano')->live(),
                TextInput::make('sponsor_name')->label('Naziv firme')->visible(fn (Get $get) => (bool) $get('sponsored'))->required(fn (Get $get) => (bool) $get('sponsored')),
                TextInput::make('sponsor_url')->label('Link firme')->url()->visible(fn (Get $get) => (bool) $get('sponsored')),
                DateTimePicker::make('published_at')->label('Objavljeno (ili zakazano za)')->seconds(false),
            ]),
            Section::make('Poslao')->columns(['default' => 1, 'md' => 2])->collapsed()
                ->visible(fn (?InfoPost $record) => $record?->origin === 'citizen')
                ->schema([
                    TextInput::make('submitter_name')->label('Ime')->disabled(),
                    TextInput::make('submitter_email')->label('Mejl')->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => InfoPost::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning', 'published' => 'success', default => 'gray'
                    }),
                TextColumn::make('type')->label('Vrsta')->formatStateUsing(fn (string $state) => InfoPost::TYPES[$state] ?? $state),
                TextColumn::make('title')->label('Naslov')->searchable()->wrap()->limit(90)
                    ->description(fn (InfoPost $record) => Str::limit($record->summary, 140)),
                IconColumn::make('needs_check')->label('Proveriti')->boolean()->trueColor('danger')->toggleable(),
                TextColumn::make('origin')->label('Od')->formatStateUsing(fn (string $state) => InfoPost::ORIGINS[$state] ?? $state),
                TextColumn::make('source_name')->label('Izvor')->placeholder('—')->url(fn (InfoPost $record) => $record->source_url, true),
                TextColumn::make('event_starts_at')->label('Događaj')->dateTime('j. n. H:i')->placeholder('—')->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Stiglo')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(InfoPost::STATUSES)->default('pending'),
                SelectFilter::make('type')->label('Vrsta')->options(InfoPost::TYPES),
                SelectFilter::make('origin')->label('Od')->options(InfoPost::ORIGINS),
            ])
            ->recordActions([
                self::publishAction(),
                Action::make('reject')->label('Odbij')->icon(Heroicon::OutlinedXMark)->color('gray')
                    ->visible(fn (InfoPost $record) => $record->status === 'pending')
                    ->action(fn (InfoPost $record) => self::review($record, 'rejected')),
                Action::make('open')->label('Otvori')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->visible(fn (InfoPost $record) => $record->status === 'published')
                    ->url(fn (InfoPost $record) => $record->url(), true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('publish')->label('Objavi označene')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(fn (InfoPost $p) => self::review($p, 'published', false));
                        Notification::make()->title('Objavljeno: '.$records->count())->success()->send();
                    }),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')->label('Objavi')->icon(Heroicon::OutlinedCheck)->color('success')
            ->visible(fn (InfoPost $record) => $record->status !== 'published')
            ->action(fn (InfoPost $record) => self::review($record, 'published'));
    }

    private static function review(InfoPost $post, string $status, bool $notify = true): void
    {
        $post->status = $status;
        $post->reviewed_by = auth()->id();
        if ($status === 'published' && (! $post->published_at || $post->published_at->isPast())) {
            $post->published_at = $post->published_at ?? now();
        }
        $post->save();
        if ($notify) {
            Notification::make()->title($status === 'published' ? 'Objavljeno.' : 'Odbijeno.')->success()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageInfoPosts::route('/')];
    }
}
