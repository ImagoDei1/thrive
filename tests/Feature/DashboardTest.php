<?php

use App\Models\User;

test('guests are redirected from home to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('guests cannot open a portal dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('home sends each role to its own portal', function (string $role, string $route) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route($route));
})->with([
    'admin'                     => ['admin', 'admin.dashboard'],
    'subscriber'                => ['subscriber', 'subscriber.dashboard'],
    'provider'                  => ['provider', 'provider.dashboard'],
]);
