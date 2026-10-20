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
use Illuminate\Support\Facades\DB;

class PartnerController extends Controller
{
    public function __construct(
        private readonly PartnerRepositoryInterface $partners,
        private readonly PartnerService $service,
    ) {}

    public function index(Request $request): mixed
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->partners->paginate([...$request->all(), '_viewer' => $request->user()], ['user', 'level']));
        }

        return view('admin.partners.index', [
            'levels' => PartnerLevel::active()->orderBy('level')->get(['id', 'name', 'level']),
        ]);
    }

    /** Searchable sponsor (upline) options for the register form. */
    public function sponsorOptions(Request $request): mixed
    {
        $term = (is_string($request->get('search')) ? trim($request->get('search')) : '');
        $q = Partner::query()->visibleTo($request->user())->with('user:id,name');
        if ($term !== '') {
            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        return $this->paginate($q->orderBy('id')->paginate($this->optionLimit($request), ['id', 'user_id', 'depth']));
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
        $blocked = DB::transaction(function () use ($partner): bool {
            // Lock the partner and its ancestors (consistent id order) so counters can't race.
            $ids = [...$this->service->ancestorIds($partner), $partner->id];
            sort($ids);
            Partner::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

            $locked = Partner::whereKey($partner->id)->first();
            if (! $locked || $locked->children()->exists() || $locked->customers()->exists()) {
                return true;
            }

            // Detach counts from ancestors, then remove partner + its user.
            $ancestorIds = $this->service->ancestorIds($locked);
            if ($ancestorIds !== []) {
                Partner::whereIn('id', $ancestorIds)->decrement('total_downline_count');
            }
            if ($locked->parent_id) {
                Partner::whereKey($locked->parent_id)->decrement('direct_referral_count');
            }

            $user = $locked->user;
            $locked->delete();
            $user?->delete();

            return false;
        });

        if ($blocked) {
            return $this->fail('Cannot delete a partner that has downline partners or customers.', 422);
        }

        return $this->ok(null, 'Partner deleted.');
    }
}
