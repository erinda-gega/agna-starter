<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * List orders, filterable by partner, status, and ordered_at date range.
     */
    public function index(Request $request): View
    {
        $startDate = $this->parseDate($request->string('start_date')->toString());
        $endDate = $this->parseDate($request->string('end_date')->toString());

        $orders = Order::query()
            ->with(['partner', 'pointOfSale', 'invoice'])
            ->when($request->filled('partner_id'), function ($query) use ($request) {
                $query->where('partner_id', $request->integer('partner_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($startDate, function ($query) use ($startDate) {
                $query->whereDate('ordered_at', '>=', $startDate);
            })
            ->when($endDate, function ($query) use ($endDate) {
                $query->whereDate('ordered_at', '<=', $endDate);
            })
            ->orderByDesc('ordered_at')
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'partners' => Partner::orderBy('name')->get(['id', 'name']),
            'statuses' => ['open', 'delivered', 'invoiced'],
            'selectedPartnerId' => $request->integer('partner_id') ?: null,
            'selectedStatus' => $request->string('status')->toString() ?: null,
            'startDate' => $startDate?->toDateString(),
            'endDate' => $endDate?->toDateString(),
        ]);
    }

    /**
     * Parse a "Y-m-d" filter value, ignoring anything malformed
     * rather than throwing (no validation layer on this controller).
     */
    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }
}
