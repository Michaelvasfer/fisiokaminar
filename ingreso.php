<?php
require_once 'db.php';
ensurePublicIntakeSchema($pdo);
ensureProtocolSchema($pdo);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingreso Inicial | KaminarFisio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        :root {
            --brand: #17b8d0;
            --brand-dark: #0d5062;
            --brand-deep: #073848;
            --brand-soft: #eaf9fc;
            --ink: #153142;
            --muted: #607d8f;
            --line: #d8e8ee;
            --bg: #f4f9fb;
            --success: #0f9f6e;
            --warning: #c27a08;
            --shadow: 0 18px 44px rgba(8, 52, 66, 0.10);
            --shadow-soft: 0 10px 26px rgba(8, 52, 66, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(23, 184, 208, 0.10), transparent 28%),
                linear-gradient(180deg, #fbfeff 0%, #f3f8fb 46%, #f9fcfd 100%);
            min-height: 100vh;
        }

        .page-bar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(216, 232, 238, 0.88);
        }
        .page-bar-inner {
            max-width: 1120px;
            margin: 0 auto;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-mark {
            width: 32px;
            height: 32px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(23, 184, 208, 0.18) 0%, rgba(13, 80, 98, 0.14) 100%);
            color: var(--brand-dark);
        }
        .brand-logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: inherit;
            display: block;
        }
        .hero-brand {
            width: 78px;
            height: 78px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.10);
            box-shadow: 0 10px 24px rgba(6, 44, 57, 0.22);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 12px;
        }
        .hero-brand img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .page-brand {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--ink);
        }
        .page-caption {
            font-size: 0.78rem;
            color: var(--muted);
        }
        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .page-nav,
        .page-cta,
        .process-band,
        .faq-section,
        .page-footer {
            display: none;
        }
        .page-nav {
            align-items: center;
            gap: 8px;
        }
        .nav-link {
            border: none;
            background: transparent;
            color: var(--muted);
            font: inherit;
            font-size: 0.84rem;
            font-weight: 700;
            padding: 9px 12px;
            border-radius: 999px;
            cursor: pointer;
            transition: 0.18s ease;
        }
        .nav-link:hover {
            color: var(--ink);
            background: rgba(23, 184, 208, 0.08);
        }
        .page-cta {
            border: none;
            border-radius: 14px;
            padding: 11px 16px;
            background: #0f1f31;
            color: #fff;
            font: inherit;
            font-size: 0.84rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(15, 31, 49, 0.16);
        }

        .shell { max-width: 1120px; margin: 0 auto; padding: 22px 18px 56px; }
        .hero, .layout { display: grid; gap: 22px; }
        .hero { grid-template-columns: 1.12fr 0.88fr; align-items: stretch; margin-bottom: 22px; }
        .layout { grid-template-columns: 0.74fr 1.26fr; align-items: start; }
        .panel, .slot-card, .upload-item, .proof-card, .assurance-card, .faq-item {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(179, 214, 224, 0.74);
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(10px);
        }
        .hero-main, .hero-side {
            border-radius: 30px;
            padding: 28px;
            box-shadow: var(--shadow);
        }
        .panel { border-radius: 28px; padding: 24px; }
        .hero-main {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.14), transparent 34%),
                linear-gradient(160deg, #0f6c82 0%, #0a495a 56%, #072f3d 100%);
            color: #fff;
            border: 1px solid rgba(10, 90, 109, 0.48);
        }
        .hero-main::before {
            content: "";
            position: absolute;
            inset: auto -70px -90px auto;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.16), transparent 68%);
            pointer-events: none;
        }
        .hero-main::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(115deg, rgba(255,255,255,0.08), transparent 26%),
                repeating-linear-gradient(135deg, rgba(255,255,255,0.05) 0, rgba(255,255,255,0.05) 1px, transparent 1px, transparent 15px);
            opacity: 0.28;
            pointer-events: none;
        }
        .hero-main > * { position: relative; z-index: 1; }
        .hero-side {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 18px;
            background:
                linear-gradient(180deg, rgba(12, 80, 97, 0.98) 0%, rgba(8, 56, 71, 0.98) 100%);
            color: rgba(255,255,255,0.92);
            border: 1px solid rgba(84, 180, 198, 0.22);
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #d8f7fc;
            background: rgba(255, 255, 255, 0.10);
            border: 1px solid rgba(255,255,255,0.14);
        }
        h1, h2, .result-title, .score-value, .confirm-card strong { font-family: 'Manrope', sans-serif; }
        h1 { font-size: clamp(2rem, 4vw, 3.2rem); line-height: 1.02; margin: 16px 0 14px; letter-spacing: -0.035em; max-width: 11ch; }
        h2 { margin: 0 0 10px; font-size: 1.08rem; }
        .hero-main p, .panel-head p, .help, .step-copy, .summary, .mini-item span, .choice-btn span, .flag-option span, .slot-card span {
            color: var(--muted);
            line-height: 1.6;
        }
        .hero-main p { color: rgba(233, 249, 252, 0.92); max-width: 58ch; }
        .hero-side h2,
        .hero-side strong,
        .hero-side p,
        .hero-side span,
        .hero-side small { color: inherit; }
        .hero-badges, .actions, .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .hero-badges { margin-top: 20px; }
        .hero-actions { margin-top: 22px; }
        .pill, .panel-badge {
            border-radius: 999px;
            padding: 8px 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .pill { background: #fff; border: 1px solid var(--line); font-size: 0.84rem; color: var(--ink); }
        .hero-main .pill { background: rgba(255, 255, 255, 0.10); border: 1px solid rgba(255,255,255,0.12); color: #f4fcfe; }
        .panel-badge { background: #f0fbff; color: var(--brand-dark); font-size: 0.76rem; }
        .mini-list, .stepper, .result-shell, .choice-grid, .flag-grid, .exercise-grid, .slot-grid, .upload-list, .proof-grid, .assurance-grid, .faq-list { display: grid; gap: 12px; }
        .mini-item { display: grid; grid-template-columns: 42px 1fr; gap: 12px; align-items: start; }
        .mini-icon, .step-number, .score-value {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .mini-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: rgba(255,255,255,0.10);
            color: #dff7fb;
            border: 1px solid rgba(255,255,255,0.12);
        }
        .mini-item strong, .choice-btn strong, .exercise-item strong, .slot-card strong { display: block; margin-bottom: 4px; }
        .step-chip {
            display: grid;
            grid-template-columns: 40px 1fr;
            gap: 12px;
            padding: 14px;
            border-radius: 20px;
            border: 1px solid rgba(196, 222, 231, 0.9);
            background: linear-gradient(180deg, #ffffff 0%, #f8fcfd 100%);
            transition: 0.2s ease;
        }
        .step-chip.active { border-color: rgba(14, 165, 183, 0.55); background: linear-gradient(180deg, #ffffff 0%, #eefcff 100%); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(14, 165, 183, 0.12); }
        .step-chip.done { border-color: rgba(15, 159, 110, 0.35); background: rgba(15, 159, 110, 0.07); }
        .step-chip.done .step-title { text-decoration: line-through; color: var(--muted); }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            font-weight: 800;
            background: #eff6fa;
            color: var(--muted);
            transition: 0.3s ease;
        }
        .step-chip.active .step-number { background: var(--brand); color: #fff; box-shadow: 0 4px 12px rgba(23, 184, 208, 0.32); }
        .step-chip.done .step-number { background: var(--success); color: #fff; }
        .step-title { font-weight: 800; margin-bottom: 2px; }
        .trust-card, .result-card, .confirm-card, .upload-area, .status-note {
            border-radius: 22px;
            padding: 18px;
        }
        .trust-card { margin-top: 18px; background: linear-gradient(180deg, rgba(23, 184, 208, 0.10) 0%, rgba(255,255,255,0.94) 100%); border: 1px solid rgba(23, 184, 208, 0.16); }
        .panel-head { display: flex; justify-content: space-between; align-items: start; gap: 12px; margin-bottom: 18px; }
        .proof-strip { margin-bottom: 22px; }
        .stats-band {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 22px;
        }
        .stat-tile {
            display: grid;
            grid-template-columns: 42px 1fr;
            gap: 12px;
            align-items: center;
            padding: 16px 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(179, 214, 224, 0.74);
            box-shadow: var(--shadow-soft);
        }
        .stat-tile .material-icons-outlined {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, rgba(23, 184, 208, 0.16) 0%, rgba(23, 184, 208, 0.08) 100%);
            color: var(--brand-dark);
            font-size: 20px;
        }
        .stat-tile strong {
            display: block;
            margin-bottom: 3px;
            font-size: 0.92rem;
        }
        .stat-tile span {
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.45;
        }
        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 14px;
        }
        .section-head small {
            display: block;
            color: var(--brand-dark);
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 4px;
        }
        .section-head p {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.55;
        }
        .proof-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .proof-card {
            padding: 18px 16px;
            min-height: 152px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .proof-card-top {
            width: 44px;
            height: 44px;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, rgba(23, 184, 208, 0.16) 0%, rgba(23, 184, 208, 0.08) 100%);
            color: var(--brand-dark);
        }
        .proof-card strong, .assurance-card strong, .faq-item strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.96rem;
            line-height: 1.35;
        }
        .proof-card p, .assurance-card p, .faq-item p {
            margin: 0;
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.55;
        }
        .assurance-grid { grid-template-columns: 1.08fr 0.92fr; margin-bottom: 22px; }
        .assurance-card { padding: 22px; }
        .process-list {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .process-item {
            display: grid;
            grid-template-columns: 34px 1fr;
            gap: 10px;
            align-items: start;
            padding: 12px 0;
            border-top: 1px solid rgba(212, 230, 238, 0.9);
        }
        .process-item:first-child { border-top: none; padding-top: 0; }
        .process-item span {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: #edfaff;
            color: var(--brand-dark);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }
        .faq-list { margin-top: 12px; }
        .faq-item { padding: 16px 18px; }
        .quote-box {
            margin-top: 16px;
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
        }
        .quote-box p {
            margin: 0;
            font-size: 0.92rem;
            color: #f3fbfd;
            line-height: 1.6;
        }
        .quote-box small {
            display: block;
            margin-top: 8px;
            color: rgba(226, 246, 250, 0.78);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .step-view { display: none; }
        .step-view.active { display: block; animation: fadeSlideIn 0.38s ease both; }
        @keyframes fadeSlideIn {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .form-grid, .choice-grid, .flag-grid, .exercise-grid, .slot-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .field { margin-bottom: 16px; }
        label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
        }
        input, select, textarea {
            width: 100%;
            border: 1.5px solid #d6e6ee;
            border-radius: 16px;
            background: #fff;
            padding: 14px 15px;
            font: inherit;
            color: var(--ink);
            transition: 0.18s ease;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: rgba(14, 165, 183, 0.7); box-shadow: 0 0 0 4px rgba(14, 165, 183, 0.12); }
        textarea { min-height: 108px; resize: vertical; }
        .choice-btn, .flag-option, .slot-card, .upload-item { border: 1px solid var(--line); background: #fff; border-radius: 18px; }
        .choice-btn {
            padding: 14px;
            text-align: left;
            cursor: pointer;
            font: inherit;
            transition: 0.18s ease;
        }
        .choice-btn.active, .slot-card.active { border-color: rgba(14, 165, 183, 0.6); background: #eefcff; box-shadow: inset 0 0 0 1px rgba(14, 165, 183, 0.18); }
        .flag-option { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; }
        .flag-option input { width: 18px; height: 18px; margin-top: 2px; }
        .btn {
            border: none;
            border-radius: 18px;
            padding: 14px 18px;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            transition: 0.22s ease;
            position: relative;
        }
        .btn:hover:not(:disabled) { transform: translateY(-1px); }
        .btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
        .btn.loading { pointer-events: none; color: transparent; }
        .btn.loading::after {
            content: '';
            position: absolute;
            inset: 0;
            margin: auto;
            width: 22px; height: 22px;
            border: 3px solid rgba(255,255,255,0.28);
            border-top-color: #fff;
            border-radius: 50%;
            animation: btnSpin 0.6s linear infinite;
        }
        @keyframes btnSpin {
            to { transform: rotate(360deg); }
        }
        .btn-primary { background: linear-gradient(135deg, #1bd0e8 0%, #139ab3 100%); color: #042f3c; box-shadow: 0 12px 26px rgba(8, 90, 109, 0.24); }
        .btn-primary:active:not(:disabled) { transform: scale(0.97); }
        .btn-secondary { background: #fff; color: var(--ink); border: 1px solid var(--line); }
        .hero-main .btn-secondary,
        .hero-side .btn-secondary { background: rgba(255,255,255,0.10); color: #f1fcfe; border: 1px solid rgba(255,255,255,0.14); }
        .btn-soft { background: #eefcff; color: var(--brand-dark); }
        /* --- Progress bar --- */
        .progress-bar {
            height: 5px;
            background: rgba(14, 165, 183, 0.12);
            border-radius: 99px;
            margin-bottom: 18px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--brand) 0%, #0ea5b7 100%);
            border-radius: 99px;
            transition: width 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .score-wrap { display: grid; grid-template-columns: 1fr auto; gap: 14px; align-items: center; }
        .score-value {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            font-size: 1.35rem;
            font-weight: 800;
            background: var(--brand-soft);
            color: var(--brand-dark);
        }
        .result-card { border: 1px solid rgba(14, 165, 183, 0.18); background: linear-gradient(180deg, rgba(14,165,183,0.08) 0%, #fff 100%); }
        .result-card.alert { border-color: rgba(194, 122, 8, 0.28); background: linear-gradient(180deg, rgba(255, 242, 223, 0.86) 0%, #fff 100%); }
        .result-card .result-title {
            font-size: 1.05rem;
            line-height: 1.35;
            margin-bottom: 8px;
            color: var(--ink);
        }
        .result-card .summary {
            font-size: 0.92rem;
            line-height: 1.62;
            color: #486a7f;
        }
        .result-summary-list {
            margin-top: 10px;
            display: grid;
            gap: 7px;
        }
        .result-summary-item {
            display: grid;
            grid-template-columns: 14px 1fr;
            align-items: start;
            gap: 8px;
            font-size: 0.9rem;
            line-height: 1.5;
            color: #385b70;
        }
        .result-summary-item::before {
            content: "•";
            color: var(--brand-dark);
            font-weight: 800;
            line-height: 1.25;
            margin-top: -1px;
        }
        #resultPills {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 8px;
            margin-top: 14px !important;
        }
        #resultPills .pill {
            width: auto !important;
            white-space: normal;
            text-align: left;
            justify-content: flex-start;
            border-radius: 14px;
            padding: 9px 12px;
            line-height: 1.35;
            min-height: 46px;
            display: flex;
            align-items: center;
            border-color: rgba(146, 184, 198, 0.52);
            font-size: 0.83rem;
            font-weight: 700;
            color: #1b3a4e;
        }
        .program-block {
            margin-top: 14px;
            border-radius: 18px;
            border: 1px solid rgba(23, 184, 208, 0.2);
            background: rgba(255, 255, 255, 0.92);
            padding: 14px;
        }
        .program-block strong {
            display: block;
            margin-bottom: 6px;
            color: var(--ink);
            font-size: 0.95rem;
        }
        .program-block p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
            font-size: 0.88rem;
        }
        .care-grid {
            display: grid;
            gap: 10px;
        }
        .care-item {
            background: #fff;
            border: 1px solid #d8e8ee;
            border-radius: 14px;
            padding: 12px 13px;
            color: var(--muted);
            line-height: 1.55;
            font-size: 0.88rem;
        }
        .upload-area { border: 1.5px dashed #b6d8e2; background: #fbfeff; }
        .upload-item { padding: 12px 14px; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
        .slot-card { padding: 16px; cursor: pointer; transition: 0.18s ease; }
        .slot-card:hover { border-color: rgba(14, 165, 183, 0.35); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(14, 165, 183, 0.10); }
        .status-note {
            background: rgba(255, 246, 227, 0.94);
            border: 1px solid #f2d8a5;
            color: var(--warning);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .toast {
            position: fixed;
            bottom: 18px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            min-width: 260px;
            max-width: calc(100vw - 32px);
            border-radius: 18px;
            padding: 13px 15px;
            background: #132a3a;
            color: #fff;
            box-shadow: 0 16px 40px rgba(19, 42, 58, 0.22);
            opacity: 0;
            pointer-events: none;
            transition: 0.22s ease;
            z-index: 30;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .muted-link { color: var(--brand-dark); font-weight: 700; text-decoration: none; }

        /* ── Booked confirmation card ── */
        .booked-card {
            border-radius: 28px;
            padding: 32px 24px;
            text-align: center;
            background: linear-gradient(160deg, #ecfdf5 0%, #f0fdfa 40%, #ffffff 100%);
            border: 2px solid rgba(16, 185, 129, 0.28);
            box-shadow: 0 18px 48px rgba(5, 150, 105, 0.12);
            animation: cardPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        @keyframes cardPop {
            0% { opacity: 0; transform: scale(0.92) translateY(14px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        .booked-icon {
            width: 72px; height: 72px;
            border-radius: 50%;
            margin: 0 auto 18px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            box-shadow: 0 10px 28px rgba(5, 150, 105, 0.32);
            animation: checkBounce 0.6s 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }
        @keyframes checkBounce {
            0% { opacity: 0; transform: scale(0); }
            100% { opacity: 1; transform: scale(1); }
        }
        .booked-card h3 {
            font-family: 'Manrope', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #065f46;
            margin: 0 0 8px;
            letter-spacing: -0.02em;
        }
        .booked-card .booked-subtitle {
            font-size: 0.92rem;
            color: #047857;
            margin: 0 0 22px;
            line-height: 1.5;
        }
        .booked-details {
            background: rgba(255,255,255,0.82);
            border: 1px solid rgba(16, 185, 129, 0.18);
            border-radius: 20px;
            padding: 18px 20px;
            margin-bottom: 20px;
            text-align: left;
        }
        .booked-details .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(214, 236, 230, 0.72);
            font-size: 0.9rem;
        }
        .booked-details .detail-row:last-child { border-bottom: none; }
        .booked-details .detail-row .detail-label {
            color: var(--muted);
            font-weight: 600;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .booked-details .detail-row .detail-value {
            font-weight: 800;
            color: #065f46;
        }
        .btn-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px 22px;
            border: none;
            border-radius: 18px;
            font: inherit;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            color: #fff;
            background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
            box-shadow: 0 12px 32px rgba(37, 211, 102, 0.28);
            transition: 0.22s ease;
            text-decoration: none;
            margin-bottom: 12px;
        }
        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 38px rgba(37, 211, 102, 0.36);
        }
        .btn-whatsapp svg {
            width: 22px; height: 22px; fill: currentColor;
        }
        .booked-footer-note {
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.55;
            margin-top: 8px;
        }
        .slot-shift-label {
            grid-column: 1 / -1;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--brand-dark);
            padding: 10px 4px 2px;
            border-bottom: 1px solid rgba(14, 165, 183, 0.12);
        }
        .sticky-panel { position: sticky; top: 18px; }
        @media (min-width: 981px) {
            body {
                background:
                    linear-gradient(180deg, #f6fafc 0%, #f1f6fb 46%, #f8fbfd 100%);
            }
            .page-bar {
                background: rgba(255, 255, 255, 0.97);
                border-bottom: 1px solid rgba(222, 233, 239, 0.96);
            }
            .page-bar-inner {
                max-width: 1240px;
                padding: 14px 24px;
                justify-content: space-between;
                gap: 20px;
            }
            .page-nav,
            .page-cta,
            .process-band,
            .faq-section,
            .page-footer {
                display: flex;
            }
            .page-brand { font-size: 0.95rem; }
            .page-caption { display: none; }
            .shell {
                max-width: 1240px;
                padding: 22px 24px 72px;
            }
            .hero {
                grid-template-columns: 1fr;
                gap: 0;
                margin-bottom: 0;
            }
            .hero-main {
                min-height: 525px;
                padding: 64px 58px 52px;
                border-radius: 34px;
                color: var(--ink);
                border: 1px solid rgba(202, 223, 231, 0.86);
                background:
                    linear-gradient(100deg, rgba(255,255,255,0.98) 0%, rgba(255,255,255,0.92) 36%, rgba(241,248,251,0.76) 55%, rgba(188,225,235,0.68) 77%, rgba(28,116,138,0.58) 100%),
                    radial-gradient(circle at 18% 18%, rgba(255,255,255,0.86), transparent 26%),
                    linear-gradient(135deg, #eef5f8 0%, #dfeef3 34%, #c4dee7 100%);
                box-shadow: 0 28px 58px rgba(12, 44, 61, 0.12);
            }
            .hero-main::before {
                inset: 42px 36px 42px auto;
                width: 33%;
                height: auto;
                border-radius: 28px;
                background:
                    linear-gradient(145deg, rgba(8, 48, 61, 0.92) 0%, rgba(13, 80, 98, 0.78) 58%, rgba(21, 173, 199, 0.48) 100%),
                    radial-gradient(circle at 22% 22%, rgba(255,255,255,0.22), transparent 28%),
                    repeating-linear-gradient(180deg, rgba(255,255,255,0.12) 0, rgba(255,255,255,0.12) 1px, transparent 1px, transparent 18px),
                    repeating-linear-gradient(90deg, rgba(255,255,255,0.08) 0, rgba(255,255,255,0.08) 1px, transparent 1px, transparent 24px);
                box-shadow:
                    inset 0 0 0 1px rgba(255,255,255,0.18),
                    0 26px 50px rgba(7, 54, 68, 0.20);
            }
            .hero-main::after {
                inset: 0;
                background:
                    linear-gradient(115deg, rgba(255,255,255,0.80) 0%, rgba(255,255,255,0.18) 40%, transparent 52%),
                    linear-gradient(145deg, transparent 60%, rgba(22, 178, 201, 0.22) 61%, transparent 62%),
                    linear-gradient(145deg, transparent 68%, rgba(22, 178, 201, 0.14) 69%, transparent 70%);
                opacity: 1;
            }
            .hero-main h1 {
                max-width: 8.2ch;
                font-size: clamp(3rem, 5vw, 4.5rem);
                line-height: 0.96;
                margin: 18px 0 18px;
                letter-spacing: -0.05em;
            }
            .hero-main p {
                color: #5d7383;
                max-width: 34rem;
                font-size: 1rem;
            }
            .eyebrow {
                color: var(--brand-dark);
                background: rgba(255, 255, 255, 0.82);
                border: 1px solid rgba(198, 223, 231, 0.92);
            }
            .hero-actions {
                margin-top: 26px;
                align-items: center;
            }
            .hero-main .btn-primary {
                background: #0f1f31;
                color: #fff;
                box-shadow: 0 18px 32px rgba(15, 31, 49, 0.18);
            }
            .hero-main .btn-secondary {
                background: transparent;
                color: var(--ink);
                border: none;
                padding-left: 2px;
                padding-right: 2px;
                box-shadow: none;
            }
            .hero-main .btn-secondary:hover {
                transform: none;
                color: var(--brand-dark);
            }
            .hero-badges,
            .hero-side {
                display: none;
            }
            .stats-band {
                max-width: 900px;
                margin: -30px auto 58px;
                gap: 16px;
                position: relative;
                z-index: 3;
            }
            .stat-tile {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
                min-height: 112px;
                padding: 18px 16px;
            }
            .stat-tile strong {
                margin: 0 0 4px;
            }
            .proof-strip {
                margin-bottom: 66px;
            }
            .section-head {
                justify-content: center;
                text-align: center;
                margin-bottom: 22px;
            }
            .section-head > div {
                max-width: 680px;
            }
            .proof-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 18px;
            }
            .proof-card {
                min-height: 196px;
                padding: 22px 18px;
                border-radius: 24px;
            }
            .process-band {
                margin: 0 -24px 70px;
                padding: 60px 24px 66px;
                background:
                    linear-gradient(180deg, #0a1a29 0%, #081725 100%);
                color: #fff;
                flex-direction: column;
            }
            .process-shell {
                max-width: 1160px;
                margin: 0 auto;
                width: 100%;
            }
            .process-head {
                max-width: 700px;
                margin: 0 auto 28px;
                text-align: center;
            }
            .process-head small {
                display: block;
                margin-bottom: 8px;
                color: rgba(111, 215, 233, 0.82);
                font-size: 0.74rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            .process-head h2 {
                margin: 0 0 10px;
                font-size: 2rem;
                color: #fff;
            }
            .process-head p {
                margin: 0;
                color: rgba(223, 236, 243, 0.74);
                line-height: 1.65;
            }
            .process-cards {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 18px;
            }
            .process-card {
                padding: 24px 22px;
                border-radius: 24px;
                background: rgba(255,255,255,0.05);
                border: 1px solid rgba(255,255,255,0.08);
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
            }
            .process-card span {
                width: 36px;
                height: 36px;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: rgba(30, 190, 214, 0.14);
                color: #8be4f0;
                font-size: 0.82rem;
                font-weight: 800;
                margin-bottom: 16px;
            }
            .process-card strong {
                display: block;
                margin-bottom: 8px;
                font-size: 1rem;
                color: #fff;
            }
            .process-card p {
                margin: 0;
                color: rgba(220, 234, 241, 0.72);
                font-size: 0.92rem;
                line-height: 1.6;
            }
            .assurance-grid {
                display: none;
            }
            .layout {
                grid-template-columns: 1fr;
                margin-bottom: 72px;
            }
            .layout aside.panel {
                display: none;
            }
            .layout > main.panel {
                max-width: 480px;
                margin: 0 auto;
                padding: 28px 28px 30px;
                border-radius: 28px;
                background: rgba(255,255,255,0.96);
                border: 1px solid rgba(216, 230, 236, 0.96);
                box-shadow: 0 26px 60px rgba(8, 52, 66, 0.12);
            }
            .layout > main .panel-head {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 10px;
                margin-bottom: 22px;
            }
            .layout > main .panel-badge {
                align-self: center;
            }
            .faq-section {
                flex-direction: column;
                padding: 0 0 44px;
            }
            .faq-shell {
                max-width: 760px;
                margin: 0 auto;
                width: 100%;
                background: rgba(255,255,255,0.96);
                border: 1px solid rgba(216, 230, 236, 0.96);
                border-radius: 24px;
                overflow: hidden;
                box-shadow: var(--shadow-soft);
            }
            .faq-shell .faq-item {
                border: none;
                border-bottom: 1px solid rgba(227, 237, 242, 0.96);
                box-shadow: none;
                border-radius: 0;
                background: transparent;
                padding: 20px 22px;
            }
            .faq-shell .faq-item:last-child {
                border-bottom: none;
            }
            .page-footer {
                max-width: 1160px;
                margin: 0 auto;
                padding-top: 18px;
                border-top: 1px solid rgba(219, 231, 237, 0.96);
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                color: var(--muted);
                font-size: 0.82rem;
            }
            .footer-links {
                display: flex;
                align-items: center;
                gap: 18px;
                flex-wrap: wrap;
            }
            .footer-links button {
                border: none;
                background: transparent;
                color: inherit;
                font: inherit;
                cursor: pointer;
                padding: 0;
            }
        }
        @media (max-width: 980px) {
            .hero, .layout { grid-template-columns: 1fr; }
            .sticky-panel { position: static; }
            .proof-grid, .assurance-grid, .stats-band { grid-template-columns: 1fr 1fr; }
            .layout main.panel { order: 1; }
            .layout aside.panel { order: 2; }
            h1 { max-width: none; }
            .hero-side { display: flex; }
        }
        @media (max-width: 640px) {
            .page-bar-inner { padding: 10px 12px; }
            .shell { padding: 14px 12px 34px; }
            .hero-main, .hero-side, .panel { border-radius: 24px; padding: 18px; }
            .hero-brand { width: 64px; height: 64px; border-radius: 16px; margin-bottom: 10px; }
            .form-grid, .choice-grid, .flag-grid, .exercise-grid, .slot-grid, .proof-grid, .assurance-grid, .stats-band { grid-template-columns: 1fr; }
            .actions .btn { width: 100%; }
            .hero-actions .btn { width: 100%; }
            .hero { gap: 14px; }
            .hero-main { padding: 22px 18px; }
            .hero-main p { font-size: 0.92rem; }
            .hero-badges { gap: 8px; }
            .pill { width: calc(50% - 4px); text-align: center; justify-content: center; }
            .proof-card { min-height: auto; }
            .panel-head { flex-direction: column; align-items: flex-start; }
            .step-chip { padding: 12px; }
            .stat-tile { padding: 14px 16px; }
        }
    </style>
</head>
<body>
    <div class="page-bar">
        <div class="page-bar-inner">
            <div class="brand-lockup">
                <div class="page-mark"><img class="brand-logo-img" src="uploads/branding/logo-fisio.png" alt="Logo KaminarFisio"></div>
                <div>
                    <div class="page-brand">KaminarFisio</div>
                    <div class="page-caption">Evaluacion guiada y reserva online</div>
                </div>
            </div>
            <nav class="page-nav" aria-label="Navegacion principal de la landing">
                <button class="nav-link" type="button" onclick="scrollToBenefits()">Lo que vas a conseguir</button>
                <button class="nav-link" type="button" onclick="scrollToProcess()">Como funciona</button>
                <button class="nav-link" type="button" onclick="scrollToIntake()">Formulario</button>
                <button class="nav-link" type="button" onclick="scrollToFaq()">Preguntas</button>
            </nav>
            <button class="page-cta" type="button" onclick="scrollToIntake()">Reservar evaluacion</button>
        </div>
    </div>
    <div class="shell">
        <section class="hero">
            <div class="hero-main">
                <div class="hero-brand">
                    <img src="uploads/branding/logo-fisio.png" alt="Logo KaminarFisio">
                </div>
                <div class="eyebrow">
                    <span class="material-icons-outlined" style="font-size:16px;">favorite</span>
                    ingreso guiado kaminarfisio
                </div>
                <h1>Te orientamos primero y luego reservas en la agenda real.</h1>
                <p>
                    Completa una evaluacion breve, cuentanos tu molestia principal y te mostramos un plan inicial orientativo,
                    ejercicios previos seguros y horarios disponibles en el sistema real del equipo. Nuestro personal revisa
                    estos ingresos desde las 8:00 AM y se pondra en contacto contigo en horario de trabajo para coordinar
                    o resolver cualquier duda.
                </p>
                <div class="hero-actions">
                    <button class="btn btn-primary" type="button" onclick="scrollToIntake()">Hacer evaluacion ahora</button>
                    <button class="btn btn-secondary" type="button" onclick="scrollToBenefits()">Ver como funciona</button>
                </div>
                <div class="hero-badges">
                    <div class="pill">1 minuto</div>
                    <div class="pill">Agenda conectada al sistema</div>
                    <div class="pill">Fotos opcionales</div>
                    <div class="pill">Orientacion inicial + confianza</div>
                </div>
            </div>
            <aside class="hero-side">
                <div>
                    <h2>Lo que vas a conseguir aqui</h2>
                    <div class="mini-list">
                        <div class="mini-item">
                            <div class="mini-icon"><span class="material-icons-outlined">assignment</span></div>
                            <div><strong>Triage rapido</strong><span>Dolor, tiempo de evolucion, limitacion y alertas importantes.</span></div>
                        </div>
                        <div class="mini-item">
                            <div class="mini-icon"><span class="material-icons-outlined">event_available</span></div>
                            <div><strong>Horarios reales</strong><span>Los espacios que ves son los mismos que ve admin, recepcion y terapia.</span></div>
                        </div>
                        <div class="mini-item">
                            <div class="mini-icon"><span class="material-icons-outlined">fitness_center</span></div>
                            <div><strong>Primeros cuidados</strong><span>Te dejamos ejercicios previos seguros mientras llega tu evaluacion.</span></div>
                        </div>
                    </div>
                </div>
                <div class="status-note">
                    Esta orientacion no reemplaza una evaluacion medica formal. Si detectamos alertas, te sugeriremos traumatologia
                    antes o junto con fisioterapia. El equipo revisa nuevos ingresos desde las 8:00 AM y te contactara en
                    horario de trabajo para ayudarte a coordinar.
                </div>
                <div class="quote-box">
                    <p>
                        Aqui no te dejamos en visto. Primero entendemos tu caso, luego te orientamos y finalmente te mostramos horarios reales para que avances rapido. Si dejas tu ingreso fuera del horario de atencion, nuestro personal lo retomara desde las 8:00 AM.
                    </p>
                    <small>Experiencia pensada para captar y convertir</small>
                </div>
            </aside>
        </section>

        <section class="stats-band" aria-label="Puntos clave del ingreso">
            <article class="stat-tile">
                <span class="material-icons-outlined">schedule</span>
                <div>
                    <strong>Respuesta clara desde el inicio</strong>
                    <span>En 1 minuto el paciente entiende el siguiente paso.</span>
                </div>
            </article>
            <article class="stat-tile">
                <span class="material-icons-outlined">calendar_month</span>
                <div>
                    <strong>Agenda real conectada</strong>
                    <span>Los horarios disponibles salen del sistema del centro.</span>
                </div>
            </article>
            <article class="stat-tile">
                <span class="material-icons-outlined">support_agent</span>
                <div>
                    <strong>Seguimiento humano</strong>
                    <span>El equipo revisa ingresos desde las 8:00 AM en horario de trabajo.</span>
                </div>
            </article>
            <article class="stat-tile">
                <span class="material-icons-outlined">add_a_photo</span>
                <div>
                    <strong>Fotos y contexto previo</strong>
                    <span>Se puede llegar a la cita con mejor informacion y confianza.</span>
                </div>
            </article>
        </section>

        <section class="proof-strip" id="benefitsSection">
            <div class="section-head">
                <div>
                    <small>Lo que vas a conseguir aqui</small>
                    <h2 style="margin:0 0 4px;">Un primer paso claro, rapido y con confianza.</h2>
                    <p>La idea es que el paciente sienta avance desde el primer clic y tenga menos friccion para reservar.</p>
                </div>
            </div>
            <div class="proof-grid">
                <article class="proof-card">
                    <div class="proof-card-top"><span class="material-icons-outlined">psychology</span></div>
                    <div>
                        <strong>Orientacion inicial sin perder tiempo</strong>
                        <p>El paciente entiende rapido si parece un caso para fisioterapia, para traumatologia o para ambas.</p>
                    </div>
                </article>
                <article class="proof-card">
                    <div class="proof-card-top"><span class="material-icons-outlined">calendar_month</span></div>
                    <div>
                        <strong>Agenda real, no promesas vacias</strong>
                        <p>Los horarios visibles son los mismos que ve tu equipo interno, asi reduces vueltas por WhatsApp.</p>
                    </div>
                </article>
                <article class="proof-card">
                    <div class="proof-card-top"><span class="material-icons-outlined">photo_camera</span></div>
                    <div>
                        <strong>Fotos y contexto antes de llegar</strong>
                        <p>Pueden subir imagenes de la zona afectada o examenes previos para que lleguen con mas confianza.</p>
                    </div>
                </article>
                <article class="proof-card">
                    <div class="proof-card-top"><span class="material-icons-outlined">exercise</span></div>
                    <div>
                        <strong>Primer valor antes de la cita</strong>
                        <p>Reciben ejercicios previos seguros y una sensacion clara de acompanamiento desde el minuto uno.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="process-band" id="processSection">
            <div class="process-shell">
                <div class="process-head">
                    <small>Que pasa despues de llenar esto</small>
                    <h2>Que pasa despues de llenar esto</h2>
                    <p>No es un formulario frio. Esta pensado para que el paciente sienta avance real, entienda su siguiente paso y llegue a la reserva con mucha menos friccion.</p>
                </div>
                <div class="process-cards">
                    <article class="process-card">
                        <span>01</span>
                        <strong>Evaluacion inicial</strong>
                        <p>Nos cuenta la molestia principal, el tiempo de evolucion, la limitacion y las alertas clinicas que deberiamos mirar primero.</p>
                    </article>
                    <article class="process-card">
                        <span>02</span>
                        <strong>Plan sugerido</strong>
                        <p>Mostramos un camino orientativo: fisioterapia inicial, derivacion a traumatologia o evaluacion combinada segun el caso.</p>
                    </article>
                    <article class="process-card">
                        <span>03</span>
                        <strong>Agendamiento real</strong>
                        <p>Si decide continuar, elige su horario y la cita queda registrada tambien para admin, recepcion y terapia dentro del sistema.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="assurance-grid">
            <article class="assurance-card">
                <h2>Que pasa despues de llenar esto</h2>
                <p>No es un formulario frio. Esta pensado para que el paciente sienta avance real y decida reservar con menos friccion.</p>
                <div class="process-list">
                    <div class="process-item">
                        <span>1</span>
                        <div>
                            <strong>Nos cuenta su molestia principal</strong>
                            <p>Dolor, tiempo de evolucion, limitacion y si hubo trauma o alguna alerta importante.</p>
                        </div>
                    </div>
                    <div class="process-item">
                        <span>2</span>
                        <div>
                            <strong>Recibe una recomendacion orientativa</strong>
                            <p>Mostramos un posible camino: fisioterapia inicial, derivacion a trauma o evaluacion combinada.</p>
                        </div>
                    </div>
                    <div class="process-item">
                        <span>3</span>
                        <div>
                            <strong>Reserva sin esperar respuesta manual</strong>
                            <p>Si decide continuar, agenda de inmediato y la cita aparece tambien para admin, recepcion y terapeuta.</p>
                        </div>
                    </div>
                </div>
            </article>
            <article class="assurance-card">
                <h2>Preguntas que generan confianza</h2>
                <div class="faq-list">
                    <div class="faq-item">
                        <strong>Esto me compromete a pagar ahora?</strong>
                        <p>No. Primero entiende tu caso y luego decides si reservas.</p>
                    </div>
                    <div class="faq-item">
                        <strong>Y si creo que necesito traumatologo?</strong>
                        <p>Tambien puedes marcarlo y el sistema lo toma en cuenta al orientarte.</p>
                    </div>
                    <div class="faq-item">
                        <strong>Tengo que subir fotos obligatoriamente?</strong>
                        <p>No, son opcionales. Solo ayudan a que el equipo llegue con mejor contexto.</p>
                    </div>
                    <div class="faq-item">
                        <strong>Cuando me responderan si tengo dudas?</strong>
                        <p>Nuestro personal revisa ingresos desde las 8:00 AM y te contactara en horario de trabajo para coordinar o ayudarte.</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="layout">
            <aside class="panel sticky-panel">
                <div class="panel-head">
                    <div>
                        <h2>Tu progreso</h2>
                        <p>Vas avanzando paso a paso. Si cierras la pagina, retomamos donde quedaste.</p>
                    </div>
                    <div class="panel-badge" id="resumeBadge">Paso 1 de 4</div>
                </div>

                <div class="progress-bar">
                    <div class="progress-bar-fill" id="progressFill" style="width: 25%;"></div>
                </div>
                <div class="stepper" id="stepper">
                    <div class="step-chip active" data-step-chip="1">
                        <div class="step-number">1</div>
                        <div>
                            <div class="step-title">Datos basicos</div>
                            <div class="step-copy">Nombre, telefono y datos para seguir contigo.</div>
                        </div>
                    </div>
                    <div class="step-chip" data-step-chip="2">
                        <div class="step-number">2</div>
                        <div>
                            <div class="step-title">Encuesta rapida</div>
                            <div class="step-copy">Que sientes, desde cuando y que te limita.</div>
                        </div>
                    </div>
                    <div class="step-chip" data-step-chip="3">
                        <div class="step-number">3</div>
                        <div>
                            <div class="step-title">Plan sugerido</div>
                            <div class="step-copy">Orientacion inicial, ejercicios y fotos opcionales.</div>
                        </div>
                    </div>
                    <div class="step-chip" data-step-chip="4">
                        <div class="step-number">4</div>
                        <div>
                            <div class="step-title">Agenda real</div>
                            <div class="step-copy">Reserva en la misma agenda que usa el equipo.</div>
                        </div>
                    </div>
                </div>

                <div class="trust-card">
                    <strong style="display:block;margin-bottom:8px;">Confianza desde el primer contacto</strong>
                    <div class="help">
                        Tu informacion queda registrada para que el equipo llegue a la evaluacion con mejor contexto.
                        Si agendas, esa cita entra directo al sistema interno. Ademas, el personal revisa ingresos desde las
                        8:00 AM y se pondra en contacto en horario de trabajo para cualquier coordinacion adicional.
                    </div>
                </div>
            </aside>

            <main class="panel" id="intakePanel">
                <div class="panel-head">
                    <div>
                        <h2 id="panelTitle">Empecemos con tus datos</h2>
                        <p id="panelCopy">Solo necesitamos la base para orientarte y mostrarte horarios reales.</p>
                    </div>
                    <div class="panel-badge" id="statusBadge">Sin enviar</div>
                </div>

                <section class="step-view active" id="step1">
                    <div class="form-grid">
                        <div class="field">
                            <label for="lead_name">Nombre completo</label>
                            <input id="lead_name" type="text" placeholder="Ej: Maria Perez">
                        </div>
                        <div class="field">
                            <label for="lead_phone">Telefono WhatsApp</label>
                            <input id="lead_phone" type="tel" placeholder="999888777" inputmode="numeric" maxlength="9">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="field">
                            <label for="lead_dni">DNI opcional</label>
                            <input id="lead_dni" type="tel" placeholder="12345678" inputmode="numeric" maxlength="8">
                        </div>
                        <div class="field">
                            <label for="lead_email">Correo opcional</label>
                            <input id="lead_email" type="email" placeholder="nombre@email.com">
                        </div>
                    </div>
                    <div class="help">
                        Al continuar aceptas que usemos estos datos para orientarte, contactarte y preparar tu atencion.
                    </div>
                    <div class="actions">
                        <button class="btn btn-primary" type="button" onclick="goToSymptoms()">Continuar con la encuesta</button>
                    </div>
                </section>

                <section class="step-view" id="step2">
                    <div class="field">
                        <label for="pain_area">Zona principal de dolor o molestia</label>
                        <input id="pain_area" type="text" placeholder="Ej: hombro derecho, lumbar, tendon de aquiles">
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="pain_since">Desde cuando te pasa</label>
                            <select id="pain_since">
                                <option value="">Selecciona una opcion</option>
                                <option value="Menos de 72 horas">Menos de 72 horas</option>
                                <option value="1 a 2 semanas">1 a 2 semanas</option>
                                <option value="3 a 6 semanas">3 a 6 semanas</option>
                                <option value="Mas de 6 semanas">Mas de 6 semanas</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="goal">Tu objetivo principal</label>
                            <input id="goal" type="text" placeholder="Ej: caminar mejor, volver al gym, dormir sin dolor">
                        </div>
                    </div>

                    <div class="field">
                        <label for="main_limitation">Que es lo que mas te limita hoy</label>
                        <textarea id="main_limitation" placeholder="Ej: no puedo apoyar, no puedo levantar el brazo, me duele al sentarme"></textarea>
                    </div>

                    <div class="field">
                        <label>Intensidad del dolor (EVA 0-10)</label>
                        <div class="score-wrap">
                            <input id="pain_score" type="range" min="0" max="10" value="5" oninput="updateScoreValue()">
                            <div class="score-value" id="painScoreValue">5</div>
                        </div>
                    </div>

                    <div class="field">
                        <label>Esto empezo por un golpe, caida o torcedura?</label>
                        <div class="choice-grid">
                            <button type="button" class="choice-btn active" data-group="trauma_event" data-value="0" onclick="selectChoice(this)">
                                <strong>No necesariamente</strong>
                                <span>Parece venir de carga, postura o repeticion.</span>
                            </button>
                            <button type="button" class="choice-btn" data-group="trauma_event" data-value="1" onclick="selectChoice(this)">
                                <strong>Si, hubo trauma</strong>
                                <span>Golpe, caida, torcedura o tiron fuerte.</span>
                            </button>
                        </div>
                    </div>

                    <div class="field">
                        <label>Alertas que te identifican</label>
                        <div class="flag-grid">
                            <label class="flag-option"><input type="checkbox" value="Perdida de fuerza importante" class="red-flag"><span>Perdida de fuerza importante</span></label>
                            <label class="flag-option"><input type="checkbox" value="Hormigueo persistente" class="red-flag"><span>Hormigueo persistente</span></label>
                            <label class="flag-option"><input type="checkbox" value="Dolor nocturno muy intenso" class="red-flag"><span>Dolor nocturno muy intenso</span></label>
                            <label class="flag-option"><input type="checkbox" value="Inflamacion o deformidad importante" class="red-flag"><span>Inflamacion o deformidad importante</span></label>
                        </div>
                    </div>

                    <div class="field">
                        <label class="flag-option" style="padding: 14px 16px;">
                            <input type="checkbox" id="wants_trauma_eval">
                            <span>Tambien quiero que revisen si conviene traumatologia.</span>
                        </label>
                    </div>

                    <div class="actions">
                        <button class="btn btn-secondary" type="button" onclick="goToStep(1)">Volver</button>
                        <button class="btn btn-primary" type="button" onclick="submitIntake()">Ver mi orientacion</button>
                    </div>
                </section>

                <section class="step-view" id="step3">
                    <div class="result-shell">
                        <div class="result-card" id="recommendationCard">
                            <div class="result-title" id="resultHeadline">Cargando orientacion...</div>
                            <div class="summary" id="resultSummary"></div>
                            <div class="result-summary-list" id="resultSummaryList"></div>
                            <div class="hero-badges" id="resultPills" style="margin-top: 14px;"></div>
                            <div class="program-block">
                                <strong id="resultProgramName">Programa de Rehabilitacion KaminarFisio</strong>
                                <p id="resultProgramSummary">Objetivo: bajar dolor, recuperar movilidad y volver a tus actividades con seguridad.</p>
                            </div>
                        </div>

                        <div>
                            <h2 style="margin-bottom:10px;">Ejercicios previos sugeridos</h2>
                            <div class="exercise-grid" id="exerciseGrid"></div>
                        </div>

                        <div>
                            <h2 style="margin-bottom:10px;">Cuidados iniciales seguros</h2>
                            <div class="care-grid" id="homeCareGrid"></div>
                        </div>

                        <div class="upload-area">
                            <h2 style="margin-bottom:8px;">Fotos opcionales para llegar con mejor contexto</h2>
                            <div class="help">Si quieres, sube una o dos fotos de la zona o examen previo. El equipo las vera dentro del sistema.</div>
                            <div class="actions" style="margin-top: 14px;">
                                <input type="file" id="photoInput" accept="image/png,image/jpeg,image/webp">
                                <button class="btn btn-soft" type="button" onclick="uploadPhoto()">Subir foto</button>
                            </div>
                            <div class="upload-list" id="uploadList"></div>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn btn-secondary" type="button" onclick="goToStep(2)">Volver</button>
                        <button class="btn btn-primary" type="button" onclick="goToAgenda()">Ver horarios reales</button>
                    </div>
                </section>

                <section class="step-view" id="step4">
                    <div id="agendaSelector">
                        <div class="field">
                            <label for="agenda_date">Elige la fecha</label>
                            <input type="date" id="agenda_date" onchange="loadAvailability()">
                        </div>

                        <div class="help">
                            Estos horarios vienen de la misma agenda que usa el equipo internamente. Si reservas aqui, admin, recepcion y terapia lo veran al instante.
                        </div>

                        <div id="slotsEmpty" class="status-note" style="display:none; margin-top:14px;">
                            No encontramos cupos para esa fecha. Prueba otro dia y te mostraremos el primer disponible.
                        </div>
                        <div class="slot-grid" id="slotGrid"></div>

                        <div class="actions">
                            <button class="btn btn-secondary" type="button" onclick="goToStep(3)">Volver</button>
                            <button class="btn btn-primary" type="button" onclick="confirmBooking()">Reservar mi evaluacion</button>
                        </div>
                    </div>

                    <div id="bookingResult" style="display:none;"></div>
                </section>
            </main>
        </section>

        <section class="faq-section" id="faqSection">
            <div class="section-head">
                <div>
                    <small>Preguntas que generan confianza</small>
                    <h2 style="margin:0 0 4px;">Preguntas que generan confianza</h2>
                    <p>Respondemos lo que mas suele frenar a una persona antes de reservar su evaluacion.</p>
                </div>
            </div>
            <div class="faq-shell">
                <div class="faq-item">
                    <strong>Esto me compromete a pagar ahora?</strong>
                    <p>No. Primero entiendes tu caso y luego decides si quieres reservar.</p>
                </div>
                <div class="faq-item">
                    <strong>Y si necesito traumatologia tambien?</strong>
                    <p>Puedes marcarlo en la encuesta y el sistema lo toma en cuenta para la orientacion inicial.</p>
                </div>
                <div class="faq-item">
                    <strong>Puedo subir fotos o examenes previos?</strong>
                    <p>Si. Es opcional, pero ayuda bastante a que el equipo llegue con mejor contexto.</p>
                </div>
                <div class="faq-item">
                    <strong>Cuando me responderan si tengo preguntas?</strong>
                    <p>El personal revisa ingresos desde las 8:00 AM y se pondra en contacto contigo en horario de trabajo para coordinar o resolver dudas.</p>
                </div>
            </div>
        </section>

        <footer class="page-footer">
            <div>
                <strong style="font-family:'Manrope',sans-serif;color:var(--ink);">KaminarFisio</strong>
                <div style="margin-top:4px;">Evaluacion guiada, orientacion inicial y reserva online.</div>
            </div>
            <div class="footer-links">
                <button type="button" onclick="scrollToBenefits()">Beneficios</button>
                <button type="button" onclick="scrollToProcess()">Proceso</button>
                <button type="button" onclick="scrollToIntake()">Formulario</button>
                <button type="button" onclick="scrollToFaq()">Preguntas</button>
            </div>
        </footer>
    </div>

    <div class="toast" id="toast"></div>
    <script>
        const state = {
            intake: null,
            therapists: [],
            availableSlots: [],
            selectedSlot: null,
            today: '',
            maxDate: '',
            currentStep: 1
        };

        const panelMeta = {
            1: { title: 'Empecemos con tus datos', copy: 'Solo necesitamos la base para orientarte y mostrarte horarios reales.', badge: 'Sin enviar' },
            2: { title: 'Cuentanos lo mas importante', copy: 'Con estas respuestas podemos orientarte mejor y detectar alertas.', badge: 'Encuesta en curso' },
            3: { title: 'Tu orientacion inicial', copy: 'Te mostramos el camino sugerido y algunos primeros cuidados.', badge: 'Plan orientativo' },
            4: { title: 'Reserva en agenda real', copy: 'Escoge el horario que mejor te calce. Se registra directo en el sistema.', badge: 'Agenda activa' }
        };

        const params = new URLSearchParams(window.location.search);
        const initialCode = params.get('code') || localStorage.getItem('publicIntakeCode') || '';

        function escapeHtml(value) {
            return String(value || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.background = isError ? '#7f1d1d' : '#132a3a';
            toast.classList.add('show');
            clearTimeout(showToast._timer);
            showToast._timer = setTimeout(() => toast.classList.remove('show'), 2600);
        }

        function getStickyOffset() {
            const bar = document.querySelector('.page-bar');
            return (bar ? bar.offsetHeight : 0) + 14;
        }

        function scrollNodeToTop(node, smooth = true) {
            if (!node) return;
            const top = window.scrollY + node.getBoundingClientRect().top - getStickyOffset();
            window.scrollTo({
                top: Math.max(0, top),
                behavior: smooth ? 'smooth' : 'auto'
            });
        }

        function focusIntakePanel(smooth = true) {
            const target = document.getElementById('intakePanel') || document.getElementById('step' + state.currentStep);
            if (!target) return;

            requestAnimationFrame(() => {
                requestAnimationFrame(() => scrollNodeToTop(target, smooth));
            });
        }

        function scrollToIntake() {
            goToStep(1);
        }

        function scrollToBenefits() {
            scrollNodeToTop(document.getElementById('benefitsSection'));
        }

        function scrollToProcess() {
            scrollNodeToTop(document.getElementById('processSection'));
        }

        function scrollToFaq() {
            scrollNodeToTop(document.getElementById('faqSection'));
        }

        function updateScoreValue() {
            document.getElementById('painScoreValue').textContent = document.getElementById('pain_score').value;
        }

        function setPanelMeta(step) {
            const meta = panelMeta[step];
            document.getElementById('panelTitle').textContent = meta.title;
            document.getElementById('panelCopy').textContent = meta.copy;
            document.getElementById('statusBadge').textContent = meta.badge;
            document.getElementById('resumeBadge').textContent = 'Paso ' + step + ' de 4';
        }

        function updateStepper(step) {
            document.querySelectorAll('[data-step-chip]').forEach((chip) => {
                const chipStep = Number(chip.dataset.stepChip);
                chip.classList.toggle('active', chipStep === step);
                chip.classList.toggle('done', chipStep < step);
            });
            const progressFill = document.getElementById('progressFill');
            if (progressFill) {
                progressFill.style.width = (step * 25) + '%';
            }
        }

        function goToStep(step, options = {}) {
            state.currentStep = step;
            document.querySelectorAll('.step-view').forEach((section) => {
                section.classList.toggle('active', section.id === 'step' + step);
            });
            updateStepper(step);
            setPanelMeta(step);
            if (options.scroll !== false) {
                focusIntakePanel(options.smooth !== false);
            }
        }

        function selectChoice(button) {
            const group = button.dataset.group;
            document.querySelectorAll('.choice-btn[data-group="' + group + '"]').forEach((node) => {
                node.classList.toggle('active', node === button);
            });
        }

        function getSelectedChoice(group) {
            const active = document.querySelector('.choice-btn[data-group="' + group + '"].active');
            return active ? active.dataset.value : '';
        }

        function collectContactData() {
            return {
                full_name: document.getElementById('lead_name').value.trim(),
                phone: document.getElementById('lead_phone').value.trim(),
                dni: document.getElementById('lead_dni').value.trim(),
                email: document.getElementById('lead_email').value.trim()
            };
        }

        function collectAnswers() {
            return {
                pain_area: document.getElementById('pain_area').value.trim(),
                pain_since: document.getElementById('pain_since').value,
                pain_score: Number(document.getElementById('pain_score').value || 0),
                main_limitation: document.getElementById('main_limitation').value.trim(),
                trauma_event: getSelectedChoice('trauma_event') === '1',
                red_flags: [...document.querySelectorAll('.red-flag:checked')].map((node) => node.value),
                goal: document.getElementById('goal').value.trim(),
                wants_trauma_eval: document.getElementById('wants_trauma_eval').checked
            };
        }

        function validateContactData() {
            const contact = collectContactData();
            if (!contact.full_name) {
                showToast('Escribe tu nombre completo.', true);
                return null;
            }
            if (!/^\d{9}$/.test(contact.phone)) {
                showToast('Escribe un telefono de 9 digitos.', true);
                return null;
            }
            if (contact.dni && !/^\d{8}$/.test(contact.dni)) {
                showToast('El DNI debe tener 8 digitos.', true);
                return null;
            }
            return contact;
        }

        function fillContactFields() {
            if (!state.intake) return;
            document.getElementById('lead_name').value = state.intake.full_name || '';
            document.getElementById('lead_phone').value = state.intake.phone || '';
            document.getElementById('lead_dni').value = state.intake.dni || '';
            document.getElementById('lead_email').value = state.intake.email || '';
        }

        function fillAnswerFields() {
            if (!state.intake?.answers) return;
            const answers = state.intake.answers;
            document.getElementById('pain_area').value = answers.pain_area || '';
            document.getElementById('pain_since').value = answers.pain_since || '';
            document.getElementById('pain_score').value = answers.pain_score ?? 5;
            document.getElementById('main_limitation').value = answers.main_limitation || '';
            document.getElementById('goal').value = answers.goal || '';
            document.getElementById('wants_trauma_eval').checked = !!answers.wants_trauma_eval;
            document.querySelectorAll('.choice-btn[data-group="trauma_event"]').forEach((btn) => {
                btn.classList.toggle('active', String(answers.trauma_event ? 1 : 0) === btn.dataset.value);
            });
            const selectedFlags = new Set(Array.isArray(answers.red_flags) ? answers.red_flags : []);
            document.querySelectorAll('.red-flag').forEach((checkbox) => {
                checkbox.checked = selectedFlags.has(checkbox.value);
            });
            updateScoreValue();
        }

        function renderUploads() {
            const photos = Array.isArray(state.intake?.photos) ? state.intake.photos : [];
            document.getElementById('uploadList').innerHTML = photos.map((photo) => `
                <div class="upload-item">
                    <div>
                        <strong style="display:block;font-size:0.9rem;">${escapeHtml(photo.original_name || 'Foto enviada')}</strong>
                        <span style="font-size:0.82rem;color:var(--muted);">${escapeHtml(photo.caption || 'Sin descripcion')}</span>
                    </div>
                    <a class="muted-link" href="${escapeHtml(photo.file_path)}" target="_blank" rel="noopener">Ver</a>
                </div>
            `).join('');
        }

        function renderRecommendation() {
            const result = state.intake?.result || {};
            const exercises = Array.isArray(result.pre_exercises) ? result.pre_exercises : [];
            const homeCareRaw = Array.isArray(result.home_recommendations) ? result.home_recommendations : [];
            const homeCare = homeCareRaw.length > 0 ? homeCareRaw : [
                'Aplicar frio local 10-15 minutos con tela de proteccion, 2 a 3 veces al dia durante 48-72 horas.',
                'Reposo relativo: evita sobrecargar la zona, pero mantente en movimiento suave.',
                'Evita ejercicios repetitivos o de alto impacto hasta la evaluacion.',
                'Si el dolor aumenta o aparece perdida de fuerza marcada, acude a evaluacion medica.'
            ];
            const needsTrauma = !!result.needs_trauma_eval;
            const card = document.getElementById('recommendationCard');
            card.classList.toggle('alert', needsTrauma);
            document.getElementById('resultHeadline').textContent = result.headline || 'Orientacion lista';
            const rawSummary = String(result.summary || '').trim();
            const summaryLines = rawSummary
                .split('.')
                .map((part) => part.trim())
                .filter(Boolean);
            document.getElementById('resultSummary').textContent = summaryLines.length > 0
                ? 'Resumen del caso reportado:'
                : '';
            document.getElementById('resultSummaryList').innerHTML = summaryLines.map((line) => `
                <div class="result-summary-item">${escapeHtml(line)}.</div>
            `).join('');
            document.getElementById('resultProgramName').textContent = result.program_name || 'Programa de Rehabilitacion KaminarFisio';
            document.getElementById('resultProgramSummary').textContent = result.program_summary || 'Objetivo: bajar dolor, recuperar movilidad y volver a tus actividades con seguridad.';
            const pills = [
                result.confidence_label || 'Plan orientativo',
                result.program_name || 'Programa KaminarFisio',
                'Plan sugerido: ' + (result.recommended_plan_name || 'Evaluacion inicial personalizada'),
                'Sugerencia: ' + (result.suggested_sessions || 0) + ' sesiones'
            ];
            if (needsTrauma) pills.push('Conviene evaluar traumatologia');
            document.getElementById('resultPills').innerHTML = pills.filter(Boolean).map((text) => `<div class="pill">${escapeHtml(text)}</div>`).join('');
            document.getElementById('exerciseGrid').innerHTML = exercises.map((exercise) => `
                <div class="exercise-item">
                    <strong>${escapeHtml(exercise.title)}</strong>
                    <span>${escapeHtml(exercise.detail)}</span>
                </div>
            `).join('');
            document.getElementById('homeCareGrid').innerHTML = homeCare.map((tip) => `
                <div class="care-item">${escapeHtml(tip)}</div>
            `).join('');
            renderUploads();
        }

        function buildWhatsAppUrl(date, time, therapistName, patientName) {
            const msg = `Hola! He agendado mi cita de evaluacion en KaminarFisio.\n\nFecha: ${date}\nHora: ${time}\nProfesional: ${therapistName}\nPaciente: ${patientName}\n\nQuedo atento/a a cualquier indicacion. Gracias!`;
            return 'https://wa.me/51921553520?text=' + encodeURIComponent(msg);
        }

        function renderBookedCard(date, time, therapistName, patientName, type) {
            const waUrl = buildWhatsAppUrl(date, time, therapistName, patientName);
            return `
                <div class="booked-card">
                    <div class="booked-icon">
                        <span class="material-icons-outlined" style="font-size:36px;">check_circle</span>
                    </div>
                    <h3>Tu evaluacion esta reservada</h3>
                    <p class="booked-subtitle">La cita ya aparece en el sistema del centro. El equipo la vera al instante.</p>
                    <div class="booked-details">
                        <div class="detail-row">
                            <span class="detail-label">Fecha</span>
                            <span class="detail-value">${escapeHtml(date)}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Hora</span>
                            <span class="detail-value">${escapeHtml(time)}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Profesional</span>
                            <span class="detail-value">${escapeHtml(therapistName)}</span>
                        </div>
                        ${type ? `<div class="detail-row"><span class="detail-label">Tipo</span><span class="detail-value">${escapeHtml(type)}</span></div>` : ''}
                        <div class="detail-row">
                            <span class="detail-label">Paciente</span>
                            <span class="detail-value">${escapeHtml(patientName)}</span>
                        </div>
                    </div>
                    <a class="btn-whatsapp" href="${waUrl}" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Confirmar por WhatsApp
                    </a>
                    <p class="booked-footer-note">
                        Nuestro equipo revisa ingresos desde las 8:00 AM y te contactara en horario de trabajo para coordinar cualquier detalle.
                    </p>
                </div>
            `;
        }

        function renderExistingBooking() {
            if (!state.intake || state.intake.status !== 'booked') return;
            const therapist = state.therapists.find((item) => Number(item.id) === Number(state.intake.therapist_id));
            const resultBox = document.getElementById('bookingResult');
            const agendaSelector = document.getElementById('agendaSelector');
            const patientName = state.intake.full_name || 'Paciente';
            const therapistName = therapist?.name || 'Pendiente de asignar';
            const date = state.intake.booked_slot_date || '-';
            const time = state.intake.booked_slot_time || '-';

            if (agendaSelector) agendaSelector.style.display = 'none';
            resultBox.style.display = 'block';
            resultBox.innerHTML = renderBookedCard(date, time, therapistName, patientName, '');
        }

        async function bootstrap() {
            const response = await fetch('api/public_intake.php?action=bootstrap' + (initialCode ? '&code=' + encodeURIComponent(initialCode) : ''));
            const json = await response.json();
            if (!json.success) {
                showToast(json.error || 'No se pudo iniciar el flujo.', true);
                return;
            }

            state.today = json.today;
            state.maxDate = json.max_date;
            state.therapists = Array.isArray(json.therapists) ? json.therapists : [];
            state.intake = json.intake || null;

            const agendaDate = document.getElementById('agenda_date');
            agendaDate.min = state.today;
            agendaDate.max = state.maxDate;
            agendaDate.value = state.today;

            if (state.intake?.public_code) {
                localStorage.setItem('publicIntakeCode', state.intake.public_code);
                if (!params.get('code')) {
                    history.replaceState({}, '', 'ingreso.php?code=' + encodeURIComponent(state.intake.public_code));
                }
                fillContactFields();
                fillAnswerFields();
                if (state.intake.result && Object.keys(state.intake.result).length > 0) {
                    renderRecommendation();
                }
                const step = state.intake.status === 'booked' ? 4 : Math.min(4, Math.max(1, Number(state.intake.current_step || 1)));
                goToStep(step, { scroll: false, smooth: false });
                if (step === 4) {
                    await loadAvailability();
                    renderExistingBooking();
                }
            } else {
                goToStep(1, { scroll: false, smooth: false });
            }
        }

        function goToSymptoms() {
            const contact = validateContactData();
            if (!contact) return;
            goToStep(2);
        }

        async function submitIntake() {
            const contact = validateContactData();
            if (!contact) return;

            const answers = collectAnswers();
            if (!answers.pain_area || !answers.pain_since || !answers.main_limitation || !answers.goal) {
                showToast('Completa la zona, el tiempo, la limitacion y tu objetivo principal.', true);
                return;
            }

            const response = await fetch('api/public_intake.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'save_intake',
                    public_code: state.intake?.public_code || '',
                    current_step: 3,
                    source_channel: 'whatsapp_link',
                    ...contact,
                    answers
                })
            });
            const json = await response.json();
            if (!json.success) {
                showToast(json.error || 'No pudimos guardar tu orientacion.', true);
                return;
            }

            state.intake = json.intake;
            localStorage.setItem('publicIntakeCode', state.intake.public_code);
            history.replaceState({}, '', 'ingreso.php?code=' + encodeURIComponent(state.intake.public_code));
            renderRecommendation();
            goToStep(3);
            showToast('Tu orientacion inicial ya esta lista.');
        }

        async function uploadPhoto() {
            const fileInput = document.getElementById('photoInput');
            const file = fileInput.files[0];
            if (!state.intake?.public_code) {
                showToast('Primero completa la orientacion para guardar las fotos.', true);
                return;
            }
            if (!file) {
                showToast('Selecciona una imagen para subir.', true);
                return;
            }

            const formData = new FormData();
            formData.append('action', 'upload_photo');
            formData.append('public_code', state.intake.public_code);
            formData.append('photo', file);
            formData.append('caption', 'Foto previa enviada por paciente');

            const response = await fetch('api/public_intake.php', { method: 'POST', body: formData });
            const json = await response.json();
            if (!json.success) {
                showToast(json.error || 'No pudimos subir la foto.', true);
                return;
            }

            state.intake = json.intake;
            fileInput.value = '';
            renderUploads();
            showToast('Foto subida correctamente.');
        }

        async function goToAgenda() {
            if (!state.intake?.public_code) {
                showToast('Completa primero la orientacion.', true);
                return;
            }
            goToStep(4);
            await loadAvailability();
        }

        async function loadAvailability() {
            const date = document.getElementById('agenda_date').value;
            if (!date) return;
            state.selectedSlot = null;
            document.getElementById('bookingResult').style.display = 'none';

            const response = await fetch('api/public_intake.php?action=availability&date=' + encodeURIComponent(date));
            const json = await response.json();
            if (!json.success) {
                showToast(json.error || 'No pudimos cargar la agenda.', true);
                return;
            }

            state.availableSlots = Array.isArray(json.slots) ? json.slots : [];
            const grid = document.getElementById('slotGrid');
            const empty = document.getElementById('slotsEmpty');

            if (state.availableSlots.length === 0) {
                grid.innerHTML = '';
                empty.style.display = 'block';
                return;
            }

            empty.style.display = 'none';
            let html = '';
            let lastShift = '';
            state.availableSlots.forEach((slot, index) => {
                const hour = parseInt(slot.start_time.split(':')[0], 10);
                const shift = hour < 14 ? 'morning' : 'afternoon';
                if (shift !== lastShift) {
                    html += `<div class="slot-shift-label">${shift === 'morning' ? '☀️ Turno Mañana (8:00 AM – 1:00 PM)' : '🌤️ Turno Tarde (2:30 PM – 7:30 PM)'}</div>`;
                    lastShift = shift;
                }
                html += `
                    <button type="button" class="slot-card" data-slot-index="${index}" onclick="selectSlot(${index})">
                        <strong>${escapeHtml(slot.start_time)} - ${escapeHtml(slot.end_time)}</strong>
                        <span>${escapeHtml(slot.therapist_name)}</span>
                    </button>
                `;
            });
            grid.innerHTML = html;
        }

        function selectSlot(index) {
            state.selectedSlot = state.availableSlots[index];
            document.querySelectorAll('.slot-card').forEach((node, nodeIndex) => {
                node.classList.toggle('active', nodeIndex === index);
            });
        }

        async function confirmBooking() {
            if (!state.intake?.public_code) {
                showToast('Completa primero tu orientacion.', true);
                return;
            }
            if (!state.selectedSlot) {
                showToast('Selecciona un horario antes de reservar.', true);
                return;
            }

            const response = await fetch('api/public_intake.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'book',
                    public_code: state.intake.public_code,
                    appointment_date: document.getElementById('agenda_date').value,
                    start_time: state.selectedSlot.start_time,
                    therapist_id: state.selectedSlot.therapist_id
                })
            });
            const json = await response.json();
            if (!json.success) {
                showToast(json.error || 'No pudimos reservar tu cita.', true);
                return;
            }

            state.intake = json.intake;
            const appointment = json.appointment;
            const resultBox = document.getElementById('bookingResult');
            const agendaSelector = document.getElementById('agendaSelector');
            const patientName = state.intake?.full_name || document.getElementById('lead_name')?.value || 'Paciente';
            const date = appointment.appointment_date;
            const time = appointment.start_time + ' - ' + appointment.end_time;
            const therapistName = appointment.therapist_name;

            if (agendaSelector) agendaSelector.style.display = 'none';
            resultBox.style.display = 'block';
            resultBox.innerHTML = renderBookedCard(date, time, therapistName, patientName, appointment.type);
            showToast('Reserva confirmada en la agenda real.');
        }

        bootstrap();
        updateScoreValue();
    </script>
</body>
</html>
