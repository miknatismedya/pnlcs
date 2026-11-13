{{-- $items: [['q' => ..., 'a' => ...], ...] --}}
<section class="vc-section">
    <div class="vc-wrap vc-wrap--narrow">
        <h2 class="vc-h2 vc-center">{{ __('viyocloud::site.common.faq') }}</h2>
        <div class="vc-faq" x-data="{ open: 0 }">
            @foreach($items as $i => $item)
            <div class="vc-faq__item" :class="{ 'is-open': open === {{ $i }} }">
                <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}" :aria-expanded="open === {{ $i }}">{{ $item['q'] }} <i class="ri-add-line"></i></button>
                <div x-show="open === {{ $i }}" x-collapse @if($i !== 0) style="display:none" @endif><p>{{ $item['a'] }}</p></div>
            </div>
            @endforeach
        </div>
    </div>
</section>
