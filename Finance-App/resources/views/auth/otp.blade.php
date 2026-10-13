<x-guest-layout>
    <div class="mb-6">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100">
            <svg class="h-6 w-6 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
        </div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Verify your email</h2>
        <p class="text-sm text-gray-500 mt-1">
            We've sent a 6-digit code to
            <span class="font-semibold text-gray-700">{{ $emailTertutup }}</span>.
            Enter it below to verify your email address.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 p-4 rounded-xl border border-green-200 bg-green-50">
            <p class="text-sm text-green-800">{{ session('status') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50">
            <p class="text-sm text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('otp.verify') }}" id="otp-form">
        @csrf

        <input type="hidden" name="kode" id="otp-kode" value="">

        <!-- 6 kotak input kode OTP (dirender langsung oleh Blade, tanpa Alpine) -->
        <div class="flex justify-between gap-2" role="group" aria-label="Kode OTP" id="otp-boxes">
            @for ($i = 0; $i < 6; $i++)
                <input id="otp-{{ $i }}"
                       class="otp-box w-full min-w-0 h-14 text-center text-xl font-bold rounded-xl border border-gray-200 focus:border-brand focus:ring-1 focus:ring-brand text-gray-900 shadow-sm transition placeholder:text-gray-300 disabled:bg-gray-50 disabled:text-gray-400"
                       type="text"
                       inputmode="numeric"
                       autocomplete="one-time-code"
                       maxlength="1"
                       pattern="[0-9]*"
                       {{ $i === 0 ? 'autofocus' : '' }}>
            @endfor
        </div>

        <x-input-error :messages="$errors->get('kode')" class="mt-2" />

        <div class="mt-6">
            <button type="submit" id="otp-submit" disabled
                    class="w-full py-3 px-4 bg-dark hover:bg-black text-white font-medium rounded-xl shadow-md transition duration-200 text-sm disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-dark">
                <span id="otp-submit-text">Verify email</span>
            </button>
        </div>
    </form>

    <!-- Kirim ulang kode -->
    <div class="mt-6 text-center">
        <form method="POST" action="{{ route('otp.resend') }}">
            @csrf
            <button type="submit" id="otp-resend-btn" disabled
                    class="text-sm font-semibold text-dark hover:underline disabled:no-underline disabled:text-gray-400 disabled:cursor-not-allowed">
                Didn't get the code? Resend
                <span id="otp-countdown">(60s)</span>
            </button>
        </form>

        <p class="mt-3 text-xs text-gray-400">
            Wrong email address?
            <a href="{{ route('register') }}" class="font-semibold text-muted hover:text-dark hover:underline">Register again</a>
        </p>
    </div>

    <script>
    (function () {
        var boxes = Array.prototype.slice.call(document.querySelectorAll('.otp-box'))
        var hidden = document.getElementById('otp-kode')
        var form = document.getElementById('otp-form')
        var btn = document.getElementById('otp-submit')
        var btnText = document.getElementById('otp-submit-text')
        if (!boxes.length || !hidden || !form) return

        var terkirim = false

        function nilai() {
            return boxes.map(function (b) { return (b.value || '').replace(/\D/g, '') }).join('')
        }
        function kosong() {
            for (var i = 0; i < boxes.length; i++) { if (!boxes[i].value) return boxes[i] }
            return null
        }
        function fokusKosong() {
            var k = kosong() || boxes[boxes.length - 1]
            if (k) k.focus()
        }
        function sinkron() {
            hidden.value = nilai()
            btn.disabled = nilai().length !== 6 || terkirim
        }
        function kirim() {
            if (terkirim) return
            terkirim = true
            hidden.value = nilai()
            btn.disabled = true
            btnText.textContent = 'Verifying...'
            boxes.forEach(function (b) { b.disabled = true })
            form.submit()
            // Pemulihan bila request gagal (mis. koneksi putus)
            setTimeout(function () {
                terkirim = false
                btnText.textContent = 'Verify email'
                boxes.forEach(function (b) { b.disabled = false })
                sinkron()
            }, 8000)
        }
        function cek() {
            sinkron()
            if (nilai().length === 6) { kirim(); return }
            fokusKosong()
        }

        boxes.forEach(function (box, i) {
            box.addEventListener('input', function () {
                var bersih = (this.value || '').replace(/\D/g, '')
                if (bersih.length > 1) {
                    // Autofill browser/HP: sebar digit dari kotak ini ke depan
                    for (var j = 0; j < bersih.length && (i + j) < boxes.length; j++) {
                        boxes[i + j].value = bersih[j]
                    }
                } else {
                    this.value = bersih
                }
                cek()
            })
            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !this.value && i > 0) {
                    e.preventDefault()
                    boxes[i - 1].value = ''
                    boxes[i - 1].focus()
                    sinkron()
                } else if (e.key === 'ArrowLeft' && i > 0) {
                    boxes[i - 1].focus()
                } else if (e.key === 'ArrowRight' && i < boxes.length - 1) {
                    boxes[i + 1].focus()
                }
            })
            box.addEventListener('paste', function (e) {
                e.preventDefault()
                var teks = (e.clipboardData && e.clipboardData.getData('text')) || ''
                var angka = teks.replace(/\D/g, '').slice(0, 6).split('')
                if (!angka.length) return
                for (var j = 0; j < angka.length && (i + j) < boxes.length; j++) {
                    boxes[i + j].value = angka[j]
                }
                cek()
            })
            box.addEventListener('focus', function () { this.select() })
        })

        // Ketik digit saat fokus tidak berada di kotak mana pun -> langsung isi kolom kosong pertama
        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey || e.metaKey || e.altKey) return
            var el = document.activeElement
            if (el && el.classList && el.classList.contains('otp-box')) return
            if (/^[0-9]$/.test(e.key)) {
                e.preventDefault()
                var k = kosong()
                if (!k) return
                k.value = e.key
                cek()
            } else if (e.key === 'Backspace') {
                for (var j = boxes.length - 1; j >= 0; j--) {
                    if (boxes[j].value) {
                        e.preventDefault()
                        boxes[j].value = ''
                        boxes[j].focus()
                        sinkron()
                        break
                    }
                }
            }
        })

        // Klik di area kotak (bukan tepat di kotak) -> fokus kolom kosong
        var row = document.getElementById('otp-boxes')
        row.addEventListener('click', function (e) {
            if (!e.target.classList.contains('otp-box')) fokusKosong()
        })

        sinkron()
        boxes[0].focus()

        // Countdown tombol kirim ulang
        var sisa = 60
        var btnResend = document.getElementById('otp-resend-btn')
        var label = document.getElementById('otp-countdown')
        if (btnResend && label) {
            var t = setInterval(function () {
                if (sisa > 0) {
                    sisa--
                    label.textContent = '(' + sisa + 's)'
                } else {
                    clearInterval(t)
                    label.textContent = ''
                    btnResend.disabled = false
                }
            }, 1000)
        }
    })()
    </script>
</x-guest-layout>
