<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->productData = [
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'sku' => 'TEST123',
        'stock_quantity' => 100,
        'is_active' => true,
    ];
});

test('guests cannot access product management', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
    $this->get(route('products.create'))->assertRedirect(route('login'));
    $this->post(route('products.store'), [])->assertRedirect(route('login'));
    $this->get(route('products.show', 1))->assertRedirect(route('login'));
    $this->get(route('products.edit', 1))->assertRedirect(route('login'));
    $this->put(route('products.update', 1), [])->assertRedirect(route('login'));
    $this->delete(route('products.destroy', 1))->assertRedirect(route('login'));
});

test('users can view products index', function () {
    $products = Product::factory()->count(3)->create();

    actingAs($this->user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Products/Index')
            ->has('products.data', 3)
        );
});

test('users can create products', function () {
    actingAs($this->user)
        ->post(route('products.store'), $this->productData)
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', ['name' => 'Test Product']);
});

test('product creation requires valid data', function () {
    actingAs($this->user)
        ->post(route('products.store'), [])
        ->assertSessionHasErrors(['name', 'price', 'sku']);
});

test('users can update products', function () {
    $product = Product::factory()->create();

    actingAs($this->user)
        ->put(route('products.update', $product), ['name' => 'Updated Name'] + $product->toArray())
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated Name']);
});

test('users can delete products', function () {
    $product = Product::factory()->create();

    actingAs($this->user)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    $this->assertSoftDeleted($product);
});
