<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Models\ReturnModel;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $returnService,
    ) {}

    public function index(Request $request): View
    {
        $query = ReturnModel::with(['order', 'supplier'])
            ->orderByDesc('created_at');

        if ($request->user()->isSupplier()) {
            $query->where('supplier_id', $request->user()->supplier_id);
        }

        if ($status = $request->query('status')) {
            $query->where('return_status', $status);
        } else {
            $query->where('return_status', ReturnStatus::Pending);
        }

        return view('returns.index', [
            'returns' => $query->paginate(25)->withQueryString(),
            'statusFilter' => $status,
        ]);
    }

    public function confirmReceived(ReturnModel $return, Request $request): RedirectResponse
    {
        if ($request->user()->isSupplier() && $return->supplier_id !== $request->user()->supplier_id) {
            abort(403);
        }

        $this->returnService->confirmReceived($return, $request->user());

        return redirect()
            ->route('returns.index')
            ->with('success', 'Return received confirmed.');
    }
}
