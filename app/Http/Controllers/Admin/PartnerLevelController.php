<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PartnerLevel\StorePartnerLevelRequest;
use App\Http\Requests\PartnerLevel\UpdatePartnerLevelRequest;
use App\Models\PartnerLevel;
use App\Repositories\Contracts\PartnerLevelRepositoryInterface;
use Illuminate\Http\Request;

class PartnerLevelController extends Controller
{
    public function __construct(private readonly PartnerLevelRepositoryInterface $levels) {}

    public function index(Request $request): mixed
    {
        if ($request->expectsJson() || $request->ajax()) {
            return $this->paginate($this->levels->paginate($request->all()));
        }

        return view('admin.partner-levels.index');
    }

    public function store(StorePartnerLevelRequest $request): mixed
    {
        $level = $this->levels->create($request->validated());

        return $this->created($level, 'Partner level created.');
    }

    public function update(UpdatePartnerLevelRequest $request, PartnerLevel $partnerLevel): mixed
    {
        $level = $this->levels->update($partnerLevel, $request->validated());

        return $this->ok($level, 'Partner level updated.');
    }

    public function destroy(PartnerLevel $partnerLevel): mixed
    {
        if ($partnerLevel->partners()->exists()) {
            return $this->fail('Cannot delete a level that still has partners assigned.', 422);
        }

        $this->levels->delete($partnerLevel);

        return $this->ok(null, 'Partner level deleted.');
    }
}
