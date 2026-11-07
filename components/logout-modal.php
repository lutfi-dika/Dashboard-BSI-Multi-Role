<?php

/**
 * Komponen modal konfirmasi logout yang elegan.
 * Include di setiap halaman dashboard SETELAH sidebar.
 * 
 * Usage: include __DIR__ . '/../../components/logout-modal.php';
 * 
 * Variabel yang dibutuhkan (otomatis dari session):
 * - $nama_user
 * - $role_user
 */
if (!isset($nama_user)) $nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');
if (!isset($role_user)) $role_user = $_SESSION['role'] ?? '';
?>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4" style="display:none;">
    <!-- Backdrop -->
    <div id="logoutBackdrop" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm opacity-0 transition-opacity duration-300"></div>

    <!-- Modal Card -->
    <div id="logoutCard" class="relative w-full max-w-sm bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden transform scale-90 opacity-0 transition-all duration-300 ease-out">

        <!-- Header dengan gradien hijau BSI (dari deep ke bright) -->
        <div class="relative h-24 bg-gradient-to-br from-[#004D2E] via-[#006B3F] to-[#3DAE7B] flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 400 120" fill="none">
                    <circle cx="350" cy="20" r="80" fill="white" />
                    <circle cx="50" cy="100" r="60" fill="white" />
                </svg>
            </div>
            <div class="relative text-center">
                <div class="w-14 h-14 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto border border-white/30 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="px-6 pt-5 pb-6 text-center">
            <h3 class="text-lg font-extrabold text-slate-800 mb-1">Keluar dari Sistem?</h3>
            <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                Anda akan keluar dari sesi dashboard ini.
                Pastikan semua pekerjaan sudah tersimpan.
            </p>

            <!-- User Info Card (dengan aksen BSI soft) -->
            <div class="flex items-center gap-3 bg-[#E6F4ED] rounded-2xl px-4 py-3 mb-6 border border-[#3DAE7B]/20">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=006B3F&color=fff&bold=true&size=40" alt="avatar" class="w-10 h-10 rounded-xl ring-2 ring-white shadow-sm">
                <div class="text-left min-w-0">
                    <p class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($nama_user); ?></p>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#006B3F]"><?= htmlspecialchars(ucfirst($role_user)); ?></p>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3">
                <button id="btnBatal" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold py-3 rounded-xl transition-all duration-200 border border-slate-200 hover:border-slate-300 active:scale-[0.97]">
                    Batal
                </button>
                <a href="<?= $base_url ?? '../../'; ?>auth/logout.php" id="btnKonfirmasi" class="flex-1 bg-gradient-to-r from-[#004D2E] via-[#006B3F] to-[#3DAE7B] hover:brightness-110 text-white text-xs font-bold py-3 rounded-xl transition-all duration-200 shadow-lg shadow-[#006B3F]/25 hover:shadow-[#006B3F]/40 active:scale-[0.97] text-center">
                    Ya, Keluar
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const modal = document.getElementById('logoutModal');
        const backdrop = document.getElementById('logoutBackdrop');
        const card = document.getElementById('logoutCard');
        const btnBatal = document.getElementById('btnBatal');

        function openLogoutModal(e) {
            if (e) e.preventDefault();
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                card.classList.remove('scale-90', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            });
        }

        function closeLogoutModal() {
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-90', 'opacity-0');
            setTimeout(() => {
                modal.style.display = 'none';
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        }

        // Bind all logout links
        document.querySelectorAll('[data-logout]').forEach(el => {
            el.addEventListener('click', openLogoutModal);
        });

        btnBatal.addEventListener('click', closeLogoutModal);
        backdrop.addEventListener('click', closeLogoutModal);

        // Close on Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeLogoutModal();
            }
        });
    })();
</script>