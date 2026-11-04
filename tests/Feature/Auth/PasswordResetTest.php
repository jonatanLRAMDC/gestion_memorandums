<?php

test('password reset by email is not available', function () {
    $this->get('/forgot-password')->assertNotFound();
    $this->get('/reset-password/token')->assertNotFound();
});
