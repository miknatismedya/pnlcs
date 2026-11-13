<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('voyar::voyar.console_title') }} · {{ $service->domain ?: '#'.$service->id }}</title>
    <style>
        html,body{margin:0;height:100%;background:#0b0c22;color:#c9cbe4;font-family:system-ui,sans-serif}
        header{display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#0e0f2b;border-bottom:1px solid #262a55;font-size:14px}
        header a{color:#b3abff;text-decoration:none}
        #screen{position:absolute;inset:44px 0 0 0}
        #status{opacity:.8}
    </style>
</head>
<body>
<header>
    <span><b>{{ __('voyar::voyar.console_title') }}</b> · <span id="status">{{ __('voyar::voyar.console_connecting') }}</span></span>
    <a href="{{ route('client.services.show', $service) }}">← {{ __('voyar::voyar.console_back') }}</a>
</header>
<div id="screen"></div>
<script type="module">
    import RFB from 'https://cdn.jsdelivr.net/npm/@novnc/novnc@1.4.0/core/rfb.js';
    const status = document.getElementById('status');
    const rfb = new RFB(document.getElementById('screen'), @js($wsUrl), { credentials: { password: @js($vncPassword) } });
    rfb.scaleViewport = true;
    rfb.addEventListener('connect', () => status.textContent = @js($service->domain ?: 'VNC'));
    rfb.addEventListener('disconnect', () => status.textContent = @js(__('voyar::voyar.console_closed')));
</script>
</body>
</html>
