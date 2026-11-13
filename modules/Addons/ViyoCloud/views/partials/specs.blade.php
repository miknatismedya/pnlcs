{{-- $plan: one Catalog plan --}}
<ul class="vc-specs">
    @foreach(['cpu' => 'ri-cpu-line', 'ram' => 'ri-ram-2-line', 'disk' => 'ri-hard-drive-3-line', 'storage' => 'ri-folder-cloud-line', 'ipv4' => 'ri-global-line'] as $key => $icon)
        @if(!empty($plan[$key]))
        <li><i class="{{ $icon }}"></i> {{ __('viyocloud::site.common.spec.'.$key, ['n' => $plan[$key]]) }}</li>
        @endif
    @endforeach
</ul>
