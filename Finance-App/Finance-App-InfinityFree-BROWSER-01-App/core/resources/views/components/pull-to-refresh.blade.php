<div
    x-data="{
        pullDistance: 0,
        isDragging: false,
        isReady: false,
        isRefreshing: false,
        startY: 0,
        startX: 0,
        isTracking: false,
        threshold: 54,
        maxPull: 76,

        init() {
            if (!('ontouchstart' in window) && navigator.maxTouchPoints <= 0) {
                return;
            }

            const onTouchStart = (e) => {
                if (e.touches.length !== 1 || this.isRefreshing) return;

                // Hanya izinkan jika posisi scroll window berada tepat di paling atas
                const scrollTop = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
                if (scrollTop > 2) {
                    this.isTracking = false;
                    return;
                }

                const target = e.target;
                if (target.closest('input, textarea, select, [contenteditable=true]')) {
                    this.isTracking = false;
                    return;
                }

                // Cek apakah target berada di dalam elemen yang memiliki scroll internal yang sedang tidak di posisi puncak
                let el = target;
                while (el && el !== document.body && el !== document.documentElement) {
                    if (el.scrollTop > 0) {
                        this.isTracking = false;
                        return;
                    }
                    el = el.parentElement;
                }

                // Jangan memicu jika drawer menu navigasi atau dialog modal sedang aktif
                if (document.body.classList.contains('overflow-hidden') || document.querySelector('[role=dialog]:not([style*=\'display: none\'])')) {
                    this.isTracking = false;
                    return;
                }

                this.startY = e.touches[0].clientY;
                this.startX = e.touches[0].clientX;
                this.isTracking = true;
                this.isDragging = false;
                this.isReady = false;
            };

            const onTouchMove = (e) => {
                if (!this.isTracking || this.isRefreshing) return;

                const scrollTop = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
                if (scrollTop > 2) {
                    this.isTracking = false;
                    this.pullDistance = 0;
                    this.isDragging = false;
                    return;
                }

                const currentY = e.touches[0].clientY;
                const currentX = e.touches[0].clientX;
                const deltaY = currentY - this.startY;
                const deltaX = currentX - this.startX;

                // Jika pengguna menggulir ke atas atau dominan geser horizontal, abaikan
                if (deltaY <= 0 || Math.abs(deltaX) > deltaY) {
                    this.isTracking = false;
                    this.pullDistance = 0;
                    this.isDragging = false;
                    return;
                }

                // Sedang menarik dari puncak halaman
                this.isDragging = true;
                const resistance = Math.min(Math.pow(deltaY, 0.82), this.maxPull);
                this.pullDistance = resistance;
                this.isReady = resistance >= this.threshold;

                if (resistance > 10 && e.cancelable) {
                    e.preventDefault();
                }
            };

            const onTouchEnd = () => {
                if (!this.isTracking || this.isRefreshing) {
                    this.isTracking = false;
                    this.isDragging = false;
                    return;
                }

                this.isTracking = false;
                this.isDragging = false;

                if (this.isReady) {
                    this.isRefreshing = true;
                    this.pullDistance = 46;

                    setTimeout(() => {
                        window.location.reload();
                    }, 350);
                } else {
                    this.pullDistance = 0;
                    this.isReady = false;
                }
            };

            const onTouchCancel = () => {
                this.isTracking = false;
                this.isDragging = false;
                this.pullDistance = 0;
                this.isReady = false;
            };

            window.addEventListener('touchstart', onTouchStart, { passive: true });
            window.addEventListener('touchmove', onTouchMove, { passive: false });
            window.addEventListener('touchend', onTouchEnd, { passive: true });
            window.addEventListener('touchcancel', onTouchCancel, { passive: true });
        }
    }"
    class="pointer-events-none fixed inset-x-0 top-0 z-50 flex justify-center lg:hidden"
    aria-hidden="true"
>
    <div
        x-cloak
        x-show="pullDistance > 0 || isRefreshing"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-75"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-75"
        class="flex h-11 w-11 items-center justify-center rounded-full border border-line bg-white shadow-card transition-transform ease-out"
        :style="{
            transform: `translateY(${pullDistance}px)`,
            transitionDuration: isDragging ? '0ms' : '220ms'
        }"
    >
        {{-- Ikon panah berputar saat ditarik --}}
        <template x-if="!isRefreshing">
            <div
                class="flex items-center justify-center transition-colors duration-150"
                :class="isReady ? 'text-brand' : 'text-muted'"
                :style="{ transform: `rotate(${pullDistance * 5}deg)` }"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
                    <path d="M21 3v5h-5"/>
                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/>
                    <path d="M8 16H3v5"/>
                </svg>
            </div>
        </template>

        {{-- Spinner saat sedang memuat / reloading --}}
        <template x-if="isRefreshing">
            <div class="flex items-center justify-center text-brand">
                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
        </template>
    </div>
</div>
