<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function fillInsuranceForm($component, string $category = 'Insurance')
{
    return $component
        ->set('name', 'Safe Harbor Agency')
        ->set('category', $category)
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'We protect families.');
}

it('saves multiple insurance types for an insurance listing', function () {
    $user = User::factory()->create();

    fillInsuranceForm(Livewire::actingAs($user)->test('add-business-modal'))
        ->set('insuranceTypes', ['auto', 'home', 'pet'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Business::first()->insurance_types)->toBe(['auto', 'home', 'pet']);
});

it('rejects unknown insurance types', function () {
    fillInsuranceForm(Livewire::actingAs(User::factory()->create())->test('add-business-modal'))
        ->set('insuranceTypes', ['boat'])
        ->call('save')
        ->assertHasErrors('insuranceTypes.0');
});

it('drops insurance types for non-insurance categories', function () {
    fillInsuranceForm(Livewire::actingAs(User::factory()->create())->test('add-business-modal'), 'Retail & Fashion')
        ->set('insuranceTypes', ['auto'])
        ->call('save');

    expect(Business::first()->insurance_types)->toBeNull();
});

it('loads and updates insurance types when editing', function () {
    $user = User::factory()->create();
    $business = Business::create([
        'user_id' => $user->id, 'name' => 'Safe Harbor', 'category' => 'Insurance',
        'location' => 'Atlanta, GA', 'city' => 'Atlanta', 'state' => 'GA',
        'description' => 'Cover.', 'insurance_types' => ['life'],
    ]);

    Livewire::actingAs($user)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertSet('insuranceTypes', ['life'])
        ->set('insuranceTypes', ['life', 'health', 'commercial'])
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->insurance_types)->toBe(['life', 'health', 'commercial']);
});

it('renders the insurance type checkboxes in the form', function () {
    Livewire::actingAs(User::factory()->create())->test('add-business-modal')
        ->assertSeeHtml('data-insurance-types-field')
        ->assertSee(['Auto', 'Home', 'Commercial', 'Life', 'Health', 'Pet']);
});

it('filters insurance listings by type only within the insurance category', function () {
    $owner = User::factory()->create();
    $make = fn (string $name, string $category, ?array $types) => Business::create([
        'user_id' => $owner->id, 'name' => $name, 'category' => $category,
        'location' => 'Atlanta, GA', 'description' => 'x', 'insurance_types' => $types,
    ]);
    $make('Auto Pros', 'Insurance', ['auto', 'home']);
    $make('Life Pros', 'Insurance', ['life']);
    $make('Other Shop', 'Retail & Fashion', ['auto']);

    Livewire::test('business-list')
        ->set('category', 'Insurance')
        ->assertSeeHtml('id="insurance-type-select"')
        ->set('insuranceType', 'auto')
        ->assertSee('Auto Pros')
        ->assertDontSee('Life Pros')
        ->set('category', 'All')
        ->assertDontSeeHtml('id="insurance-type-select"')
        ->assertSee('Life Pros')
        ->call('resetFilters')
        ->assertSet('insuranceType', 'any');
});

it('supports the Security & Protection Services category', function () {
    $owner = User::factory()->create();
    Business::create([
        'user_id' => $owner->id, 'name' => 'Guardian Security', 'category' => 'Security & Protection Services',
        'location' => 'Atlanta, GA', 'description' => 'Armed and unarmed guards.',
    ]);

    Livewire::test('business-list')
        ->assertSee('Security & Protection Services')
        ->set('category', 'Security & Protection Services')
        ->assertSee('Guardian Security');

    Livewire::actingAs($owner)->test('add-business-modal')
        ->assertSeeHtml('value="Security & Protection Services"');
});
