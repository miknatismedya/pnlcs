{{-- The core client layout, dressed by the Viyo theme (see viyo/decorate). --}}
@include('viyo.decorate', ['__core' => 'client/layouts/app', '__vars' => get_defined_vars()['__data'], '__mode' => 'app'])
