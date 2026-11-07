<div id="realtime-clock" class="fixed bottom-6 right-6 text-xs font-semibold text-slate-500 px-3 py-1.5 rounded-full border border-slate-200/50 backdrop-blur-sm shadow-sm tabular-nums z-50 bg-white/70">
    --:--:--
</div>
<script>
    (function () {
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const el = document.getElementById('realtime-clock');
            if (el) el.textContent = `${hours}:${minutes}:${seconds}`;
        }
        updateClock();
        setInterval(updateClock, 1000);
    })();
</script>
