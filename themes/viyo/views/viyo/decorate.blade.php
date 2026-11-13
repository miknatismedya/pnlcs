{{--
    Renders a core view and dresses it in the Viyo look, without copying it.

    $__core  core view path under resources/views, without ".blade.php"
    $__vars  the data the core view was given
    $__mode  "app" (client area layout) or "auth" (stand-alone sign-in pages)

    The core page is rendered as it is, then:
      - the theme's stylesheet is added to <head>;
      - in "app" mode the core menu bar and footer are swapped for ours;
      - in "auth" mode a brand panel is put beside the form.
    Each swap looks for a fixed piece of the core markup. When an update
    changes that markup the swap is skipped and the core part stays, so a
    pnlcs update can make the page plainer, never broken.
--}}
@php
    $__html = view()->file(
        resource_path('views/'.$__core.'.blade.php'),
        \Illuminate\Support\Arr::except($__vars, ['__env', 'app', '__core', '__vars', '__mode'])
    )->render();

    $__assets = $__vars['activeThemeAssets'] ?? url('/themes/viyo/assets');
    $__head = '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        .'<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
        .'<link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">'
        .'<link rel="stylesheet" href="'.e($__assets).'/css/theme.css?v='.@filemtime(base_path('themes/viyo/assets/css/theme.css')).'">';

    $__swap = function (string $html, string $open, string $close, string $with): string {
        $start = strpos($html, $open);
        if ($start === false) {
            return $html;
        }
        $end = strpos($html, $close, $start);
        if ($end === false) {
            return $html;
        }

        return substr_replace($html, $with, $start, $end + strlen($close) - $start);
    };

    if (($pos = stripos($__html, '</head>')) !== false) {
        $__html = substr_replace($__html, $__head, $pos, 0);
    }

    $__signedIn = auth()->check();
    $__bodyClass = 'vx vx--'.$__mode.' '.($__signedIn ? 'vx--in' : 'vx--out');

    if ($__mode === 'app') {
        $__before = $__html;
        $__html = $__swap($__html, '<nav class="pn-navbar">', '</nav>', view('viyo.shell-nav')->render());
        $__html = $__swap($__html, '<footer class="pn-footer">', '</footer>', view('viyo.shell-footer')->render());
        // Signed in, the page sits in a column beside the sidebar. The column
        // is opened only when both swaps worked, so it is always closed.
        if ($__signedIn && str_contains($__html, '<!--vx:nav-->') && str_contains($__html, '<!--vx:end-->')) {
            $__html = str_replace('<!--vx:nav-->', '<div class="vx-page">', $__html);
            $__html = str_replace('<!--vx:end-->', '</div>', $__html);
        } elseif (! $__signedIn) {
            $__html = str_replace(['<!--vx:nav-->', '<!--vx:end-->'], '', $__html);
        } else {
            $__html = $__before;
        }
    } elseif ($__mode === 'auth' && preg_match('/<body[^>]*>/', $__html) && stripos($__html, '</body>') !== false) {
        $__aside = view('viyo.auth-aside')->render();
        $__html = preg_replace_callback('/<body[^>]*>/', fn ($m) => $m[0].$__aside.'<div class="vx-auth__form">', $__html, 1);
        $__html = substr_replace($__html, '</div>', strripos($__html, '</body>'), 0);
    }

    // One class on <body> switches the stylesheet on; nothing in the core
    // markup is renamed.
    $__html = preg_replace_callback('/<body([^>]*)>/', function ($m) use ($__bodyClass) {
        if (preg_match('/class="([^"]*)"/', $m[1])) {
            return '<body'.preg_replace('/class="([^"]*)"/', 'class="$1 '.$__bodyClass.'"', $m[1]).'>';
        }

        return '<body class="'.$__bodyClass.'"'.$m[1].'>';
    }, $__html, 1);
@endphp
{!! $__html !!}
