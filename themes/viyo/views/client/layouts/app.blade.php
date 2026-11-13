{{--
    Viyo Cloud client layout: the core client/layouts/app.blade.php with
    the Viyo sidebar, top bar and footer. Everything else (alerts, SEO tags,
    hooks, scripts) is the core's, kept as it is so the pages work unchanged.
    When an update changes the core layout, Setup -> Updates lists this file.
--}}
<!DOCTYPE html>
<html lang="{{ $currentLocale ?? 'en' }}" dir="{{ $textDirection ?? 'ltr' }}" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Every script in this layout that posts with fetch() reads its CSRF
         token from here, the way the admin layout has always done
         (admin/layouts/app.blade.php:6). It was never added here, so those
         requests sent an empty token and were refused with a 419 — including
         the card payment form the Stripe module renders on an invoice, which
         reads exactly this tag and has been unable to take a payment in the
         client area. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield("title", __("client.my_account")) - {{ company_name() }}</title>
    @include('partials.seo-meta')
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @if(!empty($customFavicon))
    <link rel="icon" href="{{ $customFavicon }}" type="image/png">
    @endif
    @if(!empty($themeCssVars))
    <style id="theme-vars">{!! $themeCssVars !!}</style>
    @endif
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{
            --primary:var(--theme-primary, #1a4d80);
            --primary-light:var(--theme-primary-light, #e8f0fb);
            --primary-dark:var(--theme-primary-dark, #143d66);
            --accent:var(--theme-success, #06d6a0);
            --accent-dark:#05b589;
            --danger:#ef4444;
            --warning:#f59e0b;
            --success:var(--theme-success, #10b981);
            --bg:#f8fafc;
            --card:#ffffff;
            --border:var(--theme-border-color, #e2e8f0);
            --text:var(--theme-text-color, #1e293b);
            --muted:var(--theme-muted-color, #64748b);
            --shadow:0 1px 3px rgba(0,0,0,0.08),0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:0 4px 12px rgba(0,0,0,0.10),0 2px 4px rgba(0,0,0,0.06);
            --radius:12px;
            --radius-sm:8px;
        }
        /* The page is a column the full height of the window, and the content
           stretches to fill it - so on a short page the footer sits at the
           bottom of the screen instead of floating mid-page under the form. */
        body{font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;background:var(--bg);color:var(--text);line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;display:flex;flex-direction:column}

        /* ─── NAVBAR ─── */
        .pn-navbar{background:var(--card);border-bottom:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,0.06);position:sticky;top:0;z-index:1000}
        .pn-navbar-inner{max-width:1440px;margin:0 auto;padding:0 24px;display:flex;align-items:center;height:60px;gap:8px}
        .pn-brand{color:var(--primary);font-size:20px;font-weight:800;text-decoration:none;letter-spacing:-0.5px;flex-shrink:0;display:flex;align-items:center;gap:8px}
        .pn-brand-dot{width:8px;height:8px;background:var(--accent);border-radius:50%;display:inline-block}
        .pn-nav{display:flex;align-items:center;gap:2px;flex:1;padding:0 16px}
        .pn-nav-item{position:relative}
        .pn-nav-link{display:flex;align-items:center;gap:5px;padding:7px 12px;font-size:13.5px;font-weight:500;color:var(--muted);text-decoration:none;border-radius:8px;transition:all 0.15s;white-space:nowrap;cursor:pointer;border:none;background:none}
        .pn-nav-link:hover,.pn-nav-link.active{color:var(--primary);background:var(--primary-light)}
        .pn-nav-link.active{color:var(--primary);background:var(--primary-light)}
        .pn-chevron{width:12px;height:12px;transition:transform 0.2s}
        .pn-nav-item:hover .pn-chevron{transform:rotate(180deg)}
        .pn-dropdown{display:none;position:absolute;top:100%;left:0;min-width:200px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-md);z-index:999;padding:6px;overflow:hidden}
        .pn-nav-item:hover .pn-dropdown,.pn-nav-item.open .pn-dropdown{display:block}.pn-dropdown::before{content:"";position:absolute;top:-10px;left:0;right:0;height:10px}
        .pn-dropdown a{display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:13px;font-weight:500;color:var(--text);text-decoration:none;border-radius:8px;transition:all 0.12s}
        .pn-dropdown a:hover{background:var(--primary-light);color:var(--primary)}
        .pn-dropdown .sep{height:1px;background:var(--border);margin:4px 0}
        .pn-nav-right{display:flex;align-items:center;gap:10px;margin-left:auto;flex-shrink:0}
        .pn-user-btn{display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--primary-light);border:1px solid transparent;border-radius:8px;font-size:13px;font-weight:600;color:var(--primary);cursor:pointer;text-decoration:none;transition:all 0.15s}
        .pn-user-btn:hover{background:var(--primary);color:#fff}
        .pn-avatar{width:28px;height:28px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
        .pn-hamburger{display:none;background:none;border:1px solid var(--border);border-radius:8px;padding:6px 10px;cursor:pointer;flex-direction:column;gap:4px}
        .pn-hamburger span{display:block;width:18px;height:2px;background:var(--muted);transition:all 0.2s}
        .pn-mobile-menu{display:none;background:var(--card);border-top:1px solid var(--border);padding:12px 0}
        .pn-mobile-menu a{display:block;padding:9px 24px;font-size:13.5px;font-weight:500;color:var(--text);text-decoration:none}
        .pn-mobile-menu a:hover{background:var(--primary-light);color:var(--primary)}
        .pn-mobile-sec{padding:8px 24px 4px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.7px;margin-top:6px}
        @media(max-width:960px){.pn-nav{display:none}.pn-hamburger{display:flex}.pn-mobile-menu.open{display:block}}
        /* A phone has room for the brand, a few icons and the menu button. The
           words beside them (language name, the user's name, the login and
           sign-up buttons) pushed the menu button off the screen, so a visitor
           could not open the menu at all. They move into the menu instead. */
        @media(max-width:600px){
            .pn-navbar-inner{padding:0 12px;gap:6px}
            .pn-brand{min-width:0;flex-shrink:1;font-size:17px}
            .pn-brand-text{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
            .pn-nav-right{gap:6px;min-width:0}
            .pn-user-name,.pn-lang-name{display:none}
            /* app.css gives every .btn display:inline-flex !important */
            .pn-nav-right .pn-guest-btn{display:none !important}
            .pn-user-btn{padding:4px 6px}
        }
        /* Set by the script below when the bar is wider than the window - a
           long company name, a language with longer words - at any width. */
        .pn-navbar.pn-compact .pn-nav{display:none}
        .pn-navbar.pn-compact .pn-hamburger{display:flex}
        .pn-navbar.pn-compact .pn-mobile-menu.open{display:block}
        .pn-mobile-guest{display:flex;gap:8px;padding:12px 24px 4px}
        .pn-mobile-guest .btn{flex:1;justify-content:center}

        /* ─── LAYOUT ─── */
        .pn-main{max-width:1440px;margin:0 auto;padding:36px 40px;width:100%;flex:1}
        .pn-footer{background:var(--card);border-top:1px solid var(--border);padding:20px 24px;text-align:center;font-size:12.5px;color:var(--muted);margin-top:auto}
        .pn-footer a{color:var(--primary);text-decoration:none}
        .pn-footer a:hover{text-decoration:underline}

        /* ─── CARDS ─── */
        .pn-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:24px}
        .pn-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
        .pn-card-title{font-size:14px;font-weight:700;color:var(--text)}
        .pn-card-body{padding:28px 32px}
        .pn-card-body-flush{padding:0}

        /* ─── PAGE HEADER ─── */
        .pn-page-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap}
        .pn-page-title{font-size:22px;font-weight:700;color:var(--text);letter-spacing:-0.3px}
        .pn-page-subtitle{font-size:13px;color:var(--muted);margin-top:3px}

        /* ─── BUTTONS ─── */
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 18px;font-size:13.5px;font-weight:600;border-radius:var(--radius-sm);border:none;cursor:pointer;text-decoration:none;transition:all 0.15s;line-height:1.3;white-space:nowrap}
        .btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--primary-dark);color:#fff}
        .btn-accent{background:var(--accent);color:#fff}.btn-accent:hover{background:var(--accent-dark);color:#fff}
        .btn-outline{background:var(--card);color:var(--primary);border:1.5px solid var(--border)}.btn-outline:hover{border-color:var(--primary);background:var(--primary-light)}
        .btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{background:#dc2626;color:#fff}
        .btn-success{background:var(--success);color:#fff}.btn-success:hover{background:#059669;color:#fff}
        .btn-default{background:var(--card);color:var(--text);border:1.5px solid var(--border)}.btn-default:hover{border-color:var(--muted);color:var(--text)}
        .text-danger{color:var(--danger)}
        .btn-sm{padding:6px 12px;font-size:12.5px}
        .btn-xs{padding:4px 10px;font-size:12px}
        .btn svg,.btn i{flex-shrink:0;line-height:1}

        /* ─── BADGES ─── */
        .badge{display:inline-flex;align-items:center;padding:3px 10px;font-size:11.5px;font-weight:600;border-radius:999px;line-height:1.4}
        .badge-active,.badge-paid{background:#dcfce7;color:#15803d}
        .badge-pending{background:#fef9c3;color:#854d0e}
        .badge-unpaid{background:#fff7ed;color:#c2410c}
        .badge-overdue{background:#fee2e2;color:#991b1b}
        .badge-suspended,.badge-terminated,.badge-cancelled{background:var(--bg);color:var(--muted)}
        .badge-closed{background:var(--bg);color:var(--muted)}
        .badge-open{background:#dbeafe;color:#1d4ed8}
        .badge-answered,.badge-in-progress{background:#e0f2fe;color:#0369a1}
        .badge-customer-reply{background:#fce7f3;color:#be185d}
        .badge-low{background:#f0fdf4;color:#15803d}
        .badge-medium{background:#fff7ed;color:#c2410c}
        .badge-high{background:#fee2e2;color:#991b1b}
        .badge-yes{background:#dcfce7;color:#15803d}
        .badge-no{background:var(--bg);color:var(--muted)}

        /* ─── TABLES ─── */
        .pn-table{width:100%;border-collapse:collapse}
        .pn-table th{padding:11px 16px;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;background:var(--bg);border-bottom:1px solid var(--border);text-align:left;white-space:nowrap}
        .pn-table td{padding:13px 16px;font-size:13.5px;color:var(--text);border-bottom:1px solid var(--border);vertical-align:middle}
        .pn-table tbody tr:last-child td{border-bottom:none}
        .pn-table tbody tr:hover td{background:var(--bg)}
        .pn-table a{color:var(--primary);text-decoration:none;font-weight:500}
        .pn-table a:hover{text-decoration:underline}
        /* On a phone a list with five or six columns is wider than the screen.
           The table scrolls inside its card instead of dragging the whole page
           sideways. */
        @media(max-width:768px){.pn-table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch}}

        /* ─── FORMS ─── */
        .form-group{margin-bottom:20px}
        .form-label,.pn-label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px}
        .form-label .req{color:var(--danger)}
        /* .pn-input and .pn-label ride the same definitions: four screens - the
           bank-transfer notification, payment methods, two-factor setup, quotes -
           used them while nothing defined them, so their inputs rendered with no
           border and no background: invisible fields on a white card. */
        .form-control,.pn-input{width:100%;max-width:100%;min-width:0;padding:9px 13px;font-size:13.5px;color:var(--text);background:var(--card);border:1.5px solid var(--border);border-radius:var(--radius-sm);transition:border-color 0.15s,box-shadow 0.15s;outline:none;font-family:inherit}
        .form-control:focus,.pn-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,77,128,0.1)}
        textarea.form-control,textarea.pn-input{resize:vertical;min-height:120px;line-height:1.6}
        select.form-control{cursor:pointer}
        .form-hint{font-size:12px;color:var(--muted);margin-top:4px}
        .form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media(max-width:600px){.form-grid-2{grid-template-columns:minmax(0,1fr)}}

        /* ─── ALERTS ─── */
        .pn-alert{padding:12px 16px;border-radius:var(--radius-sm);font-size:13.5px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px;border:1px solid transparent}
        .pn-alert-success{background:#f0fdf4;border-color:#bbf7d0;color:#15803d}
        .pn-alert-error{background:#fef2f2;border-color:#fecaca;color:#991b1b}
        .pn-alert-danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
        .pn-alert-info{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
        .pn-alert-warning{background:#fffbeb;border-color:#fde68a;color:#92400e}
        .pn-alert ul{padding-left:16px;margin:4px 0 0}
        .pn-alert ul li{margin-bottom:2px}

        /* ─── STAT CARDS ─── */
        .pn-stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:24px}
        @media(max-width:800px){.pn-stat-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:480px){.pn-stat-grid{grid-template-columns:1fr 1fr}}
        .pn-stat{display:flex;flex-direction:column;padding:20px 24px;cursor:pointer;text-decoration:none;color:inherit;transition:box-shadow 0.15s}
        .pn-stat:hover{box-shadow:var(--shadow-md)}
        .pn-stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;flex-shrink:0}
        .pn-stat-icon svg{width:22px;height:22px}
        .pn-stat-val{font-size:28px;font-weight:800;color:var(--text);letter-spacing:-0.5px;line-height:1}
        .pn-stat-lbl{font-size:12.5px;font-weight:500;color:var(--muted);margin-top:5px}
        .pn-stat-icon-blue{background:#dbeafe;color:#1d4ed8}
        .pn-stat-icon-green{background:#dcfce7;color:#15803d}
        .pn-stat-icon-orange{background:#fff7ed;color:#c2410c}
        .pn-stat-icon-red{background:#fee2e2;color:#991b1b}

        /* ─── TWO COL ─── */
        .pn-2col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
        @media(max-width:768px){.pn-2col{grid-template-columns:minmax(0,1fr)}}

        /* ─── PROGRESS BARS ─── */
        .pn-progress-wrap{background:#e2e8f0;border-radius:999px;height:8px;overflow:hidden}
        .pn-progress-fill{height:100%;border-radius:999px;transition:width 0.4s}

        /* ─── EMPTY STATE ─── */
        .pn-empty{text-align:center;padding:56px 24px;color:var(--muted)}
        .pn-empty-icon{font-size:40px;margin-bottom:14px;opacity:0.5}
        .pn-empty p{font-size:14px;margin-bottom:16px}

        /* ─── BREADCRUMB ─── */
        .pn-back{display:inline-flex;align-items:center;gap:6px;color:var(--primary);font-size:13px;font-weight:500;text-decoration:none;margin-bottom:20px}
        .pn-back:hover{text-decoration:underline}
        .pn-back svg{width:14px;height:14px}

        /* ─── QUICK ACTIONS ─── */
        .pn-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}

        /* ─── DETAIL LIST ─── */
        .pn-detail-list{list-style:none}
        .pn-detail-list li{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:13.5px}
        .pn-detail-list li:last-child{border-bottom:none}
        .pn-detail-list .key{color:var(--muted);font-weight:500}
        .pn-detail-list .val{font-weight:600;color:var(--text);text-align:right}

        /* ─── TICKET MESSAGES ─── */
        .pn-msg{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:14px;overflow:hidden}
        .pn-msg.staff{border-left:3px solid var(--primary)}
        .pn-msg-head{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
        .pn-msg.staff .pn-msg-head{background:#eff6ff}
        .pn-msg-author{font-size:13px;font-weight:700;color:var(--text)}
        .pn-msg.staff .pn-msg-author{color:var(--primary)}
        .pn-msg-date{font-size:12px;color:var(--muted)}
        .pn-msg-body{padding:16px 18px;font-size:13.5px;line-height:1.75;color:var(--text);white-space:pre-wrap}

        /* ─── GATEWAY TABS ─── */
        .gw-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}
        .gw-tab{padding:8px 18px;border:1.5px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;font-size:13px;font-weight:600;color:var(--muted);background:var(--card);transition:all 0.15s}
        .gw-tab:hover{border-color:var(--primary);color:var(--primary)}
        .gw-tab.active{background:var(--primary);border-color:var(--primary);color:#fff}
        .gw-panel{display:none}.gw-panel.active{display:block}
        .gw-form-box{background:var(--bg);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:18px}

        /* ─── PRODUCT CARDS ─── */
        .pn-product-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
        @media(max-width:900px){.pn-product-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:560px){.pn-product-grid{grid-template-columns:1fr}}
        .pn-product-card{display:flex;flex-direction:column;padding:24px;transition:all 0.2s;position:relative}
        .pn-product-card:hover{box-shadow:var(--shadow-md)}
        .pn-product-card.featured{border-color:var(--primary)}
        .pn-product-price{font-size:26px;font-weight:800;color:var(--text);letter-spacing:-0.5px;margin:8px 0 4px}
        .pn-product-price .cycle{font-size:13px;font-weight:400;color:var(--muted)}
        .pn-popular{position:absolute;top:-1px;right:18px;background:var(--accent);color:#fff;font-size:10.5px;font-weight:700;padding:3px 10px;border-radius:0 0 8px 8px;letter-spacing:0.3px;text-transform:uppercase}

        /* ─── CHECKOUT ─── */
        .pn-checkout-grid{display:grid;grid-template-columns:1fr 320px;gap:24px}
        @media(max-width:900px){.pn-checkout-grid{grid-template-columns:minmax(0,1fr)}}
        .pn-pay-option{border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:13px 16px;cursor:pointer;margin-bottom:8px;display:flex;align-items:center;gap:12px;transition:all 0.15s}
        .pn-pay-option:hover{border-color:var(--primary);background:var(--primary-light)}
        .pn-pay-option.selected{border-color:var(--primary);background:var(--primary-light)}
        .pn-pay-option input[type=radio]{margin:0;accent-color:var(--primary)}

        /* ─── AMOUNT PRESETS ─── */
        .pn-amount-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px}
        .pn-amount-btn{padding:10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-weight:700;color:var(--text);background:var(--card);cursor:pointer;text-align:center;transition:all 0.15s}
        .pn-amount-btn:hover,.pn-amount-btn.selected{border-color:var(--primary);background:var(--primary-light);color:var(--primary)}

        /* ─── AFFILIATE ─── */
        .pn-aff-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
        @media(max-width:768px){.pn-aff-grid{grid-template-columns:repeat(2,1fr)}}
        .pn-aff-stat{text-align:center;padding:20px}
        .pn-aff-val{font-size:28px;font-weight:800;color:var(--primary);letter-spacing:-0.5px}
        .pn-aff-lbl{font-size:11.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;margin-top:6px}

        /* ─── WELCOME BANNER ─── */
        .pn-welcome{background:linear-gradient(135deg,var(--primary) 0%,#1e5fa0 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
        .pn-welcome-text h2{font-size:20px;font-weight:700;color:#fff;letter-spacing:-0.3px}
        .pn-welcome-text p{font-size:13.5px;color:rgba(255,255,255,0.75);margin-top:4px}
        .pn-welcome-credit{background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);border-radius:var(--radius-sm);padding:12px 18px;text-align:right}
        .pn-welcome-credit .credit-lbl{font-size:11px;color:rgba(255,255,255,0.65);font-weight:600;text-transform:uppercase;letter-spacing:0.5px}
        .pn-welcome-credit .credit-val{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.5px}

        /* ─── CART ─── */
        .pn-cart-grid{display:grid;grid-template-columns:1fr 300px;gap:20px}
        @media(max-width:900px){.pn-cart-grid{grid-template-columns:minmax(0,1fr)}}
        .pn-order-row{display:flex;justify-content:space-between;padding:8px 0;font-size:13.5px;border-bottom:1px solid var(--border)}
        .pn-order-row:last-child{border-bottom:none;font-weight:700;font-size:15px;padding-top:12px}
        .pn-order-row .key{color:var(--muted)}

        /* ─── MISC ─── */
        .pn-section-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);margin-bottom:12px}
        .pn-code{background:var(--bg);padding:3px 8px;border-radius:6px;font-family:monospace;font-size:12.5px;color:var(--text)}
        .link{color:var(--primary);text-decoration:none;font-weight:500}
        .link:hover{text-decoration:underline}
        .text-muted{color:var(--muted)}
        .text-sm{font-size:12.5px}
        .mt-16{margin-top:16px}
        .mt-24{margin-top:24px}
        .mb-16{margin-bottom:16px}
        .mb-24{margin-bottom:24px}
        .flex{display:flex}
        .items-center{align-items:center}
        .gap-8{gap:8px}
        .gap-12{gap:12px}
        .gap-16{gap:16px}
        .w-full{width:100%}
        code{font-family:monospace}
    
        /* ===== DARK MODE ===== */
        :root[data-theme="dark"] {
            --bg:#0f172a; --card:#1e293b; --border:#334155;
            --text:#e2e8f0; --muted:#94a3b8;
        }
        :root[data-theme="dark"] body { background:var(--bg) !important; color:var(--text) !important; }
        :root[data-theme="dark"] .pn-navbar { background:#1e293b !important; border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-card { background:#1e293b !important; border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-table th { background:#1e293b !important; }
        :root[data-theme="dark"] .pn-table td { border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-table tbody tr:hover td { background:#334155 !important; }
        :root[data-theme="dark"] .form-control, :root[data-theme="dark"] .pn-input { background:#1e293b !important; border-color:#475569 !important; color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-footer { background:#1e293b !important; border-color:var(--border) !important; color:var(--muted) !important; }
        :root[data-theme="dark"] .pn-dropdown { background:#1e293b !important; border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-dropdown a { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-dropdown a:hover { background:#334155 !important; }
        :root[data-theme="dark"] .pn-card-title { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-page-title { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-stat-val { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-detail-list .val { color:#e2e8f0 !important; }
        :root[data-theme="dark"] .pn-detail-list li { border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-msg { background:#1e293b !important; border-color:var(--border) !important; }
        :root[data-theme="dark"] .pn-msg-head { background:#0f172a !important; }
        :root[data-theme="dark"] .pn-msg-body { color:#cbd5e1 !important; }
        :root[data-theme="dark"] .pn-alert-info { background:#1e3a5f !important; border-color:#2563eb !important; color:#bfdbfe !important; }
        :root[data-theme="dark"] .pn-alert-success { background:#14532d !important; border-color:#16a34a !important; color:#86efac !important; }
        :root[data-theme="dark"] .pn-alert-warning { background:#451a03 !important; border-color:#d97706 !important; color:#fde68a !important; }
        :root[data-theme="dark"] .pn-alert-error, :root[data-theme="dark"] .pn-alert-danger { background:#450a0a !important; border-color:#dc2626 !important; color:#fca5a5 !important; }
        /* Badges carry fixed light pastels; in the dark they turned into pale
           chips with unreadable text. Deep tones, same meaning. */
        :root[data-theme="dark"] .badge-active, :root[data-theme="dark"] .badge-paid { background:#14532d !important; color:#86efac !important; }
        :root[data-theme="dark"] .badge-pending { background:#422006 !important; color:#fde047 !important; }
        :root[data-theme="dark"] .badge-unpaid { background:#431407 !important; color:#fdba74 !important; }
        :root[data-theme="dark"] .badge-overdue { background:#450a0a !important; color:#fca5a5 !important; }
        :root[data-theme="dark"] .badge-suspended, :root[data-theme="dark"] .badge-terminated,
        :root[data-theme="dark"] .badge-cancelled, :root[data-theme="dark"] .badge-closed { background:#1e293b !important; color:var(--muted) !important; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ $activeThemeAssets ?? url('/themes/viyo/assets') }}/css/theme.css">
    @yield("styles")

    @if(($textDirection ?? 'ltr') === 'rtl')
    <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
    @endif
    {!! hook_output('ClientAreaHeadOutput', ['user' => auth()->user()]) !!}
    @include('partials.dialog-boot')
    @include('partials.locale-alternates')
</head>
<body class="vx vx--app {{ auth()->check() ? 'vx--in' : 'vx--out' }}">
@if(session('impersonating_admin_id'))
<div style="background:#f59e0b;color:#000;padding:8px 16px;text-align:center;font-weight:600;font-size:13px;position:sticky;top:0;z-index:9999;">
    {{ __("client.impersonation.viewing_as") }} {{ auth()->user()->full_name ?? auth()->user()->email }}.
    <a href="{{ route('admin.clients.stop-impersonation') }}" style="color:#000;text-decoration:underline;margin-left:10px;">
        &larr; {{ __("client.impersonation.return_to_admin") }}
    </a>
</div>
@endif

@include('client.layouts.partials.viyo-nav')

@auth<div class="vx-page">@endauth

<div class="pn-main">
    @if(session("success"))
        <div class="pn-alert pn-alert-success">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session("success") }}
        </div>
    @endif
    @if(session("error"))
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session("error") }}
        </div>
    @endif
    {{-- Validation errors. Only the flash keys were drawn here, so every
         refused form (the free-plan limit, the cycle guard, checkout rules)
         bounced back to the same page with no explanation at all. --}}
    @if($errors->any())
        <div class="pn-alert pn-alert-error">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                @foreach($errors->all() as $validationError)
                    <div>{{ $validationError }}</div>
                @endforeach
            </div>
        </div>
    @endif
    @if(session("info"))
        <div class="pn-alert pn-alert-info">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session("info") }}
        </div>
    @endif
    @if(session("warning"))
        <div class="pn-alert pn-alert-warning">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            {{ session("warning") }}
        </div>
    @endif

    {{-- Standing reminder for an address that was never proved. Not on the
         verification pages themselves, where it would only repeat what the
         page already says. --}}
    @if(auth()->check()
        && \App\Http\Controllers\Client\EmailVerificationController::required()
        && ! auth()->user()->hasVerifiedEmail()
        && ! request()->routeIs('client.verification.*'))
        <div class="pn-alert pn-alert-warning">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>
                {{ __('client.email_verify.banner', ['email' => auth()->user()->email]) }}
                <a href="{{ route('client.verification.notice') }}" style="text-decoration:underline;font-weight:600;">{{ __('client.email_verify.banner_link') }}</a>
            </span>
        </div>
    @endif

    @yield("content")
</div>

@include('client.layouts.partials.viyo-footer')
@auth</div>@endauth

@yield("scripts")
{{-- A page may push instead of yielding; without this the block is silently discarded. --}}
@stack('scripts')
<script>
// One open menu at a time; a click outside closes it.
document.addEventListener("click", function (e) {
    document.querySelectorAll("details.vx-menu[open]").forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute("open"); });
});
</script>

    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.1.0/fonts/remixicon.css" rel="stylesheet">
    <script>
    function toggleDarkMode(){var d=document.documentElement.getAttribute('data-theme')==='dark';var n=d?'light':'dark';document.documentElement.setAttribute('data-theme',n);document.cookie='pnlcs_theme='+n+';path=/;max-age=31536000;SameSite=Lax';var li=document.getElementById('lightIcon'),di=document.getElementById('darkIcon');if(li&&di){li.style.display=d?'':'none';di.style.display=d?'none':'';}}
    (function(){var m=document.cookie.match(/pnlcs_theme=(\w+)/);if(m&&m[1]==='dark'){document.documentElement.setAttribute('data-theme','dark');var li=document.getElementById('lightIcon'),di=document.getElementById('darkIcon');if(li)li.style.display='';if(di)di.style.display='none';}})();
    </script>
    {!! hook_output('ClientAreaFooterOutput', ['user' => auth()->user()]) !!}
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('form').forEach(function(f){
        f.addEventListener('submit',function(){
            var btn=f.querySelector('button[type=submit],input[type=submit]');
            if(btn&&!btn.disabled){btn.disabled=true;btn.classList.add('submitting');
            setTimeout(function(){btn.disabled=false;btn.classList.remove('submitting');},5000);}
        });
    });
});
</script>
