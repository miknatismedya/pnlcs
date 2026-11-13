{{-- Viyo Cloud admin page: hourly servers, their income, and where the site is edited. --}}
@php $fmt = fn ($v) => money_fmt((float) $v); @endphp
<div class="row" style="margin-bottom:16px">
    <div class="col-md-3"><div class="stat-card"><div class="stat-value">{{ $servers->count() }}</div><div class="stat-label">{{ __('viyocloud::site.admin.running') }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value">{{ $servers->where('owed', '>', 0)->count() }}</div><div class="stat-label">{{ __('viyocloud::site.admin.in_debt') }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value">{{ $fmt($income24) }}</div><div class="stat-label">{{ __('viyocloud::site.admin.income24') }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value">{{ $fmt($income30) }}</div><div class="stat-label">{{ __('viyocloud::site.admin.income30') }}</div></div></div>
</div>

<div class="card">
    <div class="card-header">{{ __('viyocloud::site.admin.hourly_title') }} @unless($settings['enabled'])<span class="badge badge-pending" style="margin-left:8px">{{ __('viyocloud::site.admin.hourly_off') }}</span>@endunless</div>
    <div class="card-body" style="padding:0">
        @if($servers->isEmpty())
            <p style="padding:15px;margin:0" class="text-muted">{{ __('viyocloud::site.admin.no_servers') }}</p>
        @else
        <table class="table data-table" style="margin:0">
            <thead><tr>
                <th>{{ __('viyocloud::site.admin.col_client') }}</th><th>{{ __('viyocloud::site.admin.col_server') }}</th>
                <th>{{ __('viyocloud::site.admin.col_rate') }}</th><th>{{ __('viyocloud::site.admin.col_total') }}</th>
                <th>{{ __('viyocloud::site.admin.col_balance') }}</th><th>{{ __('viyocloud::site.admin.col_owed') }}</th><th>{{ __('viyocloud::site.admin.col_state') }}</th>
            </tr></thead>
            <tbody>
            @foreach($servers as $row)
                <tr>
                    <td><a href="{{ route('admin.clients.show', $row->client_id) }}">{{ trim($row->company_name ?: $row->first_name.' '.$row->last_name) }}</a></td>
                    <td><a href="{{ route('admin.services.show', $row->service_id) }}">{{ $row->plan }}</a><br><small class="text-muted">{{ $row->domain }}</small></td>
                    <td>{{ \Modules\Addons\ViyoCloud\Support\Catalog::hourlyFmt((float) $row->rate) }}</td>
                    <td>{{ $fmt($row->total) }}</td>
                    <td>{{ $fmt($row->credit) }}</td>
                    <td>@if($row->owed > 0)<b style="color:#c4323a">{{ $fmt($row->owed) }}</b>@else — @endif</td>
                    <td>
                        @if($row->terminate_requested_at)<span class="badge badge-cancelled">{{ __('viyocloud::site.admin.state_deleting') }}</span>
                        @elseif($row->suspended_for_balance_at)<span class="badge badge-overdue">{{ __('viyocloud::site.admin.state_stopped') }}</span>
                        @else<span class="badge badge-active">{{ __('viyocloud::site.admin.state_ok') }}</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">{{ __('viyocloud::site.admin.site_title') }}</div>
    <div class="card-body">
        <p>{{ __('viyocloud::site.admin.site_text') }}</p>
        <table class="table" style="margin-bottom:12px">
            <thead><tr><th>{{ __('viyocloud::site.admin.line') }}</th><th>{{ __('viyocloud::site.admin.group_slug') }}</th><th>{{ __('viyocloud::site.admin.products') }}</th></tr></thead>
            <tbody>
            @foreach($lines as $key => $line)
                <tr><td>{{ __('viyocloud::site.lines.'.$key.'.title') }}</td><td><code>{{ $line['slug'] }}</code></td><td>{{ $line['count'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <a href="{{ route('admin.settings.appearance') }}" class="btn btn-default btn-sm">{{ __('viyocloud::site.admin.go_sections') }}</a>
        <a href="{{ route('admin.products.index') }}" class="btn btn-default btn-sm">{{ __('viyocloud::site.admin.go_products') }}</a>
        @if(\Illuminate\Support\Facades\Route::has('admin.voyar.setup'))<a href="{{ route('admin.voyar.setup') }}" class="btn btn-default btn-sm">{{ __('viyocloud::site.admin.go_voyar') }}</a>@endif
    </div>
</div>
