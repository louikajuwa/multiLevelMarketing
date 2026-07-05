<?php

namespace App\Http\Controllers;

use App\Http\Requests\Commission\AdjustCommissionRequest;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Partner;
use App\Repositories\Contracts\CommissionRepositoryInterface;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function __construct(
        private readonly CommissionRepositoryInterface $commissions,
        private readonly CommissionService $service,
    ) {}

    public function index(Request $request): mixed
    {
        $user = $request->user();
        $params = $request->all();

        if (! $user->isStaff() && $user->partner) {
            $params['partner_id'] = $user->partner->id;
        }

        if ($request->expectsJson() || $request->ajax()) {
            $paginator = $this->commissions->paginate($params, ['partner.user', 'order']);

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
                    'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                    'earnings' => round((float) $this->earningsQuery($user)->sum('amount'), 2),
                ],
                'errors' => null,
            ]);
        }

        return view('commissions.index', [
            'isStaff' => $user->isStaff(),
            'partners' => $user->isStaff()
                ? Partner::with('user:id,name')->get(['id', 'user_id'])->map(fn (Partner $p) => ['id' => $p->id, 'name' => $p->user->name])
                : [],
        ]);
    }

    public function adjust(AdjustCommissionRequest $request): mixed
    {
        $partner = Partner::findOrFail($request->integer('partner_id'));
        $commission = $this->service->adjust(
            $partner,
            (float) $request->input('amount'),
            $request->string('note'),
            $request->user()->id,
            $request->input('order_id') ? Order::find($request->integer('order_id')) : null,
        );

        return $this->created($commission, 'Commission adjustment recorded.');
    }

    private function earningsQuery($user)
    {
        $q = Commission::query();
        if (! $user->isStaff() && $user->partner) {
            $q->where('partner_id', $user->partner->id);
        }

        return $q;
    }
}
