@extends('layouts.app')

@section('title', 'Orders - AGNA Distribution')

@section('content')
    <h1>Orders</h1>

    <form class="filters" method="GET" action="{{ url('/orders') }}">
        <label for="partner_id">Partner</label>
        <select name="partner_id" id="partner_id" onchange="this.form.submit()">
            <option value="">All partners</option>
            @foreach ($partners as $partner)
                <option value="{{ $partner->id }}" @selected($selectedPartnerId === $partner->id)>
                    {{ $partner->name }}
                </option>
            @endforeach
        </select>

        <label for="status">Status</label>
        <select name="status" id="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected($selectedStatus === $status)>
                    {{ ucfirst($status) }}
                </option>
            @endforeach
        </select>

        <label for="start_date">Ordered from</label>
        <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" onchange="this.form.submit()">

        <label for="end_date">Ordered to</label>
        <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" onchange="this.form.submit()">

        @if ($selectedPartnerId || $selectedStatus || $startDate || $endDate)
            <a href="{{ url('/orders') }}">Clear filters</a>
        @endif
    </form>

    <table>
        <thead>
            <tr>
                <th>Order #</th>
                <th>Ordered at</th>
                <th>Partner</th>
                <th>Point of sale</th>
                <th>Status</th>
                <th>Invoice</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>{{ $order->id }}</td>
                    <td>{{ $order->ordered_at->format('Y-m-d') }}</td>
                    <td>{{ $order->partner->name }}</td>
                    <td>{{ $order->pointOfSale->name }}</td>
                    <td><span class="status status-{{ $order->status }}">{{ $order->status }}</span></td>
                    <td>
                        @if ($order->status === 'invoiced' && $order->invoice)
                            <a href="{{ url('/invoices/'.$order->invoice->id) }}">View</a>
                        @else
                            &mdash;
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No orders match these filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination">
        {{ $orders->links() }}
    </div>
@endsection
