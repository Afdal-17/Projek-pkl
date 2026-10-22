# Checklist Deploy — Finance App

Dokumen ini melengkapi perbaikan yang **sudah ada di kode** (lihat bagian akhir).
Sisanya adalah tindakan **saat deploy** yang tidak bisa dikerjakan dari repo.

---

## 1. Environment (.env di server)

Salin `.env.example` ke `.env` di server, lalu **wajib** ubah:

| Variabel | Nilai production | Alasan |
|---|---|---|
| `APP_ENV` | `production` | Mode produksi resmi framework |
| `APP_DEBUG` | `false` | **KRITIS** — `true` menampilkan kredensial DB + isi config di halaman error ke siapa pun |
| `APP_KEY` | dihasilkan `php artisan key:generate` di server | Jangan pakai key lokal |
| `APP_URL` | `https://domain-anda.com` | Dipakai link reset password & redirect callback OAuth Google |
| `SESSION_SECURE_COOKIE` | `true` | Cookie login tidak boleh dikirim lewat HTTP polos |
| `TRUSTED_PROXIES` | IP proxy/nginx (mis. `127.0.0.1`) | Agar app tahu aslinya HTTPS di balik reverse proxy |
| `LOG_LEVEL` | `warning` | Log `debug` terlalu ramai & boros disk |
| `DB_USERNAME` / `DB_PASSWORD` | user DB khusus **bukan** `root` | Batasi hak database aplikasi |
| `MAIL_*`, `FONNTE_TOKEN`, `GOOGLE_CLIENT_SECRET` | nilai asli dari **rotasi baru** | Lihat bagian 4 |

PHP di server juga harus mengaktifkan ekstensi `BCMath` (`ext-bcmath`). Aplikasi
menggunakannya untuk validasi dan perhitungan nominal desimal tanpa kehilangan presisi.
Verifikasi di server dengan `composer check-platform-reqs --no-dev`.

## 2. Langkah perintah di server

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # aset Vite (public/build)
php artisan migrate --force       # termasuk migrasi sessions (SESSION_DRIVER=database)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- **Docroot harus folder `public/`** — jangan pernah menaruh project root di web root
  (`index.php`, `.env`, `storage/` akan terekspos).
- `chmod`/`chown` agar `storage/` dan `bootstrap/cache/` dapat ditulis user web server.
- **Jangan jalankan `php artisan db:seed` di production** — `DatabaseSeeder` membuat
  akun `admin@finance.test` dengan password `password` (akun demo).
- Duplicate guard transaksi/transfer memakai cache lock. Gunakan cache store yang
  mendukung lock dan dibagi oleh seluruh instance aplikasi (konfigurasi bawaan
  `database` memakai tabel `cache_locks`; jangan gunakan `array` untuk production
  atau `file` pada beberapa instance).
- Migrasi baru menyelaraskan `notifikasi.jumlah` ke `DECIMAL(20,2)`, sama dengan
  nominal keuangan lain. Sebelum menjalankan migrasi pada database yang pernah
  memakai kolom tersebut, pastikan backup tersedia dan periksa riwayat migrasi
  serta nominal tersimpan; jangan mengulang atau mengubah migrasi backfill lama.

## 3. Google OAuth

Kode mendaftarkan callback sebagai `GET /auth/google/callback`. Socialite memakai
`GOOGLE_REDIRECT_URI` dari konfigurasi; jika variabel itu tidak diisi, nilainya
dibentuk dari `APP_URL` ditambah `/auth/google/callback`. Pastikan URL HTTPS yang
benar-benar dihasilkan konfigurasi server sama persis dengan **Authorized redirect
URI** pada kredensial OAuth di Google Cloud Console (termasuk skema, host, port
jika ada, dan path). Host production belum diketahui dari repository ini, jadi
URI production dan pengaturan Console harus dikonfirmasi manual sebelum deploy.

## 4. Foto profil

Upload saat ini disimpan pada disk `public` lokal di `storage/app/public/avatars`
dan URL-nya memakai `/storage/...`. Pada hosting, pastikan file tersebut dapat
diakses melalui URL itu: sediakan symbolic link `public/storage` atau pemetaan web
server yang setara. Jika hosting tidak mendukung symbolic link, konfigurasikan
penyimpanan publik yang didukung hosting dan URL disk-nya; verifikasi URL gambar
setelah upload dan login ulang. Repository hanya dapat memastikan alur lokal,
bukan konfigurasi filesystem server production.

## 5. HTTPS

- Pasang sertifikat (Let's Encrypt / dll) dan paksa redirect HTTP → HTTPS di nginx.
- Dengan `TRUSTED_PROXIES` + `SESSION_SECURE_COOKIE=true`, header
  `Strict-Transport-Security` akan otomatis dikirim oleh aplikasi (via `SecurityHeaders`).

## 6. Rotasi rahasia (repo GitHub publik: https://github.com/Afdal-17/Projek-pkl)

`.env` tidak pernah ter-commit (sudah diverifikasi), tetapi **file `.env` lokal Anda
berisi rahasia asli** — siapa pun yang pernah melihat layar/backup Anda bisa memilikinya.
Rotasi semuanya sebelum / saat deploy:

- [ ] Password app Gmail (app password) untuk `MAIL_PASSWORD`
- [ ] `GOOGLE_CLIENT_SECRET` di Google Cloud Console
- [ ] `FONNTE_TOKEN` (token Fonnte WhatsApp)
- [ ] Password akun `admin@finance.test` / `user@finance.test` jika pernah dipakai di luar

## 7. Pemantauan pasca-deploy

- [ ] Buka URL yang sengaja salah (mis. `/route-tidak-ada`) — pastikan **tidak** ada
      tampilan debug/stack trace (harus halaman 404 polos).
- [ ] Uji `POST /login` 7x berturut-turut → harus kena rate limit (429) setelah 6 percobaan/menit.
- [ ] Uji lupa password untuk email yang **tidak terdaftar** dan yang terdaftar →
      respons & pesan harus identik (tidak bisa membedakan akun ada/tidak).
- [ ] Cek header respons: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
      `Referrer-Policy`.
- [ ] Pantau `storage/logs/laravel.log`.

## 8. Paket InfinityFree tanpa SSH

Paket dan layout hosting khusus InfinityFree ada di
`deploy/infinityfree/README.txt`; ZIP final dibangun dari worktree terkini.
Paket mengirim source ke `htdocs/core`, hanya public entry/assets di `htdocs`,
menyertakan vendor `--no-dev` dan `public/build`, serta tidak menyertakan `.env`.
Source, konfigurasi, dan dependency ditolak oleh `.htaccess`; avatar disimpan
ke direktori publik fisik tanpa symbolic link.

InfinityFree tidak memiliki akses SSH/Composer yang diasumsikan oleh petunjuk
deploy umum di atas. Paket menyediakan pemeriksaan PHP sekali-pakai dan skrip
migrasi sementara via web yang dilindungi token dari `.env`; backup database
dan tinjauan migration tetap wajib dilakukan manual sebelum skrip migrasi
dijalankan. PHP hosting harus minimal 8.2 dan menyediakan BCMath serta PDO
MySQL. Gunakan FTP untuk upload; uji layanan SMTP/OTP dan HTTPS dari domain
hosting sebelum membuka pendaftaran. Host belum diakses dari sesi ini.

---

## Perbaikan KODE yang sudah dikerjakan (batch ini)

1. **Throttle brute-force auth** — `POST /login`, `/forgot-password`, `/reset-password`
   diberi `throttle:6,1` (6 percobaan/menit per IP).
2. **Anti user-enumeration lupa password** — respons identik untuk email terdaftar /
   tidak terdaftar (termasuk saat throttle & kegagalan SMTP); gagal kirim email hanya
   dilaporkan ke log, tidak membocorkan status akun.
3. **Bug teks** — file `lang/en/passwords.php` dibuat; sebelumnya user melihat kunci
   mentah `passwords.sent` / `passwords.user` di halaman lupa password.
4. **Tabel `sessions` + `password_reset_tokens`** — `.env.example` memakai
   `SESSION_DRIVER=database` tetapi tabel `sessions` tidak pernah ada; setup baru akan
   error di setiap request. (Catatan: alur reset password app ini memakai cache, bukan
   tabel `password_reset_tokens` — klaim sebelumnya bahwa reset password pasti 500 adalah
   **salah**; tabel disiapkan untuk kompatibilitas standar Laravel.)
5. **Security headers global** — middleware baru `SecurityHeaders` (nosniff,
   X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS saat HTTPS).
6. **`TRUSTED_PROXIES`** — `bootstrap/app.php` kini membaca `config('app.trusted_proxies')`
   (aman saat `config:cache`) untuk mengenali HTTPS di balik reverse proxy.
7. **Mass-assignment `role`** — `role` dihapus dari `$fillable` User; pembuatan admin
   (seeder & fixture test) memakai `forceCreate`. Kode aplikasi tidak lagi bisa menaikkan
   role lewat input user dalam kondisi apa pun.
8. **Rate limit OTP per-akun** — `POST /otp/resend` maksimal 6 kirim per 15 menit per
   akun (sebelumnya hanya throttle per-IP; pelaku banyak IP bisa spam satu nomor korban).
9. **View demo dilepas** — `View::addLocation(views/demo)` dihapus; semua view demo
   sudah punya pasangan di `views/` utama (diverifikasi), mencegah render view lama
   tanpa dukungan multi-mata-uang.
10. **`platform-check` diaktifkan + `composer.lock` dirapikan** — ternyata bukan sekadar
    dimatikan tanpa alasan: `composer.lock` berisi 6 paket Symfony **v8.1** (butuh
    PHP ≥ 8.4.1) tercampur dengan inti Symfony v7.4 (PHP ≥ 8.2), plus `laravel/pint`
    v1.32 (butuh PHP ≥ 8.3) — pasti terpasang lewat `composer update --ignore-platform-req`.
    Akibatnya `platform-check` harus dimatikan agar app bisa jalan di PHP 8.2. Diperbaiki
    dengan menurunkan paket-paket itu ke seri 7.x yang kompatibel PHP 8.2
    (`composer update symfony/{clock,css-selector,event-dispatcher,string,translation,yaml}
    laravel/pint laravel/pail --with-all-dependencies`), sehingga `platform-check` bisa
    dinyalakan kembali. Laravel framework ikut naik patch 12.69.3 → 12.70.0.
    Catatan: **di server production, PHP harus ≥ 8.2** — sekarang proteksi ini aktif
    dan akan menolak berjalan di PHP yang terlalu lama (lebih baik error jelas daripada
    bug misterius).
11. **Bersih-bersih** — `a.txt`, `b.txt`, `c.txt`, `syntax.png` (screenshot bug lama yang
    sudah diperbaiki), `public/hot`, `storage/logs/laravel.log`, compiled view.

## Selesai dikerjakan (batch 2)

12. **Password policy lebih kuat** — `Password::defaults()` di `AppServiceProvider`
    kini mensyaratkan minimal 8 karakter + mengandung huruf **dan angka** (berlaku untuk
    registrasi, ganti password, dan reset). Teks petunjuk ditambahkan di 3 form terkait.
    Test yang memakai password lemah diperbarui (`password` → `password1`,
    `new-password` → `new-password1`). Catatan: user lama dengan password lemah tetap
    bisa login — aturan hanya berlaku saat mengganti/membuat password baru.

13. **Hapus akun sendiri tidak lagi error 500** — `ProfileController::destroy` kini
    menghapus semua transfer yang menyentuh dompet user (outgoing maupun incoming)
    lebih dulu dalam satu transaksi DB, sebelum user dihapus (cascade membersihkan
    dompet/transaksi/kategori/target/notifikasi). Konsekuensi yang disengaja: riwayat
    transfer pihak lain tetap ada sebagai baris transaksi dengan `id_transfer` NULL
    (FK `nullOnDelete`), dan saldo pihak lain tidak dihitung ulang.
    **Dua bug ditemukan saat pengerjaan:**
    - Urutan kritis: `Auth::logout()` harus dijalankan **sebelum** `$user->delete()` —
      `logout()` memutar `remember_token` lewat `$user->save()`, dan jika user sudah
      terhapus, `save()` justru INSERT dan menghidupkan kembali baris user (efek "zombie").
    - Test baru: `tests/Feature/AccountDeletionTest.php` (skenario cross-user transfer
      + password salah).

## Belum dikerjakan (butuh keputusan produk)

- Temuan SEDANG/RENDAH lainnya dari analisis lengkap (mis. bug Carbon `addDays(30)` di
  `CheckRole`, inkonsistensi cache store, dsb).
