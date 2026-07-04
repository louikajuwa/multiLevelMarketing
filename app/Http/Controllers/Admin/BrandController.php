<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Models\Brand;
use App\Repositories\Contracts\BrandRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function __construct(private readonly BrandRepositoryInterface $brands) {}

    public function index(Request $request): mixed
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->brands->paginate($request->all()));
        }

        return view('admin.brands.index');
    }

    public function store(StoreBrandRequest $request): mixed
    {
        $brand = $this->brands->create([
            'name' => $request->string('name'),
            'slug' => Str::slug($request->string('name')).'-'.Str::lower(Str::random(5)),
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->created($brand, 'Brand created.');
    }

    public function update(StoreBrandRequest $request, Brand $brand): mixed
    {
        $this->brands->update($brand, [
            'name' => $request->string('name'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->ok($brand, 'Brand updated.');
    }

    public function destroy(Brand $brand): mixed
    {
        if ($brand->products()->exists()) {
            return $this->fail('Cannot delete a brand that has products.', 422);
        }
        $this->brands->delete($brand);

        return $this->ok(null, 'Brand deleted.');
    }
}
