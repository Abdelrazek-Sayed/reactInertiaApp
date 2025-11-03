<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = Order::with(['user', 'items.product'])
            ->latest()
            ->paginate(10);
            
        return Inertia::render('Orders/Index', [
            'orders' => $orders
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::where('is_active', true)
            ->select('id', 'name', 'price', 'stock_quantity', 'sku')
            ->get();
            
        return Inertia::render('Orders/Create', [
            'products' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'shipping_address' => 'required|string',
            'shipping_city' => 'required|string|max:100',
            'shipping_state' => 'required|string|max:100',
            'shipping_zip' => 'required|string|max:20',
            'shipping_country' => 'required|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            // Create order
            $order = new Order([
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'user_id' => auth()->id() ?? null,
                'status' => 'pending',
                'shipping_name' => $validated['customer_name'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_state' => $validated['shipping_state'],
                'shipping_zip' => $validated['shipping_zip'],
                'shipping_country' => $validated['shipping_country'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $subtotal = 0;
            $items = [];

            // Process order items
            foreach ($validated['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = $itemData['quantity'];
                $unitPrice = $product->price;
                $itemTotal = $unitPrice * $quantity;

                $items[] = new OrderItem([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $itemTotal,
                    'product_details' => [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => $product->price,
                    ]
                ]);

                $subtotal += $itemTotal;

                // Update product stock
                $product->decrement('stock_quantity', $quantity);
            }

            // Calculate order totals
            $shippingCost = 0; // You can implement shipping cost calculation
            $taxRate = 0.1; // 10% tax rate
            $tax = $subtotal * $taxRate;
            $total = $subtotal + $shippingCost + $tax;

            $order->fill([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'total' => $total,
            ]);

            $order->save();
            $order->items()->saveMany($items);

            return redirect()->route('orders.show', $order)
                ->with('success', 'Order created successfully.');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $order->load(['items.product', 'user']);
        
        return Inertia::render('Orders/Show', [
            'order' => $order
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $order->update(['status' => $validated['status']]);

        return back()->with('success', 'Order status updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        if ($order->status !== 'pending') {
            return back()->with('error', 'Only pending orders can be deleted.');
        }

        // Restore product stock
        foreach ($order->items as $item) {
            $product = $item->product;
            $product->increment('stock_quantity', $item->quantity);
        }

        $order->delete();

        return redirect()->route('orders.index')
            ->with('success', 'Order deleted successfully.');
    }
}
