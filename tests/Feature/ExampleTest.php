<?php

it('redirects guests to the private access screen', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
