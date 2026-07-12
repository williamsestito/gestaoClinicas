<?php

declare(strict_types=1);

it('responds successfully on /up', function () {
    $this->get('/up')->assertOk();
});
