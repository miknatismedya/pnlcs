<style>
:root {
    --vc-primary: #5b4cf0;
    --vc-primary-2: #7a6cff;
    --vc-mint: #12c79a;
    --vc-ink: #0e0f2b;
    --vc-ink-2: #171a3d;
    --vc-text: #4a4f6a;
    --vc-muted: #7b8098;
    --vc-line: #e6e7f0;
    --vc-bg: #ffffff;
    --vc-tint: #f5f5fb;
    --vc-card: #ffffff;
    --vc-r: 20px;
    --vc-shadow: 0 1px 2px rgba(14,15,43,.04), 0 12px 32px -12px rgba(14,15,43,.12);
}
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body.vc { margin: 0; font-family: "Plus Jakarta Sans", system-ui, sans-serif; color: var(--vc-text); background: var(--vc-bg); line-height: 1.6; -webkit-font-smoothing: antialiased; }
.vc a { color: inherit; text-decoration: none; }
.vc h1, .vc h2, .vc h3, .vc h4 { color: var(--vc-ink); margin: 0; line-height: 1.15; letter-spacing: -.02em; }
.vc p { margin: 0; }
.vc ul, .vc ol { margin: 0; padding: 0; list-style: none; }
.vc-wrap { width: 100%; max-width: 1180px; margin: 0 auto; padding: 0 20px; }
.vc-wrap--narrow { max-width: 800px; }
.vc-center { text-align: center; }
.vc-h2 { font-size: clamp(26px, 3.6vw, 38px); font-weight: 800; }
.vc-h3 { font-size: 22px; font-weight: 800; margin-bottom: 18px !important; }
.vc-note { font-size: 13px; color: var(--vc-muted); margin-top: 18px !important; text-align: center; }
.vc-link { display: inline-flex; gap: 6px; align-items: center; font-weight: 700; color: var(--vc-primary) !important; margin-top: 20px; }

/* Buttons */
.vc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border-radius: 12px; font: inherit; font-weight: 700; font-size: 15px; border: 1.5px solid transparent; cursor: pointer; transition: transform .15s, background .2s, box-shadow .2s, color .2s; white-space: nowrap; }
.vc-btn:hover { transform: translateY(-1px); }
.vc-btn--sm { padding: 8px 14px; font-size: 14px; border-radius: 10px; }
.vc-btn--lg { padding: 15px 26px; font-size: 16px; }
.vc-btn--block { width: 100%; }
.vc-btn--primary { background: linear-gradient(135deg, var(--vc-primary), var(--vc-primary-2)); color: #fff !important; box-shadow: 0 10px 24px -10px rgba(91,76,240,.7); }
.vc-btn--dark { background: var(--vc-ink); color: #fff !important; }
.vc-btn--ghost { background: var(--vc-tint); color: var(--vc-ink) !important; border-color: var(--vc-line); }
.vc-btn--ghost:hover { border-color: var(--vc-primary); color: var(--vc-primary) !important; }
.vc-btn--light { background: #fff; color: var(--vc-ink) !important; }
.vc-btn--outline-light { border-color: rgba(255,255,255,.35); color: #fff !important; }
.vc-btn--outline-light:hover { background: rgba(255,255,255,.08); }
.vc-btn.is-disabled { opacity: .6; cursor: default; transform: none; }
.vc-icon-btn { display: inline-flex; align-items: center; gap: 6px; height: 40px; min-width: 40px; padding: 0 10px; justify-content: center; border-radius: 12px; border: 1px solid var(--vc-line); background: #fff; color: var(--vc-ink); font: inherit; font-weight: 700; font-size: 13px; cursor: pointer; }
.vc-icon-btn i { font-size: 18px; }

/* Chips & tags */
.vc-chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; color: var(--vc-primary); background: rgba(91,76,240,.08); }
.vc-chip--glass { color: #dcd8ff; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); }
.vc-pulse { width: 8px; height: 8px; border-radius: 50%; background: var(--vc-mint); box-shadow: 0 0 0 0 rgba(18,199,154,.6); animation: vc-pulse 2s infinite; }
@keyframes vc-pulse { 70% { box-shadow: 0 0 0 8px rgba(18,199,154,0); } 100% { box-shadow: 0 0 0 0 rgba(18,199,154,0); } }
.vc-tag { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; color: #0a8a6b; background: rgba(18,199,154,.14); }

/* Header */
.vc-header { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.85); backdrop-filter: saturate(1.4) blur(14px); border-bottom: 1px solid transparent; transition: border-color .2s, box-shadow .2s; }
.vc-header.is-scrolled { border-color: var(--vc-line); box-shadow: 0 6px 20px -16px rgba(14,15,43,.4); }
.vc-header__row { display: flex; align-items: center; gap: 28px; height: 70px; }
.vc-brand { display: inline-flex; align-items: center; gap: 10px; flex-shrink: 0; }
.vc-brand img { max-height: 38px; }
.vc-logo-mark { width: 34px; height: 34px; }
.vc-logo-text { font-size: 21px; font-weight: 800; color: var(--vc-ink); letter-spacing: -.03em; }
.vc-logo-text span { color: var(--vc-primary); font-weight: 600; }
.vc-brand--light .vc-logo-text { color: #fff; }
.vc-brand--light .vc-logo-text span { color: #b3abff; }
.vc-nav { display: flex; align-items: center; gap: 4px; flex: 1; }
.vc-nav__link { display: inline-flex; align-items: center; gap: 2px; padding: 8px 12px; border-radius: 10px; font: inherit; font-weight: 600; font-size: 15px; color: var(--vc-text); background: none; border: 0; cursor: pointer; }
.vc-nav__link:hover { color: var(--vc-ink); background: var(--vc-tint); }
.vc-nav__drop { position: relative; }
.vc-mega { position: absolute; top: calc(100% + 8px); left: -12px; width: 560px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px; padding: 10px; background: #fff; border: 1px solid var(--vc-line); border-radius: 18px; box-shadow: var(--vc-shadow); }
.vc-mega::before { content: ""; position: absolute; inset: -10px 0 auto; height: 10px; }
.vc-mega__item { display: flex; gap: 12px; padding: 12px; border-radius: 12px; }
.vc-mega__item:hover { background: var(--vc-tint); }
.vc-mega__icon { flex-shrink: 0; width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 20px; color: var(--vc-primary); background: rgba(91,76,240,.08); }
.vc-mega__item b { display: block; color: var(--vc-ink); font-size: 15px; }
.vc-mega__item small { font-size: 13px; color: var(--vc-muted); }
.vc-header__actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
.vc-lang { position: relative; }
.vc-lang__menu { position: absolute; right: 0; top: calc(100% + 8px); min-width: 160px; padding: 6px; background: #fff; border: 1px solid var(--vc-line); border-radius: 14px; box-shadow: var(--vc-shadow); }
.vc-lang__menu a { display: block; padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 14px; }
.vc-lang__menu a:hover, .vc-lang__menu a.is-active { background: var(--vc-tint); color: var(--vc-primary); }
.vc-burger { display: none; background: none; border: 0; font-size: 26px; color: var(--vc-ink); cursor: pointer; }
.vc-mobile { display: flex; flex-direction: column; gap: 2px; padding: 8px 20px 20px; border-top: 1px solid var(--vc-line); background: #fff; }
.vc-mobile a { display: flex; align-items: center; gap: 10px; padding: 12px 4px; font-weight: 600; color: var(--vc-ink); }
.vc-mobile a i { color: var(--vc-primary); font-size: 18px; }
.vc-mobile .vc-btn { margin-top: 8px; justify-content: center; }

/* Hero */
.vc-hero { position: relative; overflow: hidden; background: var(--vc-ink); color: #c9cbe4; padding: 88px 0 96px; }
.vc-hero__glow { position: absolute; inset: 0; background: radial-gradient(600px 380px at 78% 30%, rgba(122,108,255,.35), transparent 70%), radial-gradient(500px 300px at 10% 90%, rgba(18,199,154,.18), transparent 70%); }
.vc-hero::after { content: ""; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px); background-size: 22px 22px; mask-image: linear-gradient(to bottom, #000, transparent 85%); pointer-events: none; }
.vc-hero__grid { position: relative; z-index: 1; display: grid; grid-template-columns: 1.1fr .9fr; gap: 56px; align-items: center; }
.vc-hero h1 { color: #fff; font-size: clamp(38px, 5.4vw, 64px); font-weight: 800; margin: 22px 0 20px; letter-spacing: -.035em; }
.vc-hero h1 em { font-style: normal; background: linear-gradient(90deg, #a79dff, var(--vc-mint)); -webkit-background-clip: text; background-clip: text; color: transparent; }
.vc-hero__lead { font-size: 18px; max-width: 540px; }
.vc-hero__cta { display: flex; flex-wrap: wrap; gap: 12px; margin: 32px 0 28px; }
.vc-hero__trust { display: flex; flex-wrap: wrap; gap: 8px 22px; font-size: 14px; }
.vc-hero__trust i { color: var(--vc-mint); }

.vc-picker { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 24px; padding: 26px; backdrop-filter: blur(10px); box-shadow: 0 40px 80px -30px rgba(0,0,0,.6); }
.vc-picker__head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 22px; }
.vc-picker__head span { font-size: 14px; color: #9a9cc0; font-weight: 600; }
.vc-picker__head b { font-size: 26px; color: #fff; letter-spacing: -.02em; }
.vc-picker__bars { display: grid; gap: 14px; }
.vc-picker__bars label { display: flex; justify-content: space-between; font-size: 13px; font-weight: 700; color: #c9cbe4; margin-bottom: 6px; }
.vc-picker__bars em { font-style: normal; color: #fff; }
.vc-bar { height: 8px; border-radius: 99px; background: rgba(255,255,255,.08); overflow: hidden; }
.vc-bar i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--vc-primary-2), var(--vc-mint)); transition: width .35s cubic-bezier(.2,.8,.2,1); }
.vc-range { -webkit-appearance: none; appearance: none; width: 100%; height: 6px; margin: 26px 0 8px; border-radius: 99px; background: rgba(127,127,160,.3); outline: none; }
.vc-range::-webkit-slider-thumb { -webkit-appearance: none; width: 24px; height: 24px; border-radius: 50%; background: #fff; border: 5px solid var(--vc-primary); cursor: grab; box-shadow: 0 4px 12px rgba(0,0,0,.3); }
.vc-range::-moz-range-thumb { width: 16px; height: 16px; border-radius: 50%; background: #fff; border: 5px solid var(--vc-primary); cursor: grab; }
.vc-picker__hint { font-size: 12px; color: #8a8cb0; text-align: center; }
.vc-picker__prices { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 20px 0; }
.vc-picker__prices > div { padding: 14px; border-radius: 14px; background: rgba(255,255,255,.05); }
.vc-picker__prices small { display: block; font-size: 12px; color: #9a9cc0; font-weight: 600; }
.vc-picker__prices b { font-size: 22px; color: #fff; }
.vc-picker__prices span { font-size: 13px; color: #9a9cc0; }

/* Inner page hero */
.vc-phero { background: linear-gradient(180deg, var(--vc-tint), #fff); padding: 64px 0 40px; text-align: center; }
.vc-phero h1 { font-size: clamp(32px, 4.6vw, 50px); font-weight: 800; margin: 16px 0 14px; }
.vc-phero p { max-width: 640px; margin: 0 auto; font-size: 18px; }

/* Sections */
.vc-section { padding: 88px 0; }
.vc-section--flush { padding-top: 24px; }
.vc-section--tint { background: var(--vc-tint); }
.vc-section--dark { background: var(--vc-ink); color: #c9cbe4; }
.vc-head { max-width: 640px; margin: 0 auto 40px; text-align: center; }
.vc-head p { margin-top: 12px !important; font-size: 17px; }
.vc-head--left { margin-left: 0; text-align: left; }
.vc-head--left .vc-h2 i { color: var(--vc-primary); }
.vc-head--light .vc-h2 { color: #fff; }

/* Bento product lines */
.vc-bento { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
.vc-tile { position: relative; display: flex; flex-direction: column; gap: 10px; padding: 26px; border-radius: var(--vc-r); background: var(--vc-card); border: 1px solid var(--vc-line); transition: transform .2s, box-shadow .2s, border-color .2s; overflow: hidden; }
.vc-tile:hover { transform: translateY(-4px); box-shadow: var(--vc-shadow); border-color: rgba(91,76,240,.4); }
.vc-tile--cloud { background: linear-gradient(140deg, #f1efff, #fff 60%); }
.vc-tile--hourly { background: linear-gradient(140deg, #e8fbf5, #fff 60%); }
.vc-tile__icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 24px; color: #fff; background: linear-gradient(135deg, var(--vc-primary), var(--vc-primary-2)); }
.vc-tile--hourly .vc-tile__icon, .vc-tile--apps .vc-tile__icon { background: linear-gradient(135deg, #0fb28a, var(--vc-mint)); }
.vc-tile h3 { font-size: 20px; font-weight: 800; }
.vc-tile p { flex: 1; }
.vc-tile__foot { display: flex; align-items: center; gap: 6px; font-weight: 700; color: var(--vc-primary); font-size: 14px; }

/* Features */
.vc-features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
.vc-feature { padding: 26px; border-radius: var(--vc-r); background: var(--vc-card); border: 1px solid var(--vc-line); }
.vc-feature > i { display: inline-grid; place-items: center; width: 46px; height: 46px; border-radius: 12px; font-size: 22px; color: var(--vc-primary); background: rgba(91,76,240,.08); margin-bottom: 16px; }
.vc-feature h3 { font-size: 17px; font-weight: 800; margin-bottom: 6px !important; }
.vc-feature p { font-size: 15px; }

/* Split + steps */
.vc-split { display: grid; grid-template-columns: .9fr 1.1fr; gap: 56px; align-items: start; }
.vc-split .vc-h2 { margin-top: 14px; }
.vc-steps { display: grid; gap: 14px; counter-reset: s; }
.vc-steps li { display: flex; gap: 18px; padding: 22px; border-radius: var(--vc-r); border: 1px solid var(--vc-line); background: var(--vc-card); }
.vc-steps li > span { flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: #fff; background: var(--vc-ink); }
.vc-steps h3 { font-size: 17px; font-weight: 800; margin-bottom: 4px !important; }

/* Apps */
.vc-apps { display: grid; grid-template-columns: repeat(8, 1fr); gap: 12px; margin-bottom: 32px; }
.vc-app { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 20px 8px; border-radius: 16px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); text-align: center; }
.vc-app i { font-size: 30px; color: #b3abff; }
.vc-app b { color: #fff; font-size: 14px; }
.vc-apps--light { grid-template-columns: repeat(4, 1fr); margin-bottom: 0; }
.vc-apps--light .vc-app { background: var(--vc-card); border-color: var(--vc-line); padding: 24px 12px; }
.vc-apps--light .vc-app i { color: var(--vc-primary); }
.vc-apps--light .vc-app b { color: var(--vc-ink); font-size: 16px; }
.vc-apps--light .vc-app small { font-size: 13px; color: var(--vc-muted); }

/* Term switch */
.vc-seg { display: flex; width: fit-content; max-width: 100%; margin: 0 auto 32px; padding: 5px; gap: 4px; border-radius: 14px; background: var(--vc-tint); border: 1px solid var(--vc-line); overflow-x: auto; }
.vc-seg button { padding: 9px 18px; border: 0; border-radius: 10px; background: none; font: inherit; font-weight: 700; font-size: 14px; color: var(--vc-muted); cursor: pointer; white-space: nowrap; }
.vc-seg button.is-on { background: #fff; color: var(--vc-ink); box-shadow: 0 2px 8px rgba(14,15,43,.1); }

/* Plan cards */
.vc-plans { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
.vc-plan { position: relative; display: flex; flex-direction: column; padding: 24px; border-radius: var(--vc-r); background: var(--vc-card); border: 1px solid var(--vc-line); transition: box-shadow .2s, transform .2s; }
.vc-plan:hover { box-shadow: var(--vc-shadow); transform: translateY(-3px); }
.vc-plan.is-featured { border: 2px solid var(--vc-primary); box-shadow: 0 20px 40px -24px rgba(91,76,240,.6); }
.vc-plan__flag { position: absolute; top: 18px; right: 18px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; color: #fff; background: var(--vc-primary); }
.vc-plan h3 { font-size: 19px; font-weight: 800; }
.vc-plan__desc { font-size: 14px; color: var(--vc-muted); margin-top: 2px !important; }
.vc-plan__price { margin: 18px 0; min-height: 74px; }
.vc-plan__amount { font-size: 30px; font-weight: 800; color: var(--vc-ink); letter-spacing: -.03em; }
.vc-plan__amount small { font-size: 14px; font-weight: 600; color: var(--vc-muted); }
.vc-plan__billed { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 13px; color: var(--vc-muted); }
.vc-specs { display: grid; gap: 9px; margin-bottom: 22px !important; flex: 1; }
.vc-specs li { display: flex; align-items: center; gap: 9px; font-size: 14px; font-weight: 600; color: var(--vc-ink); }
.vc-specs i { color: var(--vc-primary); font-size: 17px; }

/* Table (hourly) */
.vc-table-wrap { overflow-x: auto; border: 1px solid var(--vc-line); border-radius: var(--vc-r); background: #fff; }
.vc-table { width: 100%; border-collapse: collapse; min-width: 720px; }
.vc-table th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--vc-muted); padding: 14px 20px; background: var(--vc-tint); }
.vc-table td { padding: 16px 20px; border-top: 1px solid var(--vc-line); vertical-align: middle; color: var(--vc-ink); }
.vc-table tr.is-featured td { background: rgba(91,76,240,.04); }
.vc-table .vc-specs { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 0 !important; }
.vc-table .vc-specs li { font-size: 13px; }
.vc-table__price { font-size: 18px; font-weight: 800; white-space: nowrap; }
.vc-table__price small { font-size: 12px; color: var(--vc-muted); font-weight: 600; }
.vc-notice { display: flex; gap: 14px; padding: 18px 20px; margin-bottom: 20px; border-radius: 16px; background: #fff8e6; border: 1px solid #f6dfa6; color: #6b5313; }
.vc-notice i { font-size: 22px; }
.vc-notice b { color: #4a3a0c; }
.vc-notice a { font-weight: 700; text-decoration: underline !important; }

/* Calculator & rules */
.vc-calc { padding: 28px; border-radius: var(--vc-r); background: #fff; border: 1px solid var(--vc-line); box-shadow: var(--vc-shadow); }
.vc-calc label { display: block; font-weight: 700; color: var(--vc-ink); font-size: 14px; margin-bottom: 18px; }
.vc-calc label b { float: right; color: var(--vc-primary); }
.vc-calc select { display: block; width: 100%; margin-top: 8px; padding: 11px 12px; border-radius: 12px; border: 1px solid var(--vc-line); font: inherit; background: #fff; }
.vc-calc .vc-range { margin: 12px 0 0; }
.vc-calc__total { padding: 18px; border-radius: 14px; background: var(--vc-ink); color: #c9cbe4; }
.vc-calc__total small { display: block; font-size: 13px; }
.vc-calc__total b { font-size: 30px; color: #fff; }
.vc-rules { display: grid; gap: 12px; }
.vc-rules li { display: flex; gap: 10px; padding: 16px; border-radius: 14px; background: #fff; border: 1px solid var(--vc-line); }
.vc-rules i { color: var(--vc-primary); font-size: 18px; flex-shrink: 0; margin-top: 2px; }

/* Checks, location, empty */
.vc-checks { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; max-width: 900px; margin: 0 auto !important; }
.vc-checks li { display: flex; align-items: center; gap: 10px; padding: 16px 18px; border-radius: 14px; background: #fff; border: 1px solid var(--vc-line); font-weight: 600; color: var(--vc-ink); }
.vc-checks i { color: var(--vc-mint); font-size: 20px; }
.vc-location { display: flex; align-items: center; gap: 20px; padding: 28px; margin-bottom: 24px; border-radius: var(--vc-r); background: linear-gradient(120deg, var(--vc-ink), var(--vc-ink-2)); color: #c9cbe4; }
.vc-location h2 { color: #fff; margin: 2px 0 4px !important; }
.vc-location small { font-weight: 700; color: #9a9cc0; text-transform: uppercase; letter-spacing: .08em; font-size: 12px; }
.vc-location__pin { width: 60px; height: 60px; border-radius: 18px; display: grid; place-items: center; font-size: 28px; color: #fff; background: linear-gradient(135deg, var(--vc-primary), var(--vc-mint)); flex-shrink: 0; }
.vc-empty { text-align: center; padding: 56px 24px; border-radius: var(--vc-r); border: 2px dashed var(--vc-line); background: #fff; }
.vc-empty > i { font-size: 40px; color: var(--vc-primary); }
.vc-empty h3 { margin: 10px 0 8px; font-size: 20px; }
.vc-empty p { max-width: 460px; margin: 0 auto 20px; }

/* FAQ */
.vc-faq { display: grid; gap: 10px; margin-top: 32px; }
.vc-faq__item { border-radius: 16px; border: 1px solid var(--vc-line); background: #fff; overflow: hidden; }
.vc-faq__item.is-open { border-color: rgba(91,76,240,.45); }
.vc-faq__item button { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 22px; border: 0; background: none; font: inherit; font-weight: 700; font-size: 16px; color: var(--vc-ink); text-align: left; cursor: pointer; }
.vc-faq__item button i { font-size: 20px; color: var(--vc-primary); transition: transform .2s; }
.vc-faq__item.is-open button i { transform: rotate(45deg); }
.vc-faq__item p { padding: 0 22px 20px; }

/* CTA + footer */
.vc-cta { padding: 0 0 88px; }
.vc-cta__box { display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap; padding: 44px 48px !important; border-radius: 28px; background: linear-gradient(120deg, var(--vc-primary), #3a2fd0 55%, #0e7f68); color: #e5e2ff; }
.vc-cta h2 { color: #fff; font-size: 28px; font-weight: 800; margin-bottom: 8px !important; }
.vc-cta__actions { display: flex; gap: 10px; flex-wrap: wrap; }
.vc-footer { background: var(--vc-ink); color: #9a9cc0; padding: 72px 0 0; font-size: 15px; }
.vc-footer__grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 40px; }
.vc-footer h4 { color: #fff; font-size: 15px; margin-bottom: 14px !important; }
.vc-footer__grid > div > a:not(.vc-brand) { display: block; padding: 4px 0; }
.vc-footer a:hover { color: #fff; }
.vc-footer__about { margin: 16px 0 !important; max-width: 320px; }
.vc-footer__contact { display: flex !important; align-items: center; gap: 8px; color: #fff; font-weight: 600; }
.vc-footer__bottom { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-top: 56px; padding-top: 22px !important; padding-bottom: 22px !important; border-top: 1px solid rgba(255,255,255,.08); font-size: 13px; }

/* Responsive */
@media (max-width: 1080px) {
    .vc-plans { grid-template-columns: repeat(2, 1fr); }
    .vc-apps { grid-template-columns: repeat(4, 1fr); }
    .vc-nav { display: none; }
    .vc-burger { display: block; }
}
@media (max-width: 860px) {
    .vc-hero { padding: 56px 0 64px; }
    .vc-hero__grid, .vc-split { grid-template-columns: 1fr; gap: 40px; }

    .vc-features, .vc-checks { grid-template-columns: 1fr 1fr; }
    .vc-footer__grid { grid-template-columns: 1fr 1fr; }
    .vc-cta__box { padding: 32px 24px !important; }
}
@media (max-width: 600px) {
    .vc-section { padding: 60px 0; }
    .vc-section--flush { padding-top: 16px; }
    .vc-hide-sm { display: none !important; }
    .vc-bento, .vc-plans, .vc-features, .vc-checks, .vc-footer__grid { grid-template-columns: 1fr; }

    .vc-apps { grid-template-columns: repeat(3, 1fr); }
    .vc-apps--light { grid-template-columns: 1fr 1fr; }
    .vc-picker { padding: 20px; }
    .vc-location { flex-direction: column; text-align: center; }
}
</style>
