<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\User;
use App\Services\Admin\SettingsSchema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Site settings: limits and rules, contact, payments, Google, home page and page texts. Admin only. */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Podešavanja';

    protected static ?string $title = 'Podešavanja';

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(SettingsSchema::load());
    }

    public function form(Schema $schema): Schema
    {
        $pages = SettingsSchema::pageTexts();
        $labels = SettingsSchema::pageLabels();

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Pravila')
                            ->icon(Heroicon::OutlinedScale)
                            ->schema([Section::make()->columns(2)->schema($this->fields(SettingsSchema::general()))]),
                        Tab::make('Plaćanja i kontakt')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Section::make('Kontakt')->columns(2)->schema($this->fields(SettingsSchema::contact())),
                                Section::make('Plaćanja')->columns(2)->schema($this->fields(SettingsSchema::payments())),
                                Section::make('Podaci za uplatu')
                                    ->description('Prikazuju se na Cenovniku. Ništa se ne izmišlja: prazno polje se ne prikazuje.')
                                    ->columns(2)
                                    ->schema($this->fields(SettingsSchema::payment())),
                            ]),
                        Tab::make('Google')
                            ->icon(Heroicon::OutlinedChartBar)
                            ->schema([Section::make()->columns(2)->schema($this->fields(SettingsSchema::google()))]),
                        Tab::make('Početna strana')
                            ->icon(Heroicon::OutlinedHome)
                            ->schema([Section::make()->columns(2)->schema($this->fields(SettingsSchema::home()))]),
                        Tab::make('Tekstovi strana')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Tabs::make('pages')->tabs(collect($pages)->map(fn ($fields, $page) => Tab::make($labels[$page] ?? $page)
                                    ->schema($this->fields($fields)))->values()->all()),
                            ]),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Sačuvaj podešavanja')->submit('save')->keyBindings(['mod+s']),
                    ])->sticky()->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $changes = SettingsSchema::save($state);

        Notification::make()
            ->title($changes === [] ? 'Nema izmena.' : 'Podešavanja su sačuvana.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return list<mixed>
     */
    private function fields(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $field) {
            $name = SettingsSchema::stateKey($key);
            $component = match ($field['type']) {
                'integer' => TextInput::make($name)->integer()->minValue(0)->maxValue(100000)->required(),
                'decimal' => TextInput::make($name)->numeric()->minValue(1)->maxValue(10000)->required(),
                'email' => TextInput::make($name)->email()->maxLength(255)->required(),
                'boolean' => Toggle::make($name)->inline(false),
                'select' => Select::make($name)->options($field['options'])->required()->native(false),
                'textarea' => Textarea::make($name)->rows(3)->maxLength(5000)->columnSpanFull(),
                'markdown' => MarkdownEditor::make($name)
                    ->toolbarButtons([['bold', 'link'], ['heading', 'bulletList', 'orderedList'], ['undo', 'redo']])
                    ->maxLength(60000)
                    ->columnSpanFull(),
                'list' => Repeater::make($name)->simple(TextInput::make('value')->required()->maxLength(500))->reorderable()->addActionLabel('Dodaj stavku')->columnSpanFull(),
                'items' => Repeater::make($name)
                    ->schema(collect($field['item_keys'])->map(fn ($k) => $this->itemField($k))->all())
                    ->columns(count($field['item_keys']) > 2 ? 3 : 2)
                    ->reorderable()
                    ->collapsible()
                    ->addActionLabel('Dodaj')
                    ->columnSpanFull(),
                default => TextInput::make($name)->maxLength(500),
            };

            $component->label($field['label']);
            if (! empty($field['help'])) {
                $component->helperText($field['help']);
            }

            $out[] = $component;
        }

        return $out;
    }

    private function itemField(string $key): mixed
    {
        return match ($key) {
            'uslov' => Select::make($key)
                ->label('Prikazuje se samo ako je aktivna usluga')
                ->options(fn () => Product::query()->orderBy('sort')->pluck('name', 'code')->all())
                ->placeholder('Uvek'),
            'tekst', 'odgovor' => Textarea::make($key)->label(ucfirst($key))->rows(3)->maxLength(3000)->required()->columnSpanFull(),
            'ikonica' => TextInput::make($key)->label('Ikonica')->helperText('image, badge, clock, grid, phone, chart...')->maxLength(32),
            default => TextInput::make($key)->label(ucfirst($key))->maxLength(500)->required(),
        };
    }
}
