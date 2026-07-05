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

    /** Lazy nodes: ?parent=<id> returns that partner's children; none returns roots. */
    public function nodes(Request $request): mixed
    {
        $user = $request->user();
        $scope = (! $user->isStaff() && $user->partner) ? $user->partner : null;

        if ($request->filled('parent')) {
            $parent = Partner::findOrFail($request->integer('parent'));
            if ($scope) {
                abort_unless($this->tree->canAccess($scope, $parent), 403);
            }

            return $this->ok($this->tree->children($parent));
        }

        return $this->ok($this->tree->roots($scope));
    }
}
