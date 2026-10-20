<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Services\MlmTreeService;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    public function __construct(private readonly MlmTreeService $tree) {}

    public function index(): mixed
    {
        return view('tree.index');
    }

    /**
     * Lazy, paged nodes:
     *   ?q=term   -> flat paged search hits
     *   ?parent=n -> that partner's children (paged)
     *   (none)    -> roots (paged)
     */
    public function nodes(Request $request): mixed
    {
        $user = $request->user();
        $scope = (! $user->isStaff() && $user->partner) ? $user->partner : null;
        $page = max(1, $request->integer('page', 1));
        $term = trim((string) $request->get('q', ''));

        if ($term !== '') {
            return $this->paged($this->tree->search($term, $scope, $page));
        }

        if ($request->filled('parent')) {
            $parent = Partner::findOrFail($request->integer('parent'));
            if ($scope) {
                abort_unless($this->tree->canAccess($scope, $parent), 403);
            }

            return $this->paged($this->tree->children($parent, $page));
        }

        return $this->paged($this->tree->roots($scope, $page));
    }

    /** Wrap a service ['data'=>…, 'meta'=>…] result in the standard JSON envelope. */
    private function paged(array $result): mixed
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $result['data'],
            'meta' => $result['meta'],
            'errors' => null,
        ]);
    }
}
