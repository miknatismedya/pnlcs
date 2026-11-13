@extends('admin.layouts.app')
@section('title', __('paytr::paytr.admin.title'))
@section('content')
<div class="page-header"><h1>{{ __('paytr::paytr.admin.title') }}</h1></div>

<div class="card">
    <div class="card-header">{{ __('paytr::paytr.admin.mode') }}</div>
    <div class="card-body">
        {{ $settings['integration'] === 'direct' ? 'Direkt API' : 'iFrame API' }}
        · {{ $settings['test_mode'] ? __('paytr::paytr.admin.test_on') : __('paytr::paytr.admin.live') }}
        · {{ __('paytr::paytr.admin.recurring') }}: {{ $settings['recurring'] ? __('paytr::paytr.admin.on') : __('paytr::paytr.admin.off') }}
        · <a href="{{ url('/admin/config/gateways') }}">{{ __('paytr::paytr.admin.settings') }}</a>
    </div>
</div>

<div class="card">
    <div class="card-header">{{ __('paytr::paytr.admin.query') }}</div>
    <div class="card-body">
        <form method="GET" style="display:flex;gap:8px;max-width:520px">
            <input type="text" name="merchant_oid" value="{{ $oid }}" class="form-control" placeholder="INV123T2611140101">
            <button class="btn btn-primary">{{ __('paytr::paytr.admin.ask') }}</button>
        </form>
        <p class="text-muted" style="margin-top:6px">{{ __('paytr::paytr.admin.query_help') }}</p>
        @if($result)
            @if($result['paid'] ?? false)
                <table class="table" style="margin-top:12px">
                    @foreach(['payment_amount', 'payment_total', 'net_tutar', 'kesinti_tutari', 'currency', 'taksit', 'kart_marka', 'masked_pan', 'payment_date', 'test_mode'] as $k)
                        @isset($result['data'][$k])<tr><th style="width:200px">{{ $k }}</th><td>{{ is_scalar($result['data'][$k]) ? $result['data'][$k] : json_encode($result['data'][$k]) }}</td></tr>@endisset
                    @endforeach
                    @if(!empty($result['data']['returns']))<tr><th>returns</th><td><code>{{ json_encode($result['data']['returns'], JSON_UNESCAPED_UNICODE) }}</code></td></tr>@endif
                </table>
            @else
                <div class="alert alert-danger" style="margin-top:12px">{{ $result['message'] ?? '—' }}</div>
            @endif
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">{{ __('paytr::paytr.admin.rates') }}</div>
    <div class="card-body">
        @if($rates === [])
            <p class="text-muted">{{ __('paytr::paytr.admin.no_rates') }}</p>
        @else
            @php $max = collect($rates)->flatMap(fn ($r) => array_keys($r))->max(); @endphp
            <table class="table data-table">
                <thead><tr><th>{{ __('paytr::paytr.admin.brand') }}</th>@for($i = 2; $i <= $max; $i++)<th>{{ $i }}</th>@endfor</tr></thead>
                <tbody>
                @foreach($rates as $brand => $row)
                    <tr><td>{{ $brand }}</td>@for($i = 2; $i <= $max; $i++)<td>{{ isset($row[$i]) ? '%'.$row[$i] : '—' }}</td>@endfor</tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
