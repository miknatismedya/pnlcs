{{--
    Viyo Cloud home page. The page itself lives in the Viyo Cloud addon
    (modules/Addons/ViyoCloud) with the rest of the site; while that addon is
    off, the core home page is shown so the site never breaks.
--}}
@if(view()->exists('viyocloud::pages.home'))
    @include('viyocloud::pages.home')
@else
    {!! view()->file(resource_path('views/welcome.blade.php'), \Illuminate\Support\Arr::except(get_defined_vars()['__data'], ['__env', 'app']))->render() !!}
@endif
