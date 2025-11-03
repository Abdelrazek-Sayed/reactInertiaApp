<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Order::class);
        
        $orders = Order::query()
            ->when(!auth()->user()->isAdmin(), function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->when(request('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->with(['items', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 15));

        return OrderResource::collection($orders);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Order::class);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            // Create the order
            $order = Order::create([
                'user_id' => auth()->id(),
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Add items to the order
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Check if there's enough stock
                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Not enough stock for product: {$product->name}");
                }

                // Create order item
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $product->price * $item['quantity'],
                    'product_details' => [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => $product->price,
                    ]
                ]);

                // Update product stock
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // Calculate order totals
            $this->updateOrderTotals($order);

            return new OrderResource($order->load('items'));
        });
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);
        
        return new OrderResource($order->load(['items', 'items.product']));
    }

    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        DB::transaction(function () use ($order) {
            // Restore product stock
            foreach ($order->items as $item) {
                if ($product = $item->product) {
                    $product->increment('stock_quantity', $item->quantity);
                }
            }

            // Delete the order
            $order->delete();
        });

        return response()->noContent();
    }

    protected function updateOrderTotals(Order $order)
    {
        $subtotal = $order->items()->sum('total_price');
        $shippingCost = $this->calculateShipping($order);
        $tax = $this->calculateTax($subtotal);
        
        $order->update([
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax' => $tax,
            'total' => $subtotal + $shippingCost + $tax,
        ]);
        
        return $order;
    }
    
    protected function calculateShipping(Order $order)
    {
        // Implement your shipping calculation logic here
        // This is a simple example - you might want to calculate based on weight, location, etc.
        return $order->items->sum('total_price') > 100 ? 0 : 10;
    }
    
    protected function calculateTax($subtotal)
    {
        // Implement your tax calculation logic here
        // This is a simple example - you might want to calculate based on location, product type, etc.
        return $subtotal * 0.1; // 10% tax
    }
}
