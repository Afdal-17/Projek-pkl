# Panduan Integrasi Google Sign-In Mobile

Panduan ini menjelaskan implementasi backend yang berlaku untuk endpoint
`POST /api/auth/google`, konfigurasi Google Cloud, kontrak Flutter, serta langkah
migrasi dan pengujian. Kode sumber di repository adalah acuan implementasi.

## Ringkasan

Sebelumnya endpoint mobile mempercayai `email` dan `google_id` yang dikirim
klien. Sekarang endpoint mewajibkan Google ID token, memverifikasi token di
backend, lalu mengambil identitas dari klaim token yang sudah diverifikasi.
Token Sanctum hanya diterbitkan untuk akun yang aktif, sudah menyelesaikan OTP,
dan tidak sedang diblokir.

Alur OAuth web Socialite tetap memakai `GOOGLE_CLIENT_ID`,
`GOOGLE_CLIENT_SECRET`, dan `GOOGLE_REDIRECT_URI` seperti sebelumnya.

## Konfigurasi Google

- Atur Flutter `serverClientId` menggunakan **Web Client ID**.
- Web Client ID harus berasal dari Google Cloud Project yang sama dengan OAuth
  Client Android (package name + SHA-1).
- Backend memakai Web Client ID yang sama pada `GOOGLE_CLIENT_ID`; backend tidak
  memerlukan Android Client ID di environment.
- Jangan menaruh `GOOGLE_CLIENT_SECRET` di aplikasi mobile atau membagikan file
  `.env`.
- Verifikasi TLS untuk mengambil kunci Google harus aktif di production.

Tidak ada variabel `GOOGLE_MOBILE_CLIENT_ID`: audience ID token mobile adalah
Web Client ID yang dipakai sebagai `serverClientId`.

## Kontrak API Mobile

```http
POST /api/auth/google
Content-Type: application/json
Accept: application/json

{
  "id_token": "<Google ID token>",
  "mode": "login"
}
```

`mode` wajib berisi `login` atau `register`. Jangan mengirim `email`,
`google_id`, `nama`, atau `avatar_path` sebagai bukti identitas; backend hanya
menggunakan klaim token yang telah diverifikasi.

### Respons

| Status | Perilaku |
|---|---|
| 200 | Login berhasil; respons berisi Sanctum `token` dan data user yang diizinkan |
| 201 | Registrasi baru; akun dibuat pending dan OTP dikirim. Verifikasi OTP sebelum menyimpan token |
| 401 | Token invalid, kedaluwarsa, issuer/audience tidak cocok, atau email Google belum terverifikasi |
| 403 | Akun diblokir atau tidak aktif |
| 404 | `mode=login`, tetapi akun belum terdaftar |
| 409 | Akun belum menyelesaikan OTP atau Google ID bertentangan dengan tautan akun yang sudah ada |
| 422 | `id_token` atau `mode` tidak valid/tidak dikirim |
| 429 | Batas 10 request per menit per IP tercapai |
| 503 | Kunci Google atau pengiriman OTP sementara tidak tersedia |

Respons `201` berisi `user_id` dan `email`; klien melanjutkan ke
`POST /api/register/verify-otp`. Jika pengiriman OTP gagal, respons `503`
menyertakan `user_id` karena akun pending tetap tersimpan; klien dapat memakai
`POST /api/register/resend-otp`. Jangan menyimpan token Sanctum sebelum OTP
terverifikasi dan endpoint mengembalikan token.

Akun aktif yang sudah tertaut ke Google ID yang sama dapat login. Jika email
cocok tetapi akun sudah terhubung ke Google ID berbeda, backend mengembalikan
`409` dan tidak mengganti tautan secara diam-diam.

## Verifikasi Token

`GoogleIdTokenVerifier` memverifikasi tanda tangan RS256 dengan JWKS resmi Google,
lalu memeriksa:

- issuer Google yang diizinkan;
- audience yang sama persis dengan `GOOGLE_CLIENT_ID`;
- `exp`, `iat`, `sub`, dan email;
- `email_verified` bernilai boolean `true`.

JWKS disimpan di cache selama 24 jam. Cache hanya diambil ulang saat `kid` tidak
dikenal; signature invalid untuk `kid` yang sudah dikenal ditolak tanpa
menghapus cache atau membuat permintaan JWKS tambahan. Error internal dicatat di
log, sementara respons klien tidak memuat detail exception.

## Tautan Akun, OTP, dan Data User

- `sub` token menjadi `google_id`; email dinormalisasi ke huruf kecil.
- Akun baru dibuat dengan status pending dan mengikuti alur OTP yang sudah ada.
- Akun dengan Google ID yang berbeda tidak ditautkan otomatis.
- Akun yang masa blokirnya telah lewat 30 hari dibuka kembali sebelum pemeriksaan
  status login.
- Respons login hanya memuat field profil yang diperlukan; field internal user
  tidak diserialisasi seluruhnya.
- Route Google mobile dibatasi 10 request per menit per IP.

Migration menambahkan unique index nullable pada `user.google_id`. Sebelum
menjalankan migration pada database yang sudah berisi data, periksa duplikasi:

```sql
SELECT google_id, COUNT(*)
FROM user
WHERE google_id IS NOT NULL
GROUP BY google_id
HAVING COUNT(*) > 1;
```

Jika query menemukan duplikasi, selesaikan penautan datanya secara manual
sebelum migration. Migration sengaja berhenti dengan pesan jelas dan tidak
menghapus atau menggabungkan akun otomatis.

## File yang Berubah

- [`app/Services/GoogleIdTokenVerifier.php`](../app/Services/GoogleIdTokenVerifier.php) — verifikasi tanda tangan dan klaim ID token.
- [`app/Http/Controllers/Auth/GoogleAuthController.php`](../app/Http/Controllers/Auth/GoogleAuthController.php) — autentikasi token, registrasi OTP, pemeriksaan tautan akun.
- [`routes/api.php`](../routes/api.php) — rate limit endpoint Google mobile.
- [`database/migrations/2026_11_19_120000_add_unique_google_id_to_user_table.php`](../database/migrations/2026_11_19_120000_add_unique_google_id_to_user_table.php) — unique index Google ID dengan preflight duplikasi.
- [`composer.json`](../composer.json) dan [`composer.lock`](../composer.lock) — dependensi langsung `firebase/php-jwt`.
- [`tests/Feature/Auth/GoogleMobileAuthTest.php`](../tests/Feature/Auth/GoogleMobileAuthTest.php) — tes verifier dan endpoint mobile.
- [`docs/MOBILE_INTEGRATION.md`](./MOBILE_INTEGRATION.md) — request/response dan panduan Flutter.

## Instalasi dan Verifikasi

Dari direktori `BE`:

```bash
composer install
php artisan migrate --force
php artisan test --filter=GoogleMobileAuthTest
php artisan test --filter=GoogleAndPhoneAuthTest
```

Tes mobile mencakup audience salah, token kedaluwarsa, email belum diverifikasi,
signature invalid, refresh JWKS hanya untuk `kid` baru, pengabaian profil mentah,
konflik tautan akun, registrasi OTP, serta login setelah masa blokir berakhir.

