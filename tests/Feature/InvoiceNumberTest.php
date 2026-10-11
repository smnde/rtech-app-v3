<?php

use App\Models\Sale;

test('invoice number starts at 01 and increments within the same day', function () {
    $this->travelTo('2026-10-11 09:00:00');

    $first = Sale::create(['subtotal' => 0, 'total' => 0, 'payment_status' => 'lunas']);
    $second = Sale::create(['subtotal' => 0, 'total' => 0, 'payment_status' => 'lunas']);

    expect($first->invoice)->toBe('INV-261011-01')
        ->and($second->invoice)->toBe('INV-261011-02');
});

test('invoice number resets to 01 on the next day', function () {
    $this->travelTo('2026-10-11 23:59:00');
    Sale::create(['invoice' => 'INV-261011-05', 'subtotal' => 0, 'total' => 0, 'payment_status' => 'lunas']);

    $this->travelTo('2026-10-12 00:01:00');

    expect(Sale::create(['subtotal' => 0, 'total' => 0, 'payment_status' => 'lunas'])->invoice)->toBe('INV-261012-01');
});
