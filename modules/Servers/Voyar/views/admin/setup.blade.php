@extends('admin.layouts.app')
@section('title', __('voyar::voyar.setup.title'))
@section('content')
@php
    $voyarProducts = $catalogue['products'] ?? [];
    $byId = collect($voyarProducts)->keyBy(fn ($p) => (string) $p['id']);
    $credit = $catalogue['account']['client']['credit'] ?? null;
    $cfg = fn ($p) => is_array($p->config_options) ? $p->config_options : (json_decode((string) $p->config_options, true) ?: []);
    $groupId = optional(\App\Models\ConfigOptionGroup::where('name', \Modules\Servers\Voyar\VoyarSetup::GROUP_NAME)->first())->id;
    $linked = $groupId ? \App\Models\ConfigOptionLink::where('group_id', $groupId)->pluck('product_id')->all() : [];
    $P = \Modules\Servers\Voyar\VoyarPricing::class;
    $rate = $P::rate();
    $money = fn (?float $v) => $v === null ? '—' : display_money_fmt($v);
    $pct = fn (float $ours, ?float $cost) => $cost ? (($ours / $cost - 1) * 100) : null;
    $vmProducts = $products->filter(fn ($p) => $p->server_type === 'voyar' && ($cfg($p)['voyar_kind'] ?? 'vm') === 'vm');
@endphp
<style>
    .vy-steps .card{margin-bottom:20px}.vy-steps .card-header{font-weight:700}
    .vy-num{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:50%;background:#5b4cf0;color:#fff;font-size:12px;margin-right:8px}
    .vy-checks{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px 16px;margin:8px 0 16px}
    .vy-checks label{display:flex;gap:8px;align-items:center;font-weight:400}
    .vy-ok{color:#16a34a;font-weight:700}.vy-bad{color:#e5484d;font-weight:700}
</style>

<div class="page-header"><h1>{{ __('voyar::voyar.setup.title') }}</h1><a href="{{ route('admin.voyar.servers') }}" class="btn btn-default btn-sm">{{ __('voyar::voyar.servers.title') }}</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="vy-steps">
{{-- 1. Connection --}}
<div class="card">
    <div class="card-header"><span class="vy-num">1</span>{{ __('voyar::voyar.setup.step_connect') }}</div>
    <div class="card-body">
        @if(! $server)
            <p>{{ __('voyar::voyar.setup.no_server') }}</p>
            <ol>
                @foreach(__('voyar::voyar.setup.server_steps') as $step)<li>{{ $step }}</li>@endforeach
            </ol>
            <a href="{{ url('/admin/config/servers') }}" class="btn btn-primary">{{ __('voyar::voyar.setup.go_servers') }}</a>
        @elseif($error)
            <p class="vy-bad">{{ __('voyar::voyar.setup.connect_failed') }}: {{ $error }}</p>
            <p>{{ __('voyar::voyar.setup.check_credentials') }}</p>
        @else
            <p class="vy-ok"><i class="fas fa-check-circle"></i> {{ __('voyar::voyar.setup.connected', ['email' => $server->username]) }}</p>
            @if($credit !== null)<p>{{ __('voyar::voyar.setup.balance') }}: <b>{{ $P::usd((float) $credit, 2) }}</b> @if($rate)<span class="text-muted">(≈ {{ $money($P::toShop((float) $credit)) }})</span>@endif</p>@endif
            @if($rate)
                <p class="text-muted">{{ __('voyar::voyar.setup.rate_line', ['rate' => number_format($rate, 2, ',', '.')]) }} <a href="{{ url('/admin/config/currencies') }}">{{ __('voyar::voyar.setup.rate_link') }}</a></p>
            @else
                <p class="vy-bad">{{ __('voyar::voyar.setup.no_rate') }}</p>
            @endif
            <form method="POST" action="{{ route('admin.voyar.setup.refresh') }}">@csrf<button class="btn btn-sm btn-default">{{ __('voyar::voyar.setup.refresh') }}</button></form>
        @endif
    </div>
</div>

@if($catalogue)
{{-- 2. Products --}}
<div class="card">
    <div class="card-header"><span class="vy-num">2</span>{{ __('voyar::voyar.setup.step_products') }}</div>
    <form class="card-body" method="POST" action="{{ route('admin.voyar.setup.mapping') }}">
        @csrf
        <p>{{ __('voyar::voyar.setup.products_text') }}</p>
        <table class="table">
            <thead><tr><th>{{ __('voyar::voyar.setup.our_product') }}</th><th>{{ __('voyar::voyar.setup.voyar_plan') }}</th><th>{{ __('voyar::voyar.setup.kind') }}</th><th>{{ __('voyar::voyar.setup.prices') }}</th></tr></thead>
            <tbody>
            @foreach($products as $product)
                @php
                    $c = $cfg($product);
                    $suggested = \Modules\Servers\Voyar\VoyarSetup::suggest($product, $voyarProducts);
                    $current = $byId->get((string) $suggested);
                    $ours = (float) ($c['hourly_rate'] ?? 0);
                    $theirs = (float) ($current['price']['ondemand'] ?? 0);
                @endphp
                <tr>
                    <td><b>{{ $product->name }}</b><br><small class="text-muted">{{ $product->group?->name }} @if(!empty($c['cpu']))· {{ $c['cpu'] }} vCPU / {{ $c['ram'] ?? '?' }} GB / {{ $c['disk'] ?? '?' }} GB @endif</small></td>
                    <td>
                        <select name="map[{{ $product->id }}]" class="form-control">
                            <option value="">— {{ __('voyar::voyar.setup.not_voyar') }} —</option>
                            @foreach($voyarProducts as $vp)
                            <option value="{{ $vp['id'] }}" @selected((string) $suggested === (string) $vp['id'])>{{ ($vp['product_group']['name'] ?? '') }} / {{ $vp['name'] }} ({{ implode(', ', (array) ($vp['features'] ?? [])) }})</option>
                            @endforeach
                        </select>
                        @if($suggested && empty($c['package_name']))<small class="text-muted">{{ __('voyar::voyar.setup.suggested') }}</small>@endif
                    </td>
                    <td>
                        <select name="kind[{{ $product->id }}]" class="form-control">
                            <option value="vm" @selected(($c['voyar_kind'] ?? 'vm') === 'vm')>{{ __('voyar::voyar.setup.kind_vm') }}</option>
                            <option value="ftp" @selected(($c['voyar_kind'] ?? '') === 'ftp' || $product->group?->slug === 'ftp-storage')>FTP</option>
                        </select>
                    </td>
                    <td style="font-size:12px;white-space:nowrap">
                        @if($current)
                            @php
                                $costM = $P::monthly((array) $current['price']); $costH = $P::hourly((array) $current['price']);
                                $oursM = (float) ($product->priceFor('monthly') ?? 0); $mM = $pct($oursM, $costM); $mH = $ours > 0 ? $pct($ours, $costH) : null;
                            @endphp
                            {{ __('voyar::voyar.setup.cost') }}: {{ $P::usd((float) $current['price']['prepaid']) }}/{{ __('voyar::voyar.setup.day') }} → <b>{{ $money($costM) }}</b>/{{ __('voyar::voyar.setup.month') }} · {{ $money($costH) }}/{{ __('voyar::voyar.setup.hour') }}<br>
                            @if($mM !== null && $oursM > 0){{ __('voyar::voyar.setup.margin_month') }}: <b class="{{ $mM > 0 ? 'vy-ok' : 'vy-bad' }}">%{{ number_format($mM, 0, ',', '.') }}</b> ({{ $money($oursM) }})@endif
                            @if($mH !== null) · {{ __('voyar::voyar.setup.margin') }}: <b class="{{ $mH > 0 ? 'vy-ok' : 'vy-bad' }}">%{{ number_format($mH, 0, ',', '.') }}</b>@endif
                        @else — @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <button class="btn btn-primary">{{ __('voyar::voyar.setup.save_mapping') }}</button>
    </form>
</div>

{{-- 3. Order options --}}
<div class="card">
    <div class="card-header"><span class="vy-num">3</span>{{ __('voyar::voyar.setup.step_options') }}</div>
    <form class="card-body" method="POST" action="{{ route('admin.voyar.setup.options') }}">
        @csrf
        <p>{{ __('voyar::voyar.setup.options_text') }}</p>

        <h5>{{ __('voyar::voyar.setup.opt.location') }}</h5>
        <div class="vy-checks">
            @foreach($catalogue['locations'] as $l)
            <label><input type="checkbox" name="location[]" value="{{ $l['id'] }}" checked> {{ $l['name'] }}</label>
            @endforeach
        </div>

        <h5>{{ __('voyar::voyar.setup.opt.os') }}</h5>
        <div class="vy-checks">
            @foreach($catalogue['families'] as $family)
                @foreach($family['operatingsystems'] ?? [] as $os)
                <label><input type="checkbox" name="os[]" value="{{ $os['id'] }}" checked> {{ $os['name'] }}</label>
                @endforeach
            @endforeach
        </div>

        <h5>{{ __('voyar::voyar.setup.opt.cpu') }}</h5>
        <div class="vy-checks">
            <label><input type="checkbox" name="cpu[]" value="amd" checked> AMD EPYC</label>
            <label><input type="checkbox" name="cpu[]" value="intel" checked> Intel Xeon</label>
        </div>

        <h5>{{ __('voyar::voyar.setup.opt.app') }}</h5>
        @if($catalogue['apps'] === [])
            <p class="text-muted">{{ __('voyar::voyar.setup.apps_later') }}</p>
        @else
        <div class="vy-checks">
            @foreach($catalogue['apps'] as $app)
            <label><input type="checkbox" name="app[]" value="{{ $app['id'] }}" checked> {{ $app['name'] }}</label>
            @endforeach
        </div>
        @endif

        <h5>{{ __('voyar::voyar.setup.for_products') }}</h5>
        <div class="vy-checks">
            @foreach($vmProducts as $p)
            <label><input type="checkbox" name="products[]" value="{{ $p->id }}" @checked($linked === [] || in_array($p->id, $linked))> {{ $p->name }}</label>
            @endforeach
        </div>
        <button class="btn btn-primary">{{ __('voyar::voyar.setup.build_options') }}</button>
    </form>
</div>

{{-- 4. Add-ons --}}
<div class="card">
    <div class="card-header"><span class="vy-num">4</span>{{ __('voyar::voyar.setup.step_addons') }}</div>
    <form class="card-body" method="POST" action="{{ route('admin.voyar.setup.addons') }}">
        @csrf
        <p>{{ __('voyar::voyar.setup.addons_text') }}</p>
        @if(($catalogue['addons'] ?? []) === [])
            <p class="text-muted">{{ __('voyar::voyar.setup.no_addons') }}</p>
        @else
        <p><label>{{ __('voyar::voyar.setup.margin_input') }} <input type="number" name="margin" value="{{ old('margin', 30) }}" min="0" max="1000" step="1" class="form-control" style="width:110px;display:inline-block"> %</label></p>
        <table class="table">
            <thead><tr><th></th><th>{{ __('voyar::voyar.setup.addon') }}</th><th>{{ __('voyar::voyar.setup.max_qty') }}</th><th>{{ __('voyar::voyar.setup.cost') }}</th></tr></thead>
            <tbody>
            @foreach($catalogue['addons'] as $addon)
                <tr>
                    <td><input type="checkbox" name="addons[]" value="{{ $addon['id'] }}" checked></td>
                    <td><b>{{ $addon['name'] }}</b><br><small class="text-muted">{{ $addon['description'] ?? '' }}</small></td>
                    <td>{{ $addon['max_qty_effective'] ?? $addon['max_qty'] ?? 1 }}</td>
                    <td style="white-space:nowrap">{{ $P::usd((float) ($addon['price']['prepaid'] ?? 0)) }}/{{ __('voyar::voyar.setup.day') }} → <b>{{ $money($P::monthly((array) ($addon['price'] ?? []))) }}</b>/{{ __('voyar::voyar.setup.month') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <h5>{{ __('voyar::voyar.setup.for_products') }}</h5>
        <div class="vy-checks">
            @foreach($vmProducts as $p)
            <label><input type="checkbox" name="products[]" value="{{ $p->id }}" checked> {{ $p->name }}</label>
            @endforeach
        </div>
        <button class="btn btn-primary" @disabled(! $rate)>{{ __('voyar::voyar.setup.build_addons') }}</button>
        @endif
    </form>
</div>

{{-- 5. FTP products --}}
@php
    $ftpPlans = collect($voyarProducts)->where('kind', 'ftp')->values();
    $taken = $products->map(fn ($p) => (string) ($cfg($p)['package_name'] ?? ''))->filter()->all();
@endphp
<div class="card">
    <div class="card-header"><span class="vy-num">5</span>{{ __('voyar::voyar.setup.step_ftp') }}</div>
    <form class="card-body" method="POST" action="{{ route('admin.voyar.setup.ftp') }}">
        @csrf
        <p>{{ __('voyar::voyar.setup.ftp_text') }}</p>
        <table class="table">
            <thead><tr><th>{{ __('voyar::voyar.setup.voyar_plan') }}</th><th>{{ __('voyar::voyar.setup.storage') }}</th><th>{{ __('voyar::voyar.setup.cost') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($ftpPlans as $plan)
                <tr>
                    <td><b>{{ $plan['name'] }}</b></td>
                    <td>{{ $plan['ftp_limits']['quota_gb'] ?? '—' }} GB</td>
                    <td>{{ $P::usd((float) ($plan['price']['prepaid'] ?? 0)) }}/{{ __('voyar::voyar.setup.day') }} → <b>{{ $money($P::monthly((array) ($plan['price'] ?? []))) }}</b>/{{ __('voyar::voyar.setup.month') }}</td>
                    <td>@if(in_array((string) $plan['id'], $taken, true))<span class="vy-ok">{{ __('voyar::voyar.setup.ftp_exists') }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p><label>{{ __('voyar::voyar.setup.margin_input') }} <input type="number" name="margin" value="30" min="0" max="1000" step="1" class="form-control" style="width:110px;display:inline-block"> %</label></p>
        <button class="btn btn-primary" @disabled(! $rate || $ftpPlans->isEmpty())>{{ __('voyar::voyar.setup.build_ftp') }}</button>
    </form>
</div>
@endif
</div>
@endsection
