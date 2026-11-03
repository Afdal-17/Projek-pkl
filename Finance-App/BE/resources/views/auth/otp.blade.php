<x-guest-layout>
    @php($kanal = $kanal ?? 'email')

    <div class="mb-6 text-center">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 mx-auto">
            @if ($kanal === 'whatsapp')
                <svg class="h-6 w-6 text-dark" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
            @else
                <svg class="h-6 w-6 text-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
            @endif
        </div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Verify your {{ $kanal === 'whatsapp' ? 'WhatsApp' : 'email' }}</h2>
        <p class="text-sm text-gray-500 mt-1">
            We've sent a 6-digit code to
            <span class="font-semibold text-gray-700">{{ $tujuanTertutup }}</span>.
            Enter it below to verify your {{ $kanal === 'whatsapp' ? 'WhatsApp number' : 'email address' }}.
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
                <span id="otp-submit-text">Verify {{ $kanal === 'whatsapp' ? 'WhatsApp' : 'email' }}</span>
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
            Wrong {{ $kanal === 'whatsapp' ? 'phone number' : 'email address' }}?
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
        var teksAwalBtn = btnText ? btnText.textContent.trim() : 'Verify email'

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
                btnText.textContent = teksAwalBtn
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
