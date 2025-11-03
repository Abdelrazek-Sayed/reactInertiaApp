<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Product::class);
        
        $products = Product::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
            })
            ->when(request('active'), function ($query, $active) {
                $query->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 15));

        return ProductResource::collection($products);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sku' => 'required|string|unique:products,sku',
            'stock_quantity' => 'required|integer|min:0',
            'is_active' => 'boolean'
        ]);

        $product = Product::create($validated);

        return new ProductResource($product);
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);
        return new ProductResource($product);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'sku' => 'sometimes|string|unique:products,sku,' . $product->id,
            'stock_quantity' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        $product->update($validated);

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        DB::transaction(function () use ($product) {
            // Check if product is in any orders
            if ($product->orderItems()->exists()) {
                $product->update(['is_active' => false]);
                return response()->json([
                    'message' => 'Product deactivated because it exists in orders',
                    'product' => new ProductResource($product)
                ]);
            }

            $product->delete();
        });

        return response()->noContent();
    }
}
