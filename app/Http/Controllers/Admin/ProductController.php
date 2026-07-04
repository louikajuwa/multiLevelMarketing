<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    public function index(Request $request): mixed
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->products->paginate($request->all(), ['category', 'brand', 'primaryImage']));
        }

        return view('admin.products.index', [
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request): mixed
    {
        $product = $this->products->create($this->data($request));
        $this->storeImages($request, $product);

        return $this->created($product->load(['category', 'brand', 'primaryImage']), 'Product created.');
    }

    public function update(StoreProductRequest $request, Product $product): mixed
    {
        $this->products->update($product, $this->data($request));
        $this->storeImages($request, $product);

        return $this->ok($product->load(['category', 'brand', 'primaryImage']), 'Product updated.');
    }

    public function destroy(Product $product): mixed
    {
        if ($product->orderItems()->exists()) {
            return $this->fail('Cannot delete a product that appears on orders.', 422);
        }
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->path);
        }
        $this->products->delete($product);

        return $this->ok(null, 'Product deleted.');
    }

    /** @return array<string,mixed> */
    private function data(StoreProductRequest $request): array
    {
        return [
            'name' => $request->string('name'),
            'sku' => $request->string('sku'),
            'category_id' => $request->input('category_id') ?: null,
            'brand_id' => $request->input('brand_id') ?: null,
            'description' => $request->input('description'),
            'cost_price' => $request->input('cost_price'),
            'retail_price' => $request->input('retail_price'),
            'partner_discount' => $request->input('partner_discount') !== null && $request->input('partner_discount') !== ''
                ? $request->input('partner_discount') : null,
            'stock' => $request->integer('stock'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function storeImages(Request $request, Product $product): void
    {
        $files = $request->file('images', []);
        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $order = (int) $product->images()->max('sort_order');

        foreach ($files as $file) {
            $order++;
            $product->images()->create([
                'path' => $file->store('products', 'public'),
                'is_primary' => ! $hasPrimary,
                'sort_order' => $order,
            ]);
            $hasPrimary = true;
        }
    }
}
