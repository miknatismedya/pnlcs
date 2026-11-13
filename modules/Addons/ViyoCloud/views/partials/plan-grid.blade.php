{{-- Plans with a billing-term switch. $plans from Catalog. --}}
@php $cycles = \Modules\Addons\ViyoCloud\Support\Catalog::CYCLES; @endphp
<div x-data="{ cycle: 'monthly' }">
    <div class="vc-seg" role="tablist">
        @foreach($cycles as $cycle => $months)
        <button type="button" role="tab" :class="{ 'is-on': cycle === '{{ $cycle }}' }" :aria-selected="cycle === '{{ $cycle }}'" @click="cycle = '{{ $cycle }}'">{{ __('viyocloud::site.common.cycles.'.$cycle) }}</button>
        @endforeach
    </div>
    <div class="vc-plans">
        @foreach($plans as $plan)
        <article class="vc-plan {{ $plan['featured'] ? 'is-featured' : '' }}">
            @if($plan['featured'])<span class="vc-plan__flag">{{ __('viyocloud::site.common.popular') }}</span>@endif
            <h3>{{ $plan['name'] }}</h3>
            @if($plan['description'])<p class="vc-plan__desc">{{ $plan['description'] }}</p>@endif
            <div class="vc-plan__price">
                @foreach($plan['prices'] as $cycle => $price)
                <div x-show="cycle === '{{ $cycle }}'" @if($cycle !== 'monthly') style="display:none" @endif>
                    <div class="vc-plan__amount">{{ $price['per_month'] }}<small>{{ __('viyocloud::site.common.per_month') }}</small></div>
                    <div class="vc-plan__billed">
                        {{ __('viyocloud::site.common.billed.'.$cycle, ['total' => $price['total']]) }}
                        @if($price['saving'] > 0)<span class="vc-tag">{{ __('viyocloud::site.common.save', ['pct' => $price['saving']]) }}</span>@endif
                    </div>
                </div>
                @endforeach
                <div x-show="!{{ \Illuminate\Support\Js::from(array_keys($plan['prices'])) }}.includes(cycle)" style="display:none" class="vc-plan__billed">{{ __('viyocloud::site.common.not_on_cycle') }}</div>
            </div>
            @include('viyocloud::partials.specs', ['plan' => $plan])
            <a href="{{ $plan['order_url'] }}" class="vc-btn {{ $plan['featured'] ? 'vc-btn--primary' : 'vc-btn--ghost' }} vc-btn--block">{{ __('viyocloud::site.common.order') }} <i class="ri-arrow-right-up-line"></i></a>
        </article>
        @endforeach
    </div>
    <p class="vc-note">{{ __('viyocloud::site.common.vat_note') }}</p>
</div>
