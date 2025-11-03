<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderItemController extends Controller
{
    /**
     * Add an item to an existing order.
     */
    public function addItem(Request $request, Order $order)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($order, $validated) {
            $product = Product::findOrFail($validated['product_id']);
            
            // Check if product is already in the order
            $existingItem = $order->items()
                ->where('product_id', $product->id)
                ->first();

            if ($existingItem) {
                // Update quantity if item exists
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $validated['quantity'],
                    'total_price' => $existingItem->unit_price * ($existingItem->quantity + $validated['quantity']),
                ]);
                $item = $existingItem;
            } else {
                // Create new order item
                $item = new OrderItem([
                    'product_id' => $product->id,
                    'quantity' => $validated['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $product->price * $validated['quantity'],
                    'product_details' => [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => $product->price,
                    ]
                ]);
                $order->items()->save($item);
            }

            // Update product stock
            $product->decrement('stock_quantity', $validated['quantity']);

            // Update order totals
            $this->updateOrderTotals($order);

            return response()->json([
                'message' => 'Item added to order successfully',
                'item' => $item->load('product')
            ]);
        });
    }

    /**
     * Update the specified order item.
     */
    public function updateItem(Request $request, Order $order, OrderItem $item)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        if ($item->order_id !== $order->id) {
            return response()->json([
                'message' => 'Item does not belong to this order'
            ], 422);
        }

        return DB::transaction(function () use ($order, $item, $validated) {
            $quantityDifference = $validated['quantity'] - $item->quantity;
            $product = $item->product;

            // Check if there's enough stock
            if ($quantityDifference > $product->stock_quantity) {
                return response()->json([
                    'message' => 'Not enough stock available',
                    'available' => $product->stock_quantity
                ], 422);
            }

            // Update item
            $item->update([
                'quantity' => $validated['quantity'],
                'total_price' => $item->unit_price * $validated['quantity']
            ]);

            // Update product stock
            if ($quantityDifference != 0) {
                $product->decrement('stock_quantity', $quantityDifference);
            }

            // Update order totals
            $this->updateOrderTotals($order);

            return response()->json([
                'message' => 'Item updated successfully',
                'item' => $item->load('product')
            ]);
        });
    }

    /**
     * Remove the specified item from order.
     */
    public function removeItem(Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            return response()->json([
                'message' => 'Item does not belong to this order'
            ], 422);
        }

        return DB::transaction(function () use ($order, $item) {
            // Restore product stock
            $product = $item->product;
            $product->increment('stock_quantity', $item->quantity);

            // Delete the item
            $item->delete();

            // Update order totals
            $this->updateOrderTotals($order);

            return response()->json([
                'message' => 'Item removed from order successfully'
            ]);
        });
    }

    /**
     * Update order totals based on items.
     */
    private function updateOrderTotals(Order $order)
    {
        $subtotal = $order->items()->sum('total_price');
        $shippingCost = 0; // You can implement shipping cost calculation
        $taxRate = 0.1; // 10% tax rate
        $tax = $subtotal * $taxRate;
        $total = $subtotal + $shippingCost + $tax;

        $order->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping_cost' => $shippingCost,
            'total' => $total,
        ]);
    }
}
