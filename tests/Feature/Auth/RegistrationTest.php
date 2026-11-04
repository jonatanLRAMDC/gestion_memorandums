<?php

test('public registration is not available', function () {
    $this->get('/register')->assertNotFound();
});
