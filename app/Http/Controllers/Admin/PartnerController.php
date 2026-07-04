<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\StorePartnerRequest;
use App\Http\Requests\Partner\UpdatePartnerRequest;
use App\Models\Partner;
use App\Models\PartnerLevel;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Services\PartnerService;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function __construct(
        private readonly PartnerRepositoryInterface $partners,
        private readonly PartnerService $service,
    ) {}

    public function index(Request $request): mixed
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->partners->paginate($request->all(), ['user', 'level']));
        }

        return view('admin.partners.index', [
            'levels' => PartnerLevel::active()->orderBy('level')->get(['id', 'name', 'level']),
            'sponsors' => Partner::with('user:id,name')->get(['id', 'user_id', 'depth'])
                ->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->user->name.' (L'.$p->depth.')']),
        ]);
    }

    public function store(StorePartnerRequest $request): mixed
    {
        $partner = $this->service->register($request->validated());

        return $this->created($partner, 'Partner registered.');
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): mixed
    {
        return $this->ok($this->service->update($partner, $request->validated()), 'Partner updated.');
    }

    public function destroy(Partner $partner): mixed
    {
        if ($partner->children()->exists() || $partner->customers()->exists()) {
            return $this->fail('Cannot delete a partner that has downline partners or customers.', 422);
        }

        // Detach counts from ancestors, then remove partner + its user.
        $ancestorIds = $this->service->ancestorIds($partner);
        if ($ancestorIds !== []) {
            Partner::whereIn('id', $ancestorIds)->decrement('total_downline_count');
        }
        if ($partner->parent_id) {
            Partner::whereKey($partner->parent_id)->decrement('direct_referral_count');
        }

        $user = $partner->user;
        $partner->delete();
        $user?->delete();

        return $this->ok(null, 'Partner deleted.');
    }
}
