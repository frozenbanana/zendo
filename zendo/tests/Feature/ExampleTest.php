<?php

test('home route is registered', function () {
    expect(route('home'))->not->toBeEmpty();
});

test('up endpoint returns ok', function () {
    $response = $this->get('/up');
    $response->assertOk();
});
