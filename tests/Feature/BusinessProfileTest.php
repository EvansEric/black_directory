<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('requires a regular signed-in user to view the business profile', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get(route('profile'))->assertForbidden();
});

it('shows a regular user profile link above the current plan', function () {
    $user = User::factory()->create();

    $html = Livewire::actingAs($user)->test('navigation')->html();
    $profilePosition = strpos($html, route('profile'));
    $planPosition = strpos($html, 'data-current-plan');

    expect($profilePosition)->not->toBeFalse()
        ->and($planPosition)->not->toBeFalse()
        ->and($profilePosition)->toBeLessThan($planPosition);
});

it('does not show the profile link to admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)->test('navigation')
        ->assertDontSee(route('profile'), false)
        ->assertDontSee('Business Profile');
});

it('lists owned businesses and provides add and edit controls', function () {
    $owner = User::factory()->create();
    $owned = Business::create([
        'user_id' => $owner->id,
        'name' => 'My Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A local shop.',
    ]);
    Business::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Another Owner Shop',
        'category' => 'Dining & Food',
        'location' => 'Atlanta, GA',
        'description' => 'Another shop.',
        'is_verified' => true,
    ]);

    $this->actingAs($owner)->get(route('profile'))
        ->assertOk()
        ->assertSee('Manage your businesses')
        ->assertSee('Add Business')
        ->assertSee('My Shop')
        ->assertSee('Edit Business')
        ->assertSee("edit-business', { businessId: {$owned->id} }", false)
        ->assertDontSee('Another Owner Shop')
        ->assertSee('Pending verification')
        ->assertSee('Free plan');
});

it('lets regular users upload and remove an avatar below the profile link', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('navigation')
        ->assertSee('data-avatar-control', false)
        ->set('avatarUpload', UploadedFile::fake()->image('avatar.jpg'));

    $avatar = $user->fresh()->avatar;
    expect($avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($avatar);
    $component->assertSeeHtml('src="'.asset('storage/'.$avatar).'"');

    $component->call('removeAvatar');

    expect($user->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($avatar);
});

it('does not expose avatar editing to admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Livewire::actingAs($admin)
        ->test('navigation')
        ->assertDontSee('data-avatar-control', false)
        ->assertDontSee('Add avatar');
});
