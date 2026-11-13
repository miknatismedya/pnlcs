{{--
    Viyo Cloud: the core client/cart/store.blade.php as a plan table. Same
    data ($groups, $currency); the Viyo product lines show their specs and the
    hourly price, read by the Viyo Cloud addon's catalogue.
--}}
@extends("client.layouts.app")
@section("title", __("client.order_a_new_product"))
@section("content")
@php
    $t = fn (string $key, string $fallback, array $r = []) => \Illuminate\Support\Facades\Lang::has('viyocloud::site.'.$key) ? __('viyocloud::site.'.$key, $r) : __($fallback, $r);
    $catalog = class_exists(\Modules\Addons\ViyoCloud\Support\Catalog::class);
    $lines = $catalog ? array_flip(\Modules\Addons\ViyoCloud\Support\Catalog::GROUPS) : [];
    $per = fn (string $cycle) => \Illuminate\Support\Facades\Lang::has('viyocloud::site.client.per.'.$cycle) ? __('viyocloud::site.client.per.'.$cycle) : '/'.cycle_label($cycle);
    $hourlyOn = $catalog && \Illuminate\Support\Facades\Route::has('viyocloud.hourly.create')
        && (bool) (\Modules\Addons\ViyoCloud\Support\HourlyBilling::settings()['enabled'] ?? false);

    $shown = $groups->filter(fn ($g) => $g->products->isNotEmpty())->values();
    $pick = request('kategori');
    $visible = $pick && $shown->firstWhere('slug', $pick) ? $shown->where('slug', $pick)->values() : $shown;
@endphp

<div class="vx-store-head">
    <h1>{{ $t('client.store_title', 'client.store.title') }}</h1>
    <p>{{ $t('client.store_lead', 'client.store.subtitle') }}</p>
</div>

@if($shown->count() > 1)
<nav class="vx-tabs">
    <a href="{{ route('client.store') }}" class="{{ $visible->count() === $shown->count() ? 'is-active' : '' }}">{{ $t('client.all', 'common.all') }}</a>
    @foreach($shown as $group)
    <a href="{{ route('client.store') }}?kategori={{ $group->slug }}" class="{{ $visible->count() === 1 && $visible->first()->id === $group->id ? 'is-active' : '' }}">{{ $group->name }}</a>
    @endforeach
</nav>
@endif

@if($hourlyOn && $visible->contains('slug', 'cloud-server'))
<div class="vx-notice">
    <i class="ri-time-line"></i>
    <span>{{ __('viyocloud::site.client.store_hourly') }}</span>
    <a href="{{ route('viyocloud.hourly.create') }}" class="btn btn-outline btn-sm">{{ __('viyocloud::site.client.store_hourly_cta') }}</a>
</div>
@endif

@forelse($visible as $group)
<section class="vx-group">
    <h2>{{ $group->name }}</h2>
    @php
        $rows = isset($lines[$group->slug])
            ? \Modules\Addons\ViyoCloud\Support\Catalog::plans($lines[$group->slug])->map(fn ($p) => [
                'name' => $p['name'], 'featured' => $p['featured'], 'url' => $p['order_url'], 'stock' => true,
                'specs' => array_filter(['cpu' => $p['cpu'], 'ram' => $p['ram'], 'disk' => $p['disk'], 'storage' => $p['storage'], 'ipv4' => $p['ipv4']]),
                'price' => isset($p['prices']['monthly']) ? $p['prices']['monthly']['per_month'].$per('monthly') : null,
                'hourly' => $p['hourly'] ? $p['hourly'].__('viyocloud::site.common.per_hour') : null,
            ])
            : $group->products->map(function ($product) use ($currency, $per) {
                $priced = $product->pricedCycles($currency?->id);
                $cycle = (string) array_key_first($priced);
                $price = $priced[$cycle] ?? null;

                return [
                    'name' => $product->name, 'featured' => (bool) $product->is_featured, 'stock' => ! $product->outOfStock(),
                    'url' => route('client.store.configure', $product), 'specs' => [], 'hourly' => null,
                    'res' => collect($product->resourceSummary())->pluck('text')->implode(' · '),
                    'price' => $price === null ? null : ($product->pay_type === 'free' ? __('common.billing.free') : display_money_fmt($price).$per($cycle)),
                ];
            });
        $specKeys = collect(['cpu', 'ram', 'disk', 'storage', 'ipv4'])->filter(fn ($k) => $rows->contains(fn ($r) => ! empty($r['specs'][$k])))->values();
        $hasHourly = $rows->contains(fn ($r) => $r['hourly']);
        $hasRes = $rows->contains(fn ($r) => ! empty($r['res']));
    @endphp
    <div class="vs-table-wrap">
        <table class="vs-table vs-plans">
            <thead>
                <tr>
                    <th>{{ __('common.table.product') }}</th>
                    @foreach($specKeys as $k)<th>{{ __('viyocloud::site.sec.col.'.$k) }}</th>@endforeach
                    @if($hasRes)<th></th>@endif
                    <th class="is-num">{{ __('common.table.amount') }}</th>
                    @if($hasHourly)<th class="is-num">{{ __('viyocloud::site.sec.col.hourly') }}</th>@endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr class="{{ $row['featured'] ? 'is-featured' : '' }}">
                    <td><b>{{ $row['name'] }}</b>@if($row['featured'])<span class="vs-tag">{{ __('client.store.most_popular') }}</span>@endif</td>
                    @foreach($specKeys as $k)
                    <td data-label="{{ __('viyocloud::site.sec.col.'.$k) }}">{{ ! empty($row['specs'][$k]) ? __('viyocloud::site.sec.unit.'.$k, ['n' => $row['specs'][$k]]) : '—' }}</td>
                    @endforeach
                    @if($hasRes)<td>{{ $row['res'] ?? '' }}</td>@endif
                    <td class="is-num" data-label="{{ __('common.table.amount') }}"><b>{{ $row['price'] ?? __('client.store.contact_us') }}</b></td>
                    @if($hasHourly)<td class="is-num" data-label="{{ __('viyocloud::site.sec.col.hourly') }}">{{ $row['hourly'] ?? '—' }}</td>@endif
                    <td class="is-action">
                        @if($row['stock'])
                        <a href="{{ $row['url'] }}" class="btn {{ $row['featured'] ? 'btn-primary' : 'btn-outline' }} btn-sm">{{ __('client.store.order_now') }}</a>
                        @else
                        <span class="btn btn-outline btn-sm" style="opacity:.6">{{ __('client.cart.out_of_stock') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@empty
<div class="pn-card"><div class="pn-empty"><p>{{ __('client.store.no_products_at_all') }}</p></div></div>
@endforelse
@endsection
