{{-- $items: list of ['q' => ..., 'a' => ...] --}}
@include('sections.faq', ['content' => collect(), 'faqItems' => $items])
