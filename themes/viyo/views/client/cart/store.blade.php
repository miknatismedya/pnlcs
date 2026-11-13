{{--
    Viyo store. Same data as the core store ($groups, $currency); the cloud
    lines show their plans with specs and the hourly price, read by the Viyo
    Cloud addon's catalogue. Other groups get a plain card. With the addon
    off, every group gets the plain card.
--}}
@extends("client.layouts.app")
@section("title", __("client.order_a_new_product"))
@section("content")
@php
    $t = fn (string $key, string $fallback, array $r = []) => \Illuminate\Support\Facades\Lang::has('viyocloud::site.'.$key) ? __('viyocloud::site.'.$key, $r) : __($fallback, $r);
    $catalog = class_exists(\Modules\Addons\ViyoCloud\Support\Catalog::class);
    $lines = $catalog ? array_flip(\Modules\Addons\ViyoCloud\Support\Catalog::GROUPS) : [];
    $per = fn (string $cycle) => \Illuminate\Support\Facades\Lang::has('viyocloud::site.client.per.'.$cycle) ? __('viyocloud::site.client.per.'.$cycle) : '/'.cycle_label($cycle);
    $hourlyOn = \Illuminate\Support\Facades\Route::has('viyocloud.hourly.create')
        && $catalog && (bool) (\Modules\Addons\ViyoCloud\Support\HourlyBilling::settings()['enabled'] ?? false);

    $shown = $groups->filter(fn ($g) => $g->products->isNotEmpty())->values();
    $pick = request('kategori');
    $visible = $pick && $shown->firstWhere('slug', $pick) ? $shown->where('slug', $pick)->values() : $shown;
    $icons = ['cloud-server' => 'ri-server-line', 'ftp-storage' => 'ri-folder-cloud-line', 'app-hosting' => 'ri-apps-2-line'];
@endphp

<section class="vx-store-hero">
    <div>
        <h1>{{ $t('client.store_title', 'client.store.title') }}</h1>
        <p>{{ $t('client.store_lead', 'client.store.subtitle') }}</p>
    </div>
    @if(!empty($cart) && count($cart) > 0)
    <a href="{{ route('client.cart.index') }}" class="btn btn-outline"><i class="ri-shopping-bag-3-line"></i> {{ __('client.cart.view_cart') }}</a>
    @endif
</section>

@if($shown->count() > 1)
<nav class="vx-tabs">
    <a href="{{ route('client.store') }}" class="{{ $visible->count() === $shown->count() ? 'is-active' : '' }}">{{ $t('client.all', 'common.all') }}</a>
    @foreach($shown as $group)
    <a href="{{ route('client.store') }}?kategori={{ $group->slug }}" class="{{ $visible->count() === 1 && $visible->first()->id === $group->id ? 'is-active' : '' }}"><i class="{{ $icons[$group->slug] ?? 'ri-stack-line' }}"></i> {{ $group->name }}</a>
    @endforeach
</nav>
@endif

@if($hourlyOn && $visible->contains('slug', 'cloud-server'))
<div class="vx-hourly-note">
    <i class="ri-timer-flash-line"></i>
    <span>{{ __('viyocloud::site.client.store_hourly') }}</span>
    <a href="{{ route('viyocloud.hourly.create') }}" class="btn btn-outline btn-sm">{{ __('viyocloud::site.client.store_hourly_cta') }} <i class="ri-arrow-right-line"></i></a>
</div>
@endif

@forelse($visible as $group)
<section class="vx-store-group">
    <h2><i class="{{ $icons[$group->slug] ?? 'ri-stack-line' }}"></i> {{ $group->name }}</h2>

    @if(isset($lines[$group->slug]))
        @php $plans = \Modules\Addons\ViyoCloud\Support\Catalog::plans($lines[$group->slug]); @endphp
        <div class="vx-plans">
            @foreach($plans as $plan)
            <article class="vx-plan {{ $plan['featured'] ? 'is-featured' : '' }}">
                @if($plan['featured'])<span class="vx-plan__badge">{{ __('client.store.most_popular') }}</span>@endif
                <header>
                    <h3>{{ $plan['name'] }}</h3>
                    @if($plan['description'])<p>{{ \Illuminate\Support\Str::limit(strip_tags($plan['description']), 70) }}</p>@endif
                </header>
                <div class="vx-plan__price">
                    @if(isset($plan['prices']['monthly']))
                        <b>{{ $plan['prices']['monthly']['per_month'] }}</b><span>{{ $per('monthly') }}</span>
                    @elseif($plan['prices'])
                        @php $first = array_key_first($plan['prices']); @endphp
                        <b>{{ $plan['prices'][$first]['total'] }}</b><span>{{ $per($first) }}</span>
                    @else
                        <b>{{ __('client.store.contact_us') }}</b>
                    @endif
                </div>
                @if($plan['hourly'])
                <div class="vx-plan__hourly">{{ __('viyocloud::site.client.or_hourly', ['price' => $plan['hourly']]) }}</div>
                @endif
                <ul class="vx-plan__specs">
                    @foreach(['cpu' => 'ri-cpu-line', 'ram' => 'ri-ram-2-line', 'disk' => 'ri-hard-drive-3-line', 'storage' => 'ri-folder-cloud-line', 'ipv4' => 'ri-global-line'] as $key => $icon)
                        @if(!empty($plan[$key]))
                        <li><i class="{{ $icon }}"></i> {{ __('viyocloud::site.common.spec.'.$key, ['n' => $plan[$key]]) }}</li>
                        @endif
                    @endforeach
                </ul>
                <a href="{{ $plan['order_url'] }}" class="btn {{ $plan['featured'] ? 'btn-primary' : 'btn-outline' }}">{{ __('client.store.order_now') }} <i class="ri-arrow-right-line"></i></a>
            </article>
            @endforeach
        </div>
    @else
        <div class="vx-plans">
            @foreach($group->products as $product)
            @php
                $pricedCycles = $product->pricedCycles($currency?->id);
                $startingCycle = (string) array_key_first($pricedCycles);
                $startingPrice = $pricedCycles[$startingCycle] ?? null;
                $res = $product->resourceSummary();
            @endphp
            <article class="vx-plan {{ $product->is_featured ? 'is-featured' : '' }}">
                @if($product->is_featured)<span class="vx-plan__badge">{{ __('client.store.most_popular') }}</span>@endif
                <header>
                    <h3>{{ $product->name }}</h3>
                    @if($product->hasDescription())<p>{{ \Illuminate\Support\Str::limit($product->descriptionText(), 90) }}</p>@endif
                </header>
                <div class="vx-plan__price">
                    @if($startingPrice !== null && $product->pay_type === 'free')
                        <b>{{ __('common.billing.free') }}</b>
                    @elseif($startingPrice !== null)
                        <b>{{ display_money_fmt($startingPrice) }}</b><span>{{ $per($startingCycle) }}</span>
                    @else
                        <b>{{ __('client.store.contact_us') }}</b>
                    @endif
                </div>
                @if($res)
                <ul class="vx-plan__specs">
                    @foreach($res as $r)<li><i class="{{ $r['icon'] }}"></i> {{ $r['text'] }}</li>@endforeach
                </ul>
                @endif
                @if($product->outOfStock())
                    <span class="btn btn-outline is-disabled">{{ __('client.cart.out_of_stock') }}</span>
                @else
                    <a href="{{ route('client.store.configure', $product) }}" class="btn {{ $product->is_featured ? 'btn-primary' : 'btn-outline' }}">{{ __('client.store.order_now') }} <i class="ri-arrow-right-line"></i></a>
                @endif
            </article>
            @endforeach
        </div>
    @endif
</section>
@empty
<div class="pn-card"><div class="pn-empty"><p>{{ __('client.store.no_products_at_all') }}</p></div></div>
@endforelse
@endsection
