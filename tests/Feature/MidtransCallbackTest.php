<?php

it('registers the midtrans callback endpoint', function () {
    $this->postJson('/api/midtrans/callback', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'order_id',
            'status_code',
            'gross_amount',
            'signature_key',
            'transaction_status',
        ]);
});
