<?php

test('halaman depan mengarahkan pengunjung ke halaman masuk', function () {
    $this->get('/')->assertRedirect(route('masuk'));
});
