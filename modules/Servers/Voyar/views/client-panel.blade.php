{{-- Voyar section on the customer's service page. --}}
@php
    $active = $service->status === 'active';
    $hasId = ! empty($data['voyar_id']);
    $billing = $data['voyar_billing'] ?? null;
@endphp
<style>
    .vy-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:16px 0}
    .vy-state{display:inline-flex;align-items:center;gap:6px}
    .vy-dot{width:9px;height:9px;border-radius:50%;background:var(--muted)}
    .vy-dot.running{background:#16a34a;box-shadow:0 0 0 4px rgba(22,163,74,.15)}
    .vy-dot.stopped{background:#e5484d}
    .vy-body{padding:16px 20px}
    .vy-body p{margin:0 0 12px;color:var(--muted);font-size:13px}
    .vy-body select{width:100%;padding:9px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:inherit;margin-bottom:10px}
    .vy-actions{display:flex;flex-wrap:wrap;gap:8px;padding:0 20px 16px}
    .vy-apps{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
    .vy-app{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px;border:1px solid var(--border);border-radius:12px;text-align:center;font-size:13px;font-weight:700}
    .vy-app img{width:34px;height:34px;object-fit:contain}
</style>

@if(session('success'))<div class="pn-alert pn-alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="pn-alert pn-alert-danger">{{ session('error') }}</div>@endif
@if($error)<div class="pn-alert pn-alert-warning">{{ $error }}</div>@endif

@if(! $hasId)
    <div class="pn-alert pn-alert-warning"><i class="ri-loader-4-line"></i> {{ __('voyar::voyar.panel.pending') }}</div>
@elseif($ftp)
    <div class="sv-panel" style="margin:16px 0">
        <div class="sv-ph"><i class="ri-folder-cloud-line"></i> FTP</div>
        <ul class="sv-dl">
            <li><span class="k">{{ __('voyar::voyar.panel.ftp_host') }}</span><span class="v"><span class="sv-code">{{ $data['ftp_host'] ?? '—' }}</span></span></li>
            <li><span class="k">{{ __('voyar::voyar.panel.ftp_port') }}</span><span class="v">{{ $data['ftp_port'] ?? 21 }}</span></li>
            <li><span class="k">{{ __('voyar::voyar.panel.ftp_user') }}</span><span class="v"><span class="sv-code">{{ $service->username }}</span></span></li>
        </ul>
    </div>
@else
<div class="vy-grid" x-data="{ s: { state: 'loading' } }" x-init="
    const load = () => fetch('{{ route('voyar.client.status', $service) }}', { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(j => { if (j.ok) s = j; else s = { state: 'unknown' } }).catch(() => s = { state: 'unknown' });
    load(); setInterval(load, 15000);">
    <div class="sv-panel">
        <div class="sv-ph"><i class="ri-server-line"></i> {{ __('voyar::voyar.panel.title') }}</div>
        <ul class="sv-dl">
            <li><span class="k">{{ __('voyar::voyar.panel.state') }}</span><span class="v vy-state"><span class="vy-dot" :class="s.state"></span>
                <span x-text="{{ \Illuminate\Support\Js::from(__('voyar::voyar.panel.states')) }}[s.state] ?? s.state"></span></span></li>
            <li><span class="k">{{ __('voyar::voyar.panel.ip') }}</span><span class="v"><span class="sv-code">{{ $data['voyar_ip'] ?? '—' }}</span></span></li>
            <li><span class="k">{{ __('voyar::voyar.panel.os') }}</span><span class="v">{{ $data['voyar_os'] ?? '—' }}</span></li>
            <li><span class="k">{{ __('voyar::voyar.panel.location') }}</span><span class="v">{{ $data['voyar_location'] ?? '—' }}</span></li>
            @if($billing)
            <li><span class="k">{{ __('voyar::voyar.panel.billing') }}</span><span class="v">{{ __('voyar::voyar.panel.billing_types.'.$billing) }}</span></li>
            @endif
        </ul>
    </div>
    <div class="sv-panel">
        <div class="sv-ph"><i class="ri-pulse-line"></i> {{ __('voyar::voyar.panel.state') }}</div>
        <ul class="sv-dl">
            <li><span class="k">{{ __('voyar::voyar.panel.cpu') }}</span><span class="v" x-text="s.cpu != null ? s.cpu.toFixed(1) + '%' : '—'"></span></li>
            <li><span class="k">{{ __('voyar::voyar.panel.memory') }}</span><span class="v" x-text="s.memory_text || '—'"></span></li>
            <li><span class="k">{{ __('voyar::voyar.panel.uptime') }}</span><span class="v" x-text="s.uptime || '—'"></span></li>
        </ul>
        @if($active)
        <div class="vy-actions">
            <a href="{{ route('voyar.client.console', $service) }}" target="_blank" rel="noopener" class="pn-btn pn-btn-sm pn-btn-primary"><i class="ri-terminal-box-line"></i> {{ __('voyar::voyar.panel.console') }}</a>
            @if($billing === 'ondemand' && \Illuminate\Support\Facades\Route::has('viyocloud.hourly.usage'))
            <a href="{{ route('viyocloud.hourly.usage', $service) }}" class="pn-btn pn-btn-sm"><i class="ri-timer-flash-line"></i> {{ __('viyocloud::site.hourly_order.usage_link') }}</a>
            @endif
            <form method="POST" action="{{ route('voyar.client.password', $service) }}" onsubmit="return confirm(@js(__('voyar::voyar.panel.password_confirm')))">
                @csrf
                <button class="pn-btn pn-btn-sm"><i class="ri-key-2-line"></i> {{ __('voyar::voyar.panel.password') }}</button>
            </form>
        </div>
        @endif
    </div>
</div>

@if($active)
<div class="vy-grid">
    @if($families !== [])
    <div class="sv-panel">
        <div class="sv-ph"><i class="ri-loop-left-line"></i> {{ __('voyar::voyar.reinstall.title') }}</div>
        <form class="vy-body" method="POST" action="{{ route('voyar.client.reinstall', $service) }}">
            @csrf
            <p>{{ __('voyar::voyar.reinstall.text') }}</p>
            <select name="osid" required aria-label="{{ __('voyar::voyar.reinstall.os') }}">
                @foreach($families as $family)
                <optgroup label="{{ $family['name'] ?? '' }}">
                    @foreach($family['operatingsystems'] ?? [] as $os)
                    <option value="{{ $os['id'] }}">{{ $os['name'] }}</option>
                    @endforeach
                </optgroup>
                @endforeach
            </select>
            <label style="display:flex;gap:8px;font-size:13px;margin-bottom:12px"><input type="checkbox" name="confirm" value="1" required> {{ __('voyar::voyar.reinstall.confirm') }}</label>
            <button class="pn-btn pn-btn-sm" style="color:#e5484d"><i class="ri-error-warning-line"></i> {{ __('voyar::voyar.reinstall.submit') }}</button>
        </form>
    </div>
    @endif
    <div class="sv-panel">
        <div class="sv-ph"><i class="ri-apps-2-line"></i> {{ __('voyar::voyar.apps.title') }}</div>
        <div class="vy-body">
            <p>{{ __('voyar::voyar.apps.text') }}</p>
            @if($apps === [])
                <p>{{ __('voyar::voyar.apps.none') }}</p>
            @else
            <div class="vy-apps">
                @foreach($apps as $app)
                <form class="vy-app" method="POST" action="{{ route('voyar.client.apps.install', $service) }}">
                    @csrf
                    <input type="hidden" name="oneclick_id" value="{{ $app['id'] }}">
                    @if(!empty($app['logo']))<img src="{{ $app['logo'] }}" alt="" loading="lazy">@endif
                    <span>{{ $app['name'] }}</span>
                    @foreach($app['fields'] ?? [] as $field)
                        <input type="text" name="fields[{{ $field['field_id'] }}]" placeholder="{{ $field['field_label'] }}" @if(!empty($field['field_required'])) required @endif style="width:100%;padding:6px;border:1px solid var(--border);border-radius:8px;font-size:12px">
                    @endforeach
                    @if(!empty($app['installed']))
                        <span class="text-muted text-sm">{{ __('voyar::voyar.apps.installed') }}</span>
                    @else
                        <button class="pn-btn pn-btn-sm">{{ __('voyar::voyar.apps.install') }}</button>
                    @endif
                </form>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endif
@endif
