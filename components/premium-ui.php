<?php

/**
 * ============================================================
 * PREMIUM UI KIT — Design System Bersama (Modern Premium)
 * Bank Syariah Indonesia | Dashboard Pendataan
 * ============================================================
 * Dipakai seluruh panel (Mikro, Konsumer, Pawning, Atasan)
 * agar tampilan konsisten: hero banner gradien per segmen,
 * kartu KPI premium, progress bar animasi, tabel & tombol seragam.
 *
 * CARA PAKAI:
 *   1. Di bagian atas file (setelah proteksi session):
 *        require_once __DIR__ . '/../../components/premium-ui.php';
 *
 *   2. Di dalam <head> (menggantikan <title>, tailwind, font, <style>):
 *        <?php premium_head('Judul Halaman', 'mikro'); ?>
 *      Aksen: 'mikro' | 'konsumer' | 'pawning' | 'atasan' | 'indigo'
 *
 *   3. Hero banner di awal <main>:
 *        <?php premium_hero([
 *            'accent'   => 'mikro',
 *            'eyebrow'  => 'BSI Mikro',
 *            'title'    => 'Dashboard Sektor Mikro',
 *            'subtitle' => 'Deskripsi singkat halaman.',
 *            'stats'    => [['label'=>'Debitur','value'=>'120']],
 *            'actions'  => [['href'=>'x.php','label'=>'+ Tambah','style'=>'primary']],
 *        ]); ?>
 *
 *   4. Kartu KPI:
 *        <?php premium_kpi_card([
 *            'label'=>'Total Debitur', 'value'=>number_format($n),
 *            'sub'=>'dari 200 pengajuan', 'tone'=>'indigo',
 *            'icon'=>'<path d="..."/>', 'delay'=>1,
 *        ]); ?>
 */

if (!defined('PREMIUM_UI_LOADED')) {
    define('PREMIUM_UI_LOADED', true);

    /**
     * Konfigurasi aksen warna per segmen/panel.
     * Semua warna disesuaikan dengan identitas BSI:
     * - main   : #006B3F (hijau BSI)
     * - deep   : #004D2E (lebih gelap)
     * - bright : #3DAE7B (hijau terang)
     * - soft   : #E6F4ED (latar hijau lembut)
     * - hero   : gradien dari gelap ke terang
     * - glow   : rgba(0,107,63,0.45)
     */
    function premium_accents(): array
    {
        static $accents = null;
        if ($accents === null) {
            $accents = [
                'mikro' => [
                    'name'   => 'BSI Mikro',
                    'main'   => '#006B3F',
                    'deep'   => '#004D2E',
                    'bright' => '#3DAE7B',
                    'soft'   => '#E6F4ED',
                    'hero'   => 'linear-gradient(125deg,#002B18 0%,#004D2E 40%,#006B3F 70%,#3DAE7B 100%)',
                    'glow'   => 'rgba(0,107,63,0.45)',
                ],
                'konsumer' => [
                    'name'   => 'BSI Konsumer',
                    'main'   => '#006B3F',
                    'deep'   => '#004D2E',
                    'bright' => '#3DAE7B',
                    'soft'   => '#E6F4ED',
                    'hero'   => 'linear-gradient(125deg,#002B18 0%,#004D2E 40%,#006B3F 70%,#3DAE7B 100%)',
                    'glow'   => 'rgba(0,107,63,0.45)',
                ],
                'pawning' => [
                    'name'   => 'BSI Pawning',
                    'main'   => '#006B3F',
                    'deep'   => '#004D2E',
                    'bright' => '#3DAE7B',
                    'soft'   => '#E6F4ED',
                    'hero'   => 'linear-gradient(125deg,#002B18 0%,#004D2E 40%,#006B3F 70%,#3DAE7B 100%)',
                    'glow'   => 'rgba(0,107,63,0.45)',
                ],
                'atasan' => [
                    'name'   => 'BSI Executive',
                    'main'   => '#006B3F',
                    'deep'   => '#004D2E',
                    'bright' => '#3DAE7B',
                    'soft'   => '#E6F4ED',
                    'hero'   => 'linear-gradient(125deg,#002B18 0%,#004D2E 40%,#006B3F 70%,#3DAE7B 100%)',
                    'glow'   => 'rgba(0,107,63,0.45)',
                ],
                'indigo' => [
                    'name'   => 'Data Center',
                    'main'   => '#006B3F',
                    'deep'   => '#004D2E',
                    'bright' => '#3DAE7B',
                    'soft'   => '#E6F4ED',
                    'hero'   => 'linear-gradient(125deg,#002B18 0%,#004D2E 40%,#006B3F 70%,#3DAE7B 100%)',
                    'glow'   => 'rgba(0,107,63,0.45)',
                ],
            ];
        }
        return $accents;
    }

    /**
     * Pemetaan tone -> kelas Tailwind untuk ikon KPI.
     */
    function premium_tone(string $tone): array
    {
        $tones = [
            'indigo'  => ['bg-indigo-50', 'text-indigo-600'],
            'sky'     => ['bg-sky-50', 'text-sky-600'],
            'emerald' => ['bg-emerald-50', 'text-emerald-600'],
            'amber'   => ['bg-amber-50', 'text-amber-600'],
            'rose'    => ['bg-rose-50', 'text-rose-600'],
            'violet'  => ['bg-violet-50', 'text-violet-600'],
            'pink'    => ['bg-pink-50', 'text-pink-600'],
            'slate'   => ['bg-slate-100', 'text-slate-600'],
        ];
        return $tones[$tone] ?? $tones['indigo'];
    }

    /**
     * Head lengkap: title + tailwind + font + seluruh CSS premium.
     * Aksen ditentukan lewat variabel CSS pada body.
     */
    function premium_head(string $title, string $accent = 'indigo'): void
    {
        $a = premium_accents()[$accent] ?? premium_accents()['indigo'];
?>
        <title><?= htmlspecialchars($title); ?></title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                background: #f3f5fa;
                --pm-main: <?= $a['main']; ?>;
                --pm-deep: <?= $a['deep']; ?>;
                --pm-bright: <?= $a['bright']; ?>;
                --pm-soft: <?= $a['soft']; ?>;
                --pm-glow: <?= $a['glow']; ?>;
                --pm-hero: <?= $a['hero']; ?>;
            }

            body.sidebar-open {
                overflow: hidden;
            }

            /* ============ KARTU DASAR ============ */
            .pm-card {
                background: #ffffff;
                border: 1px solid #e9edf4;
                border-radius: 18px;
                box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
                transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
            }

            .pm-card-hover:hover {
                transform: translateY(-3px);
                box-shadow: 0 14px 30px -14px rgba(15, 23, 42, .18);
                border-color: #dbe3ef;
            }

            /* ============ KARTU KPI ============ */
            .pm-kpi {
                padding: 20px;
                position: relative;
                overflow: hidden;
            }

            .pm-kpi::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                border-radius: 0 4px 4px 0;
                background: linear-gradient(180deg, var(--pm-bright), var(--pm-main));
                opacity: 0;
                transition: opacity .25s ease;
            }

            .pm-kpi:hover::before {
                opacity: 1;
            }

            .pm-kpi-icon {
                width: 46px;
                height: 46px;
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .pm-spark {
                height: 36px;
                position: relative;
            }

            /* ============ HERO BANNER ============ */
            .pm-hero {
                position: relative;
                overflow: hidden;
                border-radius: 20px;
                padding: 24px 26px;
                color: #fff;
                background: var(--pm-hero);
                box-shadow: 0 20px 45px -20px var(--pm-glow);
                margin-bottom: 24px;
            }

            .pm-hero::before {
                content: '';
                position: absolute;
                width: 360px;
                height: 360px;
                border-radius: 50%;
                background: rgba(255, 255, 255, .09);
                top: -170px;
                right: -90px;
            }

            .pm-hero::after {
                content: '';
                position: absolute;
                width: 230px;
                height: 230px;
                border-radius: 50%;
                background: rgba(255, 255, 255, .07);
                bottom: -140px;
                left: 16%;
            }

            .pm-hero-pattern {
                position: absolute;
                inset: 0;
                background-image: radial-gradient(rgba(255, 255, 255, .16) 1px, transparent 1px);
                background-size: 18px 18px;
                opacity: .3;
                pointer-events: none;
                -webkit-mask-image: linear-gradient(to left, black, transparent 60%);
                mask-image: linear-gradient(to left, black, transparent 60%);
            }

            .pm-chip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: rgba(255, 255, 255, .15);
                border: 1px solid rgba(255, 255, 255, .28);
                backdrop-filter: blur(6px);
                border-radius: 999px;
                padding: 5px 12px;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: .12em;
                text-transform: uppercase;
                color: rgba(255, 255, 255, .95);
            }

            .pm-hero-stat {
                background: rgba(255, 255, 255, .13);
                border: 1px solid rgba(255, 255, 255, .24);
                backdrop-filter: blur(8px);
                border-radius: 14px;
                padding: 10px 16px;
                min-width: 108px;
            }

            .pm-hero-stat b {
                display: block;
                font-size: 17px;
                font-weight: 800;
                line-height: 1.2;
                white-space: nowrap;
            }

            .pm-hero-stat span {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .08em;
                color: rgba(255, 255, 255, .75);
            }

            /* ============ TOMBOL ============ */
            .pm-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-weight: 700;
                font-size: 12px;
                padding: 10px 18px;
                border-radius: 12px;
                transition: all .2s ease;
                cursor: pointer;
                white-space: nowrap;
            }

            .pm-btn-primary {
                color: #fff;
                background: linear-gradient(135deg, var(--pm-deep), var(--pm-main) 55%, var(--pm-bright));
                box-shadow: 0 10px 22px -10px var(--pm-glow);
            }

            .pm-btn-primary:hover {
                filter: brightness(1.08);
                transform: translateY(-1px);
                box-shadow: 0 14px 28px -12px var(--pm-glow);
            }

            .pm-btn-ghost {
                background: #fff;
                color: #334155;
                border: 1px solid #e2e8f0;
            }

            .pm-btn-ghost:hover {
                background: #f8fafc;
                border-color: #cbd5e1;
                transform: translateY(-1px);
            }

            .pm-btn-white {
                background: rgba(255, 255, 255, .92);
                color: #1e293b;
                box-shadow: 0 8px 18px -8px rgba(0, 0, 0, .35);
            }

            .pm-btn-white:hover {
                background: #fff;
                transform: translateY(-1px);
            }

            /* ============ PROGRESS BAR ANIMASI ============ */
            .pm-progress {
                height: 8px;
                border-radius: 999px;
                background: #eef2f7;
                overflow: hidden;
            }

            .pm-progress-fill {
                display: block;
                height: 100%;
                border-radius: 999px;
                animation: pmGrow 1.1s cubic-bezier(.22, 1, .36, 1) both;
            }

            @keyframes pmGrow {
                from {
                    width: 0 !important;
                }
            }

            /* ============ ANIMASI MASUK ============ */
            .pm-fade {
                opacity: 0;
                transform: translateY(12px);
                animation: pmFadeUp .55s cubic-bezier(.22, 1, .36, 1) forwards;
            }

            @keyframes pmFadeUp {
                to {
                    opacity: 1;
                    transform: none;
                }
            }

            .pm-d1 {
                animation-delay: .04s;
            }

            .pm-d2 {
                animation-delay: .08s;
            }

            .pm-d3 {
                animation-delay: .12s;
            }

            .pm-d4 {
                animation-delay: .16s;
            }

            .pm-d5 {
                animation-delay: .20s;
            }

            .pm-d6 {
                animation-delay: .24s;
            }

            .pm-d7 {
                animation-delay: .28s;
            }

            .pm-d8 {
                animation-delay: .32s;
            }

            /* ============ TABEL PREMIUM ============ */
            .pm-table-wrap {
                overflow-x: auto;
            }

            .pm-table {
                width: 100%;
                text-align: left;
                border-collapse: separate;
                border-spacing: 0;
                font-size: 13px;
            }

            .pm-table thead th {
                background: #f8fafc;
                text-transform: uppercase;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: .08em;
                color: #94a3b8;
                padding: 12px 18px;
                border-bottom: 1px solid #eef2f7;
                white-space: nowrap;
            }

            .pm-table tbody td {
                padding: 13px 18px;
                border-bottom: 1px solid #f1f5f9;
                color: #475569;
                vertical-align: middle;
            }

            .pm-table tbody tr {
                transition: background .15s ease;
            }

            .pm-table tbody tr:hover {
                background: #f8fafc;
            }

            .pm-table tbody tr:last-child td {
                border-bottom: none;
            }

            /* ============ BADGE / PIL ============ */
            .pm-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 3px 10px;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: .02em;
                white-space: nowrap;
            }

            /* ============ SCROLLBAR TIPIS ============ */
            .pm-scroll::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }

            .pm-scroll::-webkit-scrollbar-track {
                background: transparent;
            }

            .pm-scroll::-webkit-scrollbar-thumb {
                background: #dbe3ef;
                border-radius: 999px;
            }

            .pm-scroll::-webkit-scrollbar-thumb:hover {
                background: #cbd5e1;
            }

            /* ============ RING / DONUT WRAP ============ */
            .pm-ring-wrap {
                position: relative;
                margin: 0 auto;
            }

            .pm-ring-wrap canvas {
                position: relative;
                z-index: 1;
            }

            .pm-ring-center {
                position: absolute;
                inset: 0;
                z-index: 2;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                pointer-events: none;
            }
        </style>
        <script>
            // Fallback global: dipakai jika halaman belum mendefinisikan sendiri.
            if (typeof window.toggleSidebar !== 'function') {
                window.toggleSidebar = function() {
                    var sidebar = document.getElementById('sidebar');
                    var overlay = document.getElementById('sidebarOverlay');
                    if (!sidebar) return;
                    var isOpen = !sidebar.classList.contains('-translate-x-full');
                    if (isOpen) {
                        sidebar.classList.add('-translate-x-full');
                        if (overlay) overlay.classList.add('hidden');
                        document.body.classList.remove('sidebar-open');
                    } else {
                        sidebar.classList.remove('-translate-x-full');
                        if (overlay) overlay.classList.remove('hidden');
                        document.body.classList.add('sidebar-open');
                    }
                };
            }
            if (typeof window.toggleProfilePopup !== 'function') {
                window.toggleProfilePopup = function(event) {
                    if (event) event.stopPropagation();
                    var popup = document.getElementById('profilePopup');
                    if (popup) popup.classList.toggle('hidden');
                };
            }
        </script>
    <?php
    }

    /**
     * Hero banner gradien dengan chip, statistik ringkas, dan tombol aksi.
     */
    function premium_hero(array $opts): void
    {
        $accentKey = $opts['accent'] ?? 'indigo';
        $a         = premium_accents()[$accentKey] ?? premium_accents()['indigo'];
        $eyebrow   = $opts['eyebrow'] ?? $a['name'];
        $title     = $opts['title'] ?? '';
        $subtitle  = $opts['subtitle'] ?? '';
        $stats     = $opts['stats'] ?? [];
        $actions   = $opts['actions'] ?? [];
        $tanggal   = $opts['show_date'] ?? true;
    ?>
        <div class="pm-hero pm-fade">
            <div class="pm-hero-pattern"></div>
            <div class="relative z-[1] flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-2.5">
                        <span class="pm-chip"><?= htmlspecialchars($eyebrow); ?></span>
                        <?php if ($tanggal): ?>
                            <span class="pm-chip" style="letter-spacing:.04em;text-transform:none;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                                <?= date('d M Y'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-xl sm:text-2xl lg:text-[27px] font-extrabold tracking-tight leading-tight"><?= $title; ?></h1>
                    <?php if ($subtitle): ?>
                        <p class="text-white/75 text-xs sm:text-sm mt-1.5 max-w-2xl leading-relaxed"><?= $subtitle; ?></p>
                    <?php endif; ?>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <?php foreach ($stats as $st): ?>
                        <div class="pm-hero-stat">
                            <span><?= htmlspecialchars($st['label'] ?? ''); ?></span>
                            <b><?= $st['value'] ?? ''; ?></b>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($actions as $act): ?>
                        <a href="<?= htmlspecialchars($act['href'] ?? '#'); ?>"
                            class="pm-btn <?= ($act['style'] ?? 'primary') === 'ghost' ? 'pm-btn-white' : 'pm-btn-white'; ?>">
                            <?= $act['label'] ?? ''; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Kartu KPI premium.
     * Opsi: label, value, sub, sub_class, tone, icon (inner svg), spark (canvas id), delay (1-8), hover (bool)
     */
    function premium_kpi_card(array $o): void
    {
        $tone  = $o['tone'] ?? 'indigo';
        [$bg, $tx] = premium_tone($tone);
        $delay = !empty($o['delay']) ? ' pm-d' . (int) $o['delay'] : '';
        $hover = isset($o['hover']) && !$o['hover'] ? '' : ' pm-card-hover';
        $icon  = $o['icon'] ?? '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>';
    ?>
        <div class="pm-card pm-kpi pm-fade<?= $delay . $hover; ?>">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 truncate"><?= $o['label'] ?? ''; ?></p>
                    <h3 class="mt-1.5 text-2xl sm:text-[26px] font-extrabold tracking-tight text-slate-900 truncate <?= $o['value_class'] ?? ''; ?>"><?= $o['value'] ?? ''; ?></h3>
                    <?php if (!empty($o['sub'])): ?>
                        <p class="mt-1 text-xs font-semibold <?= $o['sub_class'] ?? 'text-slate-400'; ?>"><?= $o['sub']; ?></p>
                    <?php endif; ?>
                </div>
                <div class="<?= $bg; ?> <?= $tx; ?> pm-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icon; ?></svg>
                </div>
            </div>
            <?php if (!empty($o['spark'])): ?>
                <div class="pm-spark mt-3"><canvas id="<?= htmlspecialchars($o['spark']); ?>"></canvas></div>
            <?php endif; ?>
        </div>
    <?php
    }

    /**
     * Header section kartu: judul + deskripsi opsional + aksi kanan.
     */
    function premium_section_header(string $title, string $sub = '', string $actionHtml = ''): void
    {
    ?>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h4 class="text-sm font-extrabold tracking-tight text-slate-800"><?= htmlspecialchars($title); ?></h4>
                <?php if ($sub): ?><p class="text-[11px] text-slate-400 mt-0.5"><?= htmlspecialchars($sub); ?></p><?php endif; ?>
            </div>
            <?= $actionHtml; ?>
        </div>
<?php
    }
}
