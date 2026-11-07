<?php
$current_page = basename(parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH) ?: '');
$pawning_menu_groups = [
    'NASABAH' => [
        ['Data Nasabah', 'pawning-data.php', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />'],
    ],
    'PRODUK GADAI' => [
        ['Gadai Emas iB', 'pawning-produk.php?p=Gadai Emas iB', '<circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 2" />'],
        ['Gadai Non Emas', 'pawning-produk.php?p=Gadai Non Emas', '<rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />'],
        ['Fleksi Emas iB', 'pawning-produk.php?p=Fleksi Emas iB', '<path d="m9 12 2 2 4-4" /><path d="M12 3c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8" /><path d="M20 3v5h-5" />'],
    ],
    'GADAI EMAS' => [
        ['Gadai Baru', 'pawning-tambah.php', '<circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 2" />'],
        ['Perpanjangan', 'pawning-perpanjangan.php', '<path d="M21 12a9 9 0 1 1-2.64-6.36" /><polyline points="21 3 21 9 15 9" />'],
        ['Pelunasan', 'pawning-pelunasan.php', '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" /><path d="M3 5v14a2 2 0 0 0 2 2h16v-5" /><path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />'],
        ['Penebusan', 'pawning-penebusan.php', '<path d="m9 12 2 2 4-4" /><path d="M12 3c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8" /><path d="M20 3v5h-5" />'],
        ['Lelang', 'pawning-lelang.php', '<path d="m14.5 12.5-8 8a2.1 2.1 0 1 1-3-3l8-8" /><path d="m16 16 6-6" /><path d="m8 8 6-6" /><path d="m9 7 8 8" /><path d="m21 11-8-8" />'],
    ],
    'DATA BARANG' => [
        ['Barang Gadai', 'pawning-barang.php', '<rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />'],
        ['Foto Barang', 'pawning-foto.php', '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z" /><circle cx="12" cy="13" r="3" />'],
        ['Nilai Taksiran', 'pawning-taksiran.php', '<path d="M12 2v20" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />'],
    ],
    'MONITORING' => [
        ['Jatuh Tempo', 'pawning-jatuh-tempo.php', '<rect width="18" height="18" x="3" y="4" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />'],
        ['Outstanding', 'pawning-outstanding.php', '<line x1="12" y1="20" x2="12" y2="10" /><line x1="18" y1="20" x2="18" y2="4" /><line x1="6" y1="20" x2="6" y2="16" />'],
        ['Pendapatan Ujrah', 'pawning-ujrah.php', '<circle cx="12" cy="12" r="9" /><path d="M14.8 9a2.8 2.8 0 0 0-2.8-1.5c-1.9 0-3 1-3 2.2s1.1 1.9 3 2.3 3 1.1 3 2.3-1.1 2.2-3 2.2A2.8 2.8 0 0 1 9.2 15" /><line x1="12" y1="6" x2="12" y2="7.5" /><line x1="12" y1="16.5" x2="12" y2="18" />'],
    ],
    'LAPORAN' => [
        ['Laporan', 'pawning-laporan.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="14" x2="15" y2="14" /><line x1="9" y1="17" x2="15" y2="17" />'],
        ['Export', 'pawning-export.php', '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />'],
    ],
    'PENGATURAN' => [
        ['Notifikasi', 'pawning-notifikasi.php', '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />'],
        ['Profil', 'pawning-profil.php', '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="10" r="3" /><path d="M7 20.7a7.5 7.5 0 0 1 10 0" />'],
        ['Pengaturan', 'pawning-pengaturan.php', '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />'],
    ],
];

function render_pawning_menu_item($label, $file, $icon_path, $current_page) {
    // Menu produk (mis. "pawning-produk.php?p=Gadai Emas iB") harus dicocokkan lengkap
    // dengan query string-nya, bukan cuma nama file, supaya bisa ter-highlight dengan benar.
    $parts = explode('?', $file, 2);
    $file_base = $parts[0];
    $is_active = false;
    if ($file_base === $current_page) {
        if (isset($parts[1])) {
            parse_str($parts[1], $item_params);
            $is_active = true;
            foreach ($item_params as $key => $value) {
                if (!isset($_GET[$key]) || (string) $_GET[$key] !== (string) $value) {
                    $is_active = false;
                    break;
                }
            }
        } else {
            $is_active = true;
        }
    }
    $active_class = $is_active
        ? 'bg-amber-50 text-amber-700 font-semibold'
        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium';
    echo '<a href="' . htmlspecialchars($file) . '" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition ' . $active_class . '">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">' . $icon_path . '</svg>';
    echo '<span class="truncate">' . htmlspecialchars($label) . '</span>';
    echo '</a>';
}
?>

<aside id="sidebar" class="fixed md:static inset-y-0 left-0 z-50 w-72 max-w-[80%] md:w-64 md:max-w-none bg-white border-r border-slate-100 flex flex-col justify-between py-6 px-5 overflow-y-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    <div>
        <div class="flex items-center justify-between px-2 mb-6">
            <div class="flex items-center gap-2.5">
                <div class="w-11 h-11 rounded-xl flex-shrink-0 overflow-hidden bg-amber-100 border border-amber-200 flex items-center justify-center font-bold text-amber-700">BSI</div>
                <div>
                    <span class="font-bold text-slate-800 text-sm block leading-tight">BSI Pawning</span>
                    <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Gadai Emas</span>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <nav class="space-y-1">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 mb-1.5">Main Menu</p>
            <a href="pawning-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl <?= $current_page === 'pawning-dashboard.php' ? 'bg-amber-50 text-amber-700 font-semibold' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'; ?> text-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" /></svg>
                Dashboard
            </a>

            <?php foreach ($pawning_menu_groups as $group => $items): ?>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 pt-4 mb-1.5"><?= htmlspecialchars($group); ?></p>
                <?php foreach ($items as $item) render_pawning_menu_item($item[0], $item[1], $item[2], $current_page); ?>
            <?php endforeach; ?>
        </nav>
    </div>
    <button data-logout class="w-full px-3 py-2 text-red-500 font-medium text-sm hover:bg-red-50 rounded-xl text-left">Keluar Sistem</button>
</aside>

<div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebarOverlay').classList.toggle('hidden');
}
</script>

<?php include __DIR__ . '/../../components/logout-modal.php'; ?>
