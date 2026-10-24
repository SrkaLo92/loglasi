<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Fields\Pages\EditField;
use App\Models\Field;
use App\Support\Catalog\CatalogDefinition;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class FieldAdminTest extends TestCase
{
    use ActsAsStaff;

    public function test_small_option_list_is_edited_in_the_repeater_with_a_parent(): void
    {
        $marka = Field::create(['key' => 'marka', 'label' => 'Marka', 'type' => 'select', 'filter' => 'select',
            'options' => [['value' => 'audi', 'label' => 'Audi'], ['value' => 'bmw', 'label' => 'BMW']]]);
        $model = Field::create(['key' => 'model', 'label' => 'Model', 'type' => 'select', 'filter' => 'select', 'depends_on' => 'marka',
            'options' => [['value' => 'audi-a4', 'label' => 'A4', 'parent' => 'audi']]]);
        $this->signIn($this->admin());

        Livewire::test(EditField::class, ['record' => $model->getRouteKey()])
            ->assertFormSet(['depends_on' => 'marka'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([['value' => 'audi-a4', 'label' => 'A4', 'parent' => 'audi']], $model->fresh()->options);
        $this->assertSame('marka', $model->fresh()->depends_on);
        $this->assertNull($marka->fresh()->depends_on);
    }

    public function test_long_option_list_is_edited_as_text(): void
    {
        (new CatalogDefinition)->apply();
        $model = Field::where('key', 'model_automobila')->first();
        $count = count($model->options);
        $this->signIn($this->admin());

        $form = Livewire::test(EditField::class, ['record' => $model->getRouteKey()]);
        $text = $form->get('data.options_text');
        $this->assertStringContainsString('audi-a4 | A4 | audi', $text);

        $form->set('data.options_text', $text."\nzastava-nova | Nova | zastava")->call('save')->assertHasNoFormErrors();
        $this->assertCount($count + 1, $model->fresh()->options);
        $this->assertContains(['value' => 'zastava-nova', 'label' => 'Nova', 'parent' => 'zastava'], $model->fresh()->options);

        Livewire::test(EditField::class, ['record' => $model->getRouteKey()])
            ->set('data.options_text', "audi-a4 | A4\n")
            ->call('save')
            ->assertHasErrors(['data.options_text']);
    }
}
