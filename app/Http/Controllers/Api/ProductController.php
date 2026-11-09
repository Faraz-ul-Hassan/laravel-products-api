<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // GET /api/products?search=&is_active=&min_price=&max_price=&sort_by=&sort_dir=&per_page=
    public function index(Request $request): AnonymousResourceCollection
    {
        $sortable = ['id', 'name', 'price', 'stock', 'created_at'];

        $sortBy = in_array($request->query('sort_by'), $sortable, true) ? $request->query('sort_by') : 'id';
        $sortDir = $request->query('sort_dir') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max($request->integer('per_page', 10), 1), 100);

        $products = Product::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->query('search');
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->when($request->has('is_active'), fn ($query) =>
                    $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('min_price'), fn ($query) =>
                    $query->where('price', '>=', $request->query('min_price')))
            ->when($request->filled('max_price'), fn ($query) =>
                    $query->where('price', '<=', $request->query('max_price')))
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();


        return ProductResource::collection($products);
        // $products = Product::orderBy('id', 'desc')->paginate(10);

        // return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    // POST /api/products
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return (new ProductResource($product))
                ->response()
                ->setStatusCode(201);
        // return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    // GET /api/products/{product}
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
        // return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    // PUT/PATCH /api/products/{product}
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->refresh());
        // return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    // DELETE /api/products/{product}
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }
}
