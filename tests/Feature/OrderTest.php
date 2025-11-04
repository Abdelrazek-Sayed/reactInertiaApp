<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['stock_quantity' => 10]);
    $this->order = Order::factory()->create(['user_id' => $this->user->id]);
    $this->orderItem = OrderItem::factory()->create([
        'order_id' => $this->order->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => $this->product->price,
        'total_price' => $this->product->price,
    ]);
});

test('guests cannot access order management', function () {
    $this->get(route('orders.index'))->assertRedirect(route('login'));
    $this->get(route('orders.create'))->assertRedirect(route('login'));
    $this->post(route('orders.store'), [])->assertRedirect(route('login'));
    $this->get(route('orders.show', 1))->assertRedirect(route('login'));
    $this->post(route('orders.status', 1), [])->assertRedirect(route('login'));
    $this->delete(route('orders.destroy', 1))->assertRedirect(route('login'));
});

test('users can view their orders', function () {
    actingAs($this->user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Orders/Index')
            ->has('orders.data')
        );
});

test('users can create orders', function () {
    $orderData = [
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ],
        ],
    ];

    actingAs($this->user)
        ->post(route('orders.store'), $orderData)
        ->assertRedirect(route('orders.index'));

    $this->assertDatabaseHas('orders', ['user_id' => $this->user->id]);
    $this->assertDatabaseHas('order_items', ['product_id' => $this->product->id]);
    $this->assertEquals(8, $this->product->fresh()->stock_quantity);
});

test('users can update order status', function () {
    $newStatus = 'processing';

    actingAs($this->user)
        ->post(route('orders.status', $this->order), ['status' => $newStatus])
        ->assertRedirect(route('orders.show', $this->order));

    $this->assertDatabaseHas('orders', [
        'id' => $this->order->id,
        'status' => $newStatus,
    ]);
});

test('users can cancel their orders', function () {
    actingAs($this->user)
        ->delete(route('orders.destroy', $this->order))
        ->assertRedirect(route('orders.index'));

    $this->assertSoftDeleted($this->order);
    $this->assertEquals(10, $this->product->fresh()->stock_quantity);
});

test('order items can be managed', function () {
    // Add item to order
    actingAs($this->user)
        ->post(route('orders.items.add', $this->order), [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ])
        ->assertOk();

    // Update item quantity
    $item = $this->order->items()->first();
    actingAs($this->user)
        ->put(route('orders.items.update', [$this->order, $item]), [
            'quantity' => 2,
        ])
        ->assertOk();

    // Remove item
    actingAs($this->user)
        ->delete(route('orders.items.remove', [$this->order, $item]))
        ->assertOk();
});

test('order total is calculated correctly', function () {
    $product2 = Product::factory()->create(['price' => 50]);

    // Add two items to order
    $this->order->items()->create([
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => $this->product->price,
        'total_price' => $this->product->price * 2,
    ]);

    $this->order->items()->create([
        'product_id' => $product2->id,
        'quantity' => 1,
        'unit_price' => $product2->price,
        'total_price' => $product2->price,
    ]);

    $response = actingAs($this->user)
        ->get(route('orders.show', $this->order));

    $response->assertInertia(
        fn ($page) => $page->component('Orders/Show')
        ->where('order.subtotal', $this->product->price * 2 + $product2->price)
    );
});
