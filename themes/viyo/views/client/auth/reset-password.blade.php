{{-- The core sign-in page, dressed by the Viyo theme (see viyo/decorate). --}}
@include('viyo.decorate', ['__core' => 'client/auth/reset-password', '__vars' => get_defined_vars()['__data'], '__mode' => 'auth'])
