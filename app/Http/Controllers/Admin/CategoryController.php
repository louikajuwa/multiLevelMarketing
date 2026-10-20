<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryRepositoryInterface $categories) {}

    public function index(Request $request): mixed
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->categories->paginate($request->all(), ['parent']));
        }

        return view('admin.categories.index');
    }

    /** Searchable parent-category options. */
    public function parentOptions(Request $request): mixed
    {
        $term = (is_string($request->get('search')) ? trim($request->get('search')) : '');
        $q = Category::query();
        if ($term !== '') {
            $q->where('name', 'like', "%{$term}%");
        }

        return $this->paginate($q->orderBy('name')->paginate($this->optionLimit($request), ['id', 'name']));
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
        $this->guardParentCycle($category, $request->input('parent_id') ? (int) $request->input('parent_id') : null);

        $this->categories->update($category, [
            'name' => $request->string('name'),
            'parent_id' => $request->input('parent_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->ok($category, 'Category updated.');
    }

    /** Reject a parent that is the category itself or one of its descendants (walk up from the proposed parent). */
    private function guardParentCycle(Category $category, ?int $parentId): void
    {
        $steps = 0;
        while ($parentId !== null) {
            if ($parentId === $category->id || ++$steps > 100) {
                throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent or a descendant of itself.']);
            }
            $parentId = Category::whereKey($parentId)->value('parent_id');
        }
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
