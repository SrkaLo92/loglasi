<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Admin\ListingImport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/** Import of a firm's listings from a CSV or Excel table: first a preview, then the import with a report. */
class ListingImportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Oglasi';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Uvoz oglasa';

    protected static ?string $title = 'Uvoz oglasa iz tabele';

    protected static ?string $slug = 'uvoz-oglasa';

    protected string $view = 'admin.pages.listing-import';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    /** @var array<string, mixed>|null */
    public ?array $report = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff();
    }

    public function mount(): void
    {
        $this->form->fill(['images' => true]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()->columns(2)->schema([
                    Select::make('user_id')
                        ->label('Oglasi se upisuju nalogu')
                        ->options(fn () => User::query()->orderBy('name')->limit(200)->pluck('name', 'id'))
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => User::query()
                            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                            ->orderBy('name')->limit(50)->pluck('name', 'id'))
                        ->required()
                        ->native(false),
                    Toggle::make('images')
                        ->label('Preuzmi slike sa adresa iz kolone „slike”')
                        ->inline(false),
                    FileUpload::make('file')
                        ->label('Tabela (CSV, XLSX)')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')
                        ->directory('uvoz')
                        ->preserveFilenames()
                        ->maxSize(20480)
                        ->required()
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public function previewTable(): void
    {
        $this->run(function (ListingImport $import, string $path, string $name, User $owner) {
            $this->report = null;
            $this->preview = $import->preview($path, $name, $owner);
            $bad = count(array_filter($this->preview['rows'], fn ($row) => $row['errors'] !== []));

            Notification::make()
                ->title('Pregled: '.count($this->preview['rows']).' redova, '.$bad.' sa greškom.')
                ->success()
                ->send();
        });
    }

    public function import(): void
    {
        $this->run(function (ListingImport $import, string $path, string $name, User $owner) {
            $this->preview = null;
            $this->report = $import->import($path, $name, $owner, (bool) ($this->data['images'] ?? true));

            Notification::make()
                ->title('Uvezeno oglasa: '.$this->report['created'].', preskočeno: '.$this->report['failed'].'.')
                ->success()
                ->send();
        });
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('previewTable'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label('Pregledaj tabelu')->outlined()->action('previewTable'),
            Action::make('import')->label('Uvezi oglase')->requiresConfirmation()
                ->modalDescription('Oglasi se upisuju odmah. Redovi sa greškom se preskaču i ostaju u izveštaju.')
                ->action('import'),
        ];
    }

    protected function getViewData(): array
    {
        return ['help' => app(ListingImport::class)->columnHelp()];
    }

    /** Runs one step with the uploaded file; the file stays until a new one is uploaded. */
    private function run(callable $step): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $file = is_array($state['file'] ?? null) ? (string) reset($state['file']) : (string) ($state['file'] ?? '');
        $owner = User::query()->find($state['user_id'] ?? null);

        if ($file === '' || ! Storage::disk('local')->exists($file) || ! $owner instanceof User) {
            Notification::make()->title('Izaberite nalog i tabelu.')->danger()->send();

            return;
        }

        try {
            $step(app(ListingImport::class), Storage::disk('local')->path($file), basename($file), $owner);
        } catch (Throwable $e) {
            Notification::make()->title('Tabela ne može da se pročita.')->body($e->getMessage())->danger()->send();
        }
    }
}
