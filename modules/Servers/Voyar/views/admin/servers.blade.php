@extends('admin.layouts.app')
@section('title', __('voyar::voyar.servers.title'))
@section('content')
@php
    $rows = collect($remote)->filter(fn ($r) => $showEnded || ! in_array($r['status'] ?? '', ['terminated', 'cancelled'], true))->values();
    $ip = fn ($r) => collect($r['ipaddresses'] ?? [])->firstWhere('type', 'ipv4')['ipaddress'] ?? collect($r['ipaddresses'] ?? [])->first()['ipaddress'] ?? '—';
@endphp
<div class="page-header">
    <h1>{{ __('voyar::voyar.servers.title') }}</h1>
    <div>
        <a href="{{ route('admin.voyar.setup') }}" class="btn btn-default btn-sm">{{ __('voyar::voyar.setup.title') }}</a>
        <a href="?ended={{ $showEnded ? 0 : 1 }}" class="btn btn-default btn-sm">{{ $showEnded ? __('voyar::voyar.servers.hide_ended') : __('voyar::voyar.servers.show_ended') }}</a>
    </div>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if(! $server)<div class="alert alert-warning">{{ __('voyar::voyar.setup.no_server') }}</div>@endif
@if($error)<div class="alert alert-danger">{{ __('voyar::voyar.setup.connect_failed') }}: {{ $error }}</div>@endif

<div class="card">
    <div class="card-header">{{ __('voyar::voyar.servers.health') }} ({{ count($issues) }})</div>
    <div class="card-body">
        @forelse($issues as $issue)
            <p style="margin:0 0 6px"><span class="badge badge-overdue">!</span>
                <a href="{{ route('admin.services.show', $issue['service']) }}">#{{ $issue['service']->id }} {{ $issue['service']->product?->name }}</a>
                ({{ $issue['service']->client?->company_name ?: trim(($issue['service']->client?->first_name ?? '').' '.($issue['service']->client?->last_name ?? '')) }}) — {{ $issue['text'] }}</p>
        @empty
            <p class="text-muted" style="margin:0">{{ __('voyar::voyar.servers.healthy') }}</p>
        @endforelse
    </div>
</div>

<div class="card">
    <div class="card-header">{{ __('voyar::voyar.servers.list') }} ({{ $rows->count() }})</div>
    <div class="card-body" style="padding:0">
        <table class="table data-table" style="margin:0">
            <thead><tr>
                <th>Voyar</th><th>{{ __('voyar::voyar.servers.hostname') }}</th><th>IP</th><th>{{ __('voyar::voyar.servers.type') }}</th>
                <th>{{ __('voyar::voyar.servers.status') }}</th><th>{{ __('voyar::voyar.servers.expires') }}</th><th>{{ __('voyar::voyar.servers.owner') }}</th>
            </tr></thead>
            <tbody>
            @foreach($rows as $r)
                @php $svc = $byId->get((string) $r['id']); @endphp
                <tr>
                    <td><code>{{ $r['id'] }}</code></td>
                    <td>{{ $r['hostname'] ?? '' }}<br><small class="text-muted">{{ $r['os']['name'] ?? '' }} · {{ $r['location']['name'] ?? '' }}</small></td>
                    <td>{{ $ip($r) }}</td>
                    <td>{{ ($r['type'] ?? '') === 'ondemand' ? __('voyar::voyar.panel.billing_types.ondemand') : __('voyar::voyar.panel.billing_types.prepaid') }}</td>
                    <td><span class="badge badge-{{ ($r['status'] ?? '') === 'active' ? 'active' : 'cancelled' }}">{{ $r['status'] ?? '' }}</span></td>
                    <td>{{ $r['expires_at'] ?? '—' }}</td>
                    <td>
                        @if($svc)
                            <a href="{{ route('admin.services.show', $svc) }}">#{{ $svc->id }}</a> · <a href="{{ route('admin.clients.show', $svc->client_id) }}">{{ $svc->client?->company_name ?: trim(($svc->client?->first_name ?? '').' '.($svc->client?->last_name ?? '')) }}</a>
                        @elseif(($r['status'] ?? '') === 'active' && ($r['type'] ?? '') === 'prepaid' && $products->isNotEmpty())
                            <form method="POST" action="{{ route('admin.voyar.servers.import') }}" style="display:flex;gap:6px;flex-wrap:wrap" onsubmit="return confirm(@js(__('voyar::voyar.servers.import_confirm')))">
                                @csrf
                                <input type="hidden" name="voyar_id" value="{{ $r['id'] }}">
                                <input type="number" name="client_id" class="form-control" placeholder="{{ __('voyar::voyar.servers.client_id') }}" required style="width:110px">
                                <select name="product_id" class="form-control" style="width:140px">
                                    @foreach($products as $p)<option value="{{ $p->id }}" @selected((string) ($p->config_options['package_name'] ?? '') === (string) ($r['pid'] ?? ''))>{{ $p->name }}</option>@endforeach
                                </select>
                                <button class="btn btn-primary btn-sm">{{ __('voyar::voyar.servers.import') }}</button>
                            </form>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
