<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryRepositoryInterface $categories) {}

    public function index(Request $request): mixed
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->categories->paginate($request->all(), ['parent']));
        }

        return view('admin.categories.index', [
            'parents' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCategoryRequest $request): mixed
    {
        $category = $this->categories->create([
            'name' => $request->string('name'),
            'slug' => Str::slug($request->string('name')).'-'.Str::lower(Str::random(5)),
            'parent_id' => $request->input('parent_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->created($category, 'Category created.');
    }

    public function update(StoreCategoryRequest $request, Category $category): mixed
    {
        abort_if((int) $request->input('parent_id') === $category->id, 422, 'A category cannot be its own parent.');

        $this->categories->update($category, [
            'name' => $request->string('name'),
            'parent_id' => $request->input('parent_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->ok($category, 'Category updated.');
    }

    public function destroy(Category $category): mixed
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return $this->fail('Cannot delete a category that has products or sub-categories.', 422);
        }
        $this->categories->delete($category);

        return $this->ok(null, 'Category deleted.');
    }
}
