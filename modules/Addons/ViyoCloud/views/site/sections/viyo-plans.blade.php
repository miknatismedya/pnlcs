{{--
    Viyo Cloud: the cloud plans as a comparison table (cards on a phone).
    Also used by the Cloud page: $line ('cloud' by default), $bare (no heading).
--}}
@php
    use Modules\Addons\ViyoCloud\Support\Catalog;
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
    $plans = Catalog::plans($line ?? 'cloud');
    $hourly = $plans->contains(fn ($p) => $p['hourly']);
    $specKeys = collect(['cpu', 'ram', 'disk', 'storage', 'ipv4'])->filter(fn ($k) => $plans->contains(fn ($p) => ! empty($p[$k])))->values();
@endphp
<section class="vs-section" id="plans">
    <div class="vs-wrap">
        @unless($bare ?? false)
        <header class="vs-head">
            <h2>{{ $val('title', __('viyocloud::site.sec.plans_title')) }}</h2>
            <p>{{ $val('subtitle', __('viyocloud::site.sec.plans_text')) }}</p>
        </header>
        @endunless

        @if($plans->isEmpty())
            <div class="vs-empty"><b>{{ __('viyocloud::site.common.no_plans_title') }}</b><p>{{ __('viyocloud::site.common.no_plans_text') }}</p></div>
        @else
        <div class="vs-table-wrap">
            <table class="vs-table vs-plans">
                <thead>
                    <tr>
                        <th>{{ __('viyocloud::site.hourly.table_plan') }}</th>
                        @foreach($specKeys as $k)<th>{{ __('viyocloud::site.sec.col.'.$k) }}</th>@endforeach
                        <th class="is-num">{{ __('viyocloud::site.sec.col.monthly') }}</th>
                        @if($hourly)<th class="is-num">{{ __('viyocloud::site.sec.col.hourly') }}</th>@endif
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plans as $plan)
                    <tr class="{{ $plan['featured'] ? 'is-featured' : '' }}">
                        <td data-label="{{ __('viyocloud::site.hourly.table_plan') }}">
                            <b>{{ $plan['name'] }}</b>
                            @if($plan['featured'])<span class="vs-tag">{{ __('viyocloud::site.common.popular') }}</span>@endif
                        </td>
                        @foreach($specKeys as $k)
                        <td data-label="{{ __('viyocloud::site.sec.col.'.$k) }}">{{ $plan[$k] ? __('viyocloud::site.sec.unit.'.$k, ['n' => $plan[$k]]) : '—' }}</td>
                        @endforeach
                        <td class="is-num" data-label="{{ __('viyocloud::site.sec.col.monthly') }}">
                            @if(isset($plan['prices']['monthly']))<b>{{ $plan['prices']['monthly']['per_month'] }}</b>@else — @endif
                        </td>
                        @if($hourly)
                        <td class="is-num" data-label="{{ __('viyocloud::site.sec.col.hourly') }}">{{ $plan['hourly'] ?? '—' }}</td>
                        @endif
                        <td class="is-action"><a href="{{ $plan['order_url'] }}" class="vs-btn {{ $plan['featured'] ? 'vs-btn--primary' : 'vs-btn--line' }} vs-btn--sm">{{ __('viyocloud::site.common.order') }}</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="vs-note">{{ __('viyocloud::site.common.vat_note') }} {{ __('viyocloud::site.sec.plans_note') }}</p>
        @endif
    </div>
</section>
