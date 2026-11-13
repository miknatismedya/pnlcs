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
@endphp
<style>
    .vy-steps .card{margin-bottom:20px}.vy-steps .card-header{font-weight:700}
    .vy-num{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:50%;background:#5b4cf0;color:#fff;font-size:12px;margin-right:8px}
    .vy-checks{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px 16px;margin:8px 0 16px}
    .vy-checks label{display:flex;gap:8px;align-items:center;font-weight:400}
    .vy-ok{color:#16a34a;font-weight:700}.vy-bad{color:#e5484d;font-weight:700}
</style>

<div class="page-header"><h1>{{ __('voyar::voyar.setup.title') }}</h1></div>
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
            @if($credit !== null)<p>{{ __('voyar::voyar.setup.balance') }}: <b>{{ number_format((float) $credit, 2, ',', '.') }}₺</b></p>@endif
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
                    <td style="font-size:12px">
                        @if($current)
                            Voyar: {{ $current['price']['ondemand'] ?? '—' }} /saat · prepaid {{ $current['price']['prepaid'] ?? '—' }}<br>
                            @if($ours > 0 && $theirs > 0)
                                {{ __('voyar::voyar.setup.margin') }}: <b class="{{ $ours > $theirs ? 'vy-ok' : 'vy-bad' }}">%{{ number_format(($ours / $theirs - 1) * 100, 1, ',', '.') }}</b>
                            @endif
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
            @foreach($products->filter(fn ($p) => $p->server_type === 'voyar' && ($cfg($p)['voyar_kind'] ?? 'vm') === 'vm') as $p)
            <label><input type="checkbox" name="products[]" value="{{ $p->id }}" @checked($linked === [] || in_array($p->id, $linked))> {{ $p->name }}</label>
            @endforeach
        </div>
        <button class="btn btn-primary">{{ __('voyar::voyar.setup.build_options') }}</button>
    </form>
</div>
@endif
</div>
@endsection
