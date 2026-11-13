{{--
    Viyo Cloud FAQ. Items from Setup -> Appearance (items, a JSON list of
    {"question","answer"}); when that is empty, the translated Viyo questions.
--}}
@php
    $c = $content ?? collect();
    $val = fn ($key, $fallback) => ($c->get($key)?->content_value) ?: $fallback;
    $items = json_decode((string) ($c->get('items')?->content_value ?? ''), true) ?: [];
    if ($items === []) {
        $items = array_map(fn ($i) => ['question' => $i['q'], 'answer' => $i['a']], (array) ($faqItems ?? __('viyocloud::site.sec.faq')));
    }
@endphp
@if(count($items))
<section class="vs-section" id="faq">
    <div class="vs-wrap vs-split vs-split--faq">
        <header>
            <h2>{{ $val('title', __('viyocloud::site.common.faq')) }}</h2>
            <p class="vs-lead">{{ $val('subtitle', __('viyocloud::site.sec.faq_text')) }}</p>
        </header>
        <div class="vs-faq">
            @foreach($items as $item)
            <details @if($loop->first) open @endif>
                <summary>{{ $item['question'] ?? '' }}<i class="ri-add-line"></i></summary>
                <p>{{ $item['answer'] ?? '' }}</p>
            </details>
            @endforeach
        </div>
    </div>
</section>
@endif
