<?php

declare(strict_types=1);

it('defaults to light for a visitor with no appearance cookie, regardless of the page rendered', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('class="dark"', false);
    $response->assertDontSeeText("const appearance = 'system';", false);
    $response->assertSeeText("const appearance = 'light';", false);
});

it('renders the dark class on <html> when the appearance cookie is dark', function () {
    $response = $this->withUnencryptedCookie('appearance', 'dark')->get('/');

    $response->assertOk();
    $response->assertSeeText("const appearance = 'dark';", false);
    $response->assertSee('class="dark"', false);
});

it('renders light (no dark class) when the appearance cookie is light', function () {
    $response = $this->withUnencryptedCookie('appearance', 'light')->get('/');

    $response->assertOk();
    $response->assertDontSee('class="dark"', false);
    $response->assertSeeText("const appearance = 'light';", false);
});

it('preserves the system cookie so the pre-hydration script can follow the OS theme when explicitly chosen', function () {
    $response = $this->withUnencryptedCookie('appearance', 'system')->get('/');

    $response->assertOk();
    $response->assertSeeText("const appearance = 'system';", false);
});
