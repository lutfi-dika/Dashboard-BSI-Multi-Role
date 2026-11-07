<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../pilih-karyawan.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');

function kolom_ada($conn, $table, $col)
{
    $table = mysqli_real_escape_string($conn, $table);
    $col   = mysqli_real_escape_string($conn, $col);
    $q = mysqli_query($conn, "
        SELECT COUNT(*) as jml FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$col'
    ");
    $r = mysqli_fetch_assoc($q);
    return ($r['jml'] ?? 0) > 0;
}

$ada_status_mikro    = kolom_ada($conn, 'nasabah_mikro', 'status');
$ada_status_konsumer = kolom_ada($conn, 'nasabah_konsumer', 'status');

// Urutan tahapan standar pipeline
$tahapan = ['Prospek', 'Diajukan', 'Verifikasi', 'Disetujui', 'Cair', 'Ditolak'];
$tahapan_selesai = ['Cair', 'Ditolak']; // dianggap keluar dari pipeline aktif

$funnel = array_fill_keys($tahapan, 0);
$funnel['Lainnya'] = 0;
$pending_list = [];

if ($ada_status_mikro) {
    $q = mysqli_query($conn, "SELECT status, COUNT(*) as jml FROM nasabah_mikro GROUP BY status");
    while ($row = mysqli_fetch_assoc($q)) {
        $s = $row['status'] ?: 'Lainnya';
        if (in_array($s, $tahapan)) $funnel[$s] += (int)$row['jml'];
        else $funnel['Lainnya'] += (int)$row['jml'];
    }

    $selesai_sql = "'" . implode("','", array_map(fn($s) => mysqli_real_escape_string($conn, $s), $tahapan_selesai)) . "'";
    $q2 = mysqli_query($conn, "SELECT id, nama_pemilik as nama, nama_usaha as info, status, created_at FROM nasabah_mikro WHERE status IS NULL OR status NOT IN ($selesai_sql) ORDER BY created_at DESC LIMIT 20");
    while ($row = mysqli_fetch_assoc($q2)) {
        $row['segmen'] = 'Mikro';
        $pending_list[] = $row;
    }
}

if ($ada_status_konsumer) {
    $q = mysqli_query($conn, "SELECT status, COUNT(*) as jml FROM nasabah_konsumer GROUP BY status");
    while ($row = mysqli_fetch_assoc($q)) {
        $s = $row['status'] ?: 'Lainnya';
        if (in_array($s, $tahapan)) $funnel[$s] += (int)$row['jml'];
        else $funnel['Lainnya'] += (int)$row['jml'];
    }

    $selesai_sql = "'" . implode("','", array_map(fn($s) => mysqli_real_escape_string($conn, $s), $tahapan_selesai)) . "'";
    $q2 = mysqli_query($conn, "SELECT id, nama_lengkap as nama, produk_diajukan as info, status, created_at FROM nasabah_konsumer WHERE status IS NULL OR status NOT IN ($selesai_sql) ORDER BY created_at DESC LIMIT 20");
    while ($row = mysqli_fetch_assoc($q2)) {
        $row['segmen'] = 'Konsumer';
        $pending_list[] = $row;
    }
}

usort($pending_list, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
$pending_list = array_slice($pending_list, 0, 15);

$funnel_max = max(1, max($funnel));

// Data untuk kanban (dipakai JS)
$kanban_data = [];
foreach ($tahapan as $t) $kanban_data[$t] = [];
if ($ada_status_mikro || $ada_status_konsumer) {
    foreach ($pending_list as $item) {
        $stage = in_array($item['status'], $tahapan) ? $item['status'] : 'Prospek';
        $kanban_data[$stage][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipeline - Data Center</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .card {
            background: #ffffff;
            border: 1px solid #eef0f3;
        }

        .kanban-col {
            min-height: 120px;
        }

        .kanban-card {
            cursor: grab;
        }

        .kanban-card.dragging {
            opacity: 0.4;
        }

        .kanban-col.drag-over {
            background: #eef2ff;
        }
    </style>
</head>

<body class="bg-white text-slate-700 min-h-screen flex">

    <aside class="hidden md:flex w-64 bg-white border-r border-slate-100 flex-col justify-between py-6 px-5">
        <div>
            <div class="flex items-center gap-2.5 px-2 mb-8">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">DC</div>
                <span class="font-bold text-slate-800">Data Center</span>
            </div>

            <nav class="space-y-1">
                <a href="../index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9" rx="1" />
                        <rect x="14" y="3" width="7" height="5" rx="1" />
                        <rect x="14" y="12" width="7" height="9" rx="1" />
                        <rect x="3" y="16" width="7" height="5" rx="1" />
                    </svg>
                    Overview Dashboard
                </a>

                <a href="Kinerja.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" />
                        <path d="M18.7 8 12 14.7l-3.3-3.4L3 17" />
                    </svg>
                    Kinerja
                </a>

                <a href="pipeline.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-indigo-50 text-indigo-600 font-semibold text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="6" r="3" />
                        <circle cx="6" cy="18" r="3" />
                        <path d="M6 9v6" />
                        <path d="M6 6h9a3 3 0 0 1 3 3v0a3 3 0 0 1-3 3h-3" />
                        <circle cx="18" cy="18" r="3" />
                    </svg>
                    Pipeline
                </a>

                <?php if (in_array($role_user, ['admin', 'mikro', 'atasan'], true)): ?>
                    <a href="../admin/Mikro/mikro-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">Sektor Mikro</a>
                <?php endif; ?>
                <?php if (in_array($role_user, ['admin', 'konsumer', 'atasan'], true)): ?>
                    <a href="../admin/konsumer/konsumer-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">Sektor Konsumer</a>
                <?php endif; ?>
                <?php if (in_array($role_user, ['admin', 'pawning', 'atasan'], true)): ?>
                    <a href="../admin/pawning/pawning-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">Sektor Gadai</a>
                <?php endif; ?>
                <?php if ($role_user === 'atasan'): ?>
                    <a href="../admin/Atasan/admin-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-violet-50 text-violet-700 font-semibold text-sm transition">Pusat Dashboard Atasan</a>
                <?php endif; ?>
            </nav>
        </div>
        <a href="../auth/logout.php" class="px-3 py-2 text-red-500 font-medium text-sm hover:bg-red-50 rounded-xl">Keluar Sistem</a>
    </aside>

    <main class="flex-1 p-6 md:p-8 overflow-y-auto max-h-screen relative">

        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Pipeline</h1>
                <p class="text-sm text-slate-500 mt-0.5">Status nasabah dari prospek sampai cair.</p>
            </div>
            <div class="flex items-center gap-2.5">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=6366f1&color=fff&bold=true" alt="Foto profil" class="w-9 h-9 rounded-full object-cover ring-2 ring-indigo-500/10">
                <span class="text-sm font-semibold text-slate-700 hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
            </div>
        </header>

        <?php if (!$ada_status_mikro && !$ada_status_konsumer): ?>
            <div class="p-5 bg-amber-50 border border-amber-100 rounded-2xl text-sm text-amber-700 mb-6">
                Kolom <code>status</code> belum ada di tabel <code>nasabah_mikro</code> maupun <code>nasabah_konsumer</code>, jadi funnel dan kanban di bawah masih kosong. Jalankan SQL berikut untuk mengaktifkannya:
                <code class="block mt-3 bg-white/70 p-3 rounded-xl leading-relaxed">
                    ALTER TABLE nasabah_mikro ADD status VARCHAR(20) DEFAULT 'Prospek';<br>
                    ALTER TABLE nasabah_konsumer ADD status VARCHAR(20) DEFAULT 'Prospek';
                </code>
                Nilai yang dikenali: Prospek, Diajukan, Verifikasi, Disetujui, Cair, Ditolak.
            </div>
        <?php endif; ?>

        <!-- FUNNEL -->
        <div class="card p-6 rounded-2xl shadow-xs mb-6">
            <h4 class="text-sm font-bold tracking-wide text-slate-800 mb-5">Funnel Tahapan</h4>
            <div class="space-y-3">
                <?php foreach ($tahapan as $t):
                    $jml = $funnel[$t];
                    $lebar = round(($jml / $funnel_max) * 100);
                    $warna = $t === 'Ditolak' ? 'bg-red-400' : ($t === 'Cair' ? 'bg-emerald-500' : 'bg-indigo-500');
                ?>
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-xs font-semibold text-slate-600 shrink-0"><?= $t; ?></span>
                        <div class="flex-1 h-6 bg-slate-50 rounded-lg overflow-hidden">
                            <div class="h-full <?= $warna; ?> rounded-lg flex items-center justify-end px-2 transition-all" style="width: <?= max(4, $lebar); ?>%">
                                <span class="text-[10px] font-bold text-white"><?= $jml; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($funnel['Lainnya'] > 0): ?>
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-xs font-semibold text-slate-600 shrink-0">Lainnya</span>
                        <div class="flex-1 h-6 bg-slate-50 rounded-lg overflow-hidden">
                            <div class="h-full bg-slate-400 rounded-lg flex items-center justify-end px-2" style="width: <?= max(4, round(($funnel['Lainnya'] / $funnel_max) * 100)); ?>%">
                                <span class="text-[10px] font-bold text-white"><?= $funnel['Lainnya']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KANBAN -->
        <div class="card p-6 rounded-2xl shadow-xs mb-6 overflow-x-auto">
            <div class="flex justify-between items-center mb-5">
                <h4 class="text-sm font-bold tracking-wide text-slate-800">Kanban Board</h4>
                <span class="text-[10px] text-slate-400">Geser kartu untuk ubah status</span>
            </div>
            <div class="flex gap-4" id="kanbanBoard">
                <?php foreach ($tahapan as $t): if (in_array($t, $tahapan_selesai)) continue; ?>
                    <div class="flex-1 sm:min-w-[180px]">
                        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-2 px-1"><?= $t; ?> (<?= count($kanban_data[$t]); ?>)</div>
                        <div class="kanban-col bg-white border border-slate-100 rounded-xl p-2 space-y-2" data-stage="<?= $t; ?>">
                            <?php foreach ($kanban_data[$t] as $item): ?>
                                <div class="kanban-card bg-white border border-slate-100 rounded-lg p-3 shadow-sm text-xs"
                                    draggable="true"
                                    data-id="<?= (int)$item['id']; ?>"
                                    data-table="<?= $item['segmen'] === 'Mikro' ? 'nasabah_mikro' : 'nasabah_konsumer'; ?>">
                                    <p class="font-semibold text-slate-700 truncate"><?= htmlspecialchars($item['nama']); ?></p>
                                    <p class="text-slate-400 truncate mt-0.5"><?= htmlspecialchars($item['info']); ?></p>
                                    <span class="inline-block mt-1.5 px-1.5 py-0.5 rounded text-[9px] font-bold <?= $item['segmen'] === 'Mikro' ? 'bg-indigo-50 text-indigo-600' : 'bg-sky-50 text-sky-600'; ?>"><?= $item['segmen']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- DAFTAR PENDING -->
        <div class="card rounded-2xl shadow-xs overflow-hidden">
            <div class="px-6 pt-6 pb-4">
                <h4 class="text-sm font-bold tracking-wide text-slate-800">Nasabah Masih Berjalan (Belum Cair/Ditolak)</h4>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] text-slate-400 uppercase tracking-wider border-y border-slate-100">
                            <th class="px-6 py-3 font-semibold">Nama</th>
                            <th class="px-6 py-3 font-semibold">Info</th>
                            <th class="px-6 py-3 font-semibold">Segmen</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Tanggal Masuk</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (!empty($pending_list)): ?>
                            <?php foreach ($pending_list as $item): ?>
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="px-6 py-3.5 font-semibold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($item['nama']); ?></td>
                                    <td class="px-6 py-3.5 text-slate-500 truncate max-w-[200px]"><?= htmlspecialchars($item['info']); ?></td>
                                    <td class="px-6 py-3.5">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold <?= $item['segmen'] === 'Mikro' ? 'bg-indigo-50 text-indigo-600 border border-indigo-100' : 'bg-sky-50 text-sky-600 border border-sky-100'; ?>"><?= $item['segmen']; ?></span>
                                    </td>
                                    <td class="px-6 py-3.5 text-slate-500"><?= htmlspecialchars($item['status'] ?: 'Prospek'); ?></td>
                                    <td class="px-6 py-3.5 text-slate-400 text-xs whitespace-nowrap"><?= date('d M Y', strtotime($item['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-xs text-slate-400 italic">Tidak ada nasabah yang sedang berjalan, atau kolom status belum diaktifkan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']); ?>;
        // Drag & drop kanban -> update status lewat pipeline-update-status.php
        let draggedCard = null;

        document.querySelectorAll('.kanban-card').forEach(card => {
            card.addEventListener('dragstart', () => {
                draggedCard = card;
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', () => card.classList.remove('dragging'));
        });

        document.querySelectorAll('.kanban-col').forEach(col => {
            col.addEventListener('dragover', e => {
                e.preventDefault();
                col.classList.add('drag-over');
            });
            col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
            col.addEventListener('drop', async e => {
                e.preventDefault();
                col.classList.remove('drag-over');
                if (!draggedCard) return;

                const newStage = col.dataset.stage;
                const id = draggedCard.dataset.id;
                const table = draggedCard.dataset.table;

                col.appendChild(draggedCard);

                try {
                    const res = await fetch('pipeline-update-status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id,
                            table,
                            status: newStage,
                            csrf_token: CSRF_TOKEN
                        })
                    });
                    const data = await res.json();
                    if (!data.ok) {
                        alert('Gagal update status: ' + (data.error || 'unknown error'));
                    }
                } catch (err) {
                    alert('Gagal menghubungi server: ' + err.message);
                }
            });
        });
    </script>
</body>

</html>