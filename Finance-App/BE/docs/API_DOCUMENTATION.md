# Dokumentasi API — Finance App (Backend Laravel)

**Base URL (lokal):** `http://127.0.0.1:8003/api`
**Base URL (Ngrok, aktif):** `https://sinuous-fame-jazz.ngrok-free.dev/api`
**Base URL (HP di Wi-Fi sama):** `http://<IP-komputer>:8003/api`

> ### ⚠️ Tiga aturan yang WAJIB dipatuhi klien
>
> 1. **Selalu pakai prefix `/api`.** URL tanpa `/api` (mis. `POST /login`) jatuh ke route
>    web dan membalas **419 `CSRF token mismatch`** — ini bukan bug token Anda, melainkan
>    karena endpoint-nya salah alamat.
> 2. **Selalu kirim header `Accept: application/json`.** Tanpa header ini banyak endpoint
>    mengembalikan **view HTML / redirect 302**, dan error validasi jadi redirect 302
>    alih-alih JSON 422.
> 3. **Port server.** `php artisan serve` berjalan di port **8003**. Port **8000** adalah
>    Apache XAMPP dan akan membalas **403** untuk semua request — bukan Laravel.
>
> Untuk HP di jaringan Wi-Fi yang sama, jalankan:
> `php artisan serve --host=0.0.0.0 --port=8003`

### Menggunakan ngrok (untuk frontend/mobile di luar mesin ini)

ngrok **sudah terpasang dan tunnel aktif** menunjuk ke port 8003. Cek ulang URL-nya kapan
saja lewat `http://127.0.0.1:4040/api/tunnels` — **URL gratisan berubah setiap kali ngrok
di-restart**, jadi jangan hardcode di produksi.

```bash
# Jika tunnel belum jalan / URL sudah berubah:
ngrok http 8003
# Lalu ambil "public_url" dari layar ngrok atau dari API 4040 di atas
```

Sudah diverifikasi berfungsi lewat ngrok: `POST /api/login` → 200 + token,
`GET /api/dompet|dashboard/summary|transactions|transfer/recipients/search` → 200,
preflight CORS `OPTIONS` → 204 dengan `Access-Control-Allow-Origin` terpantul benar.

**Hal yang perlu diketahui saat pakai ngrok:**
- Alamat **HTTPS** otomatis — tidak perlu set `SESSION_SECURE_COOKIE` apa pun untuk API
  (API memakai Bearer token, bukan cookie).
- `TRUSTED_PROXIES` **tidak wajib** untuk API. Isi hanya bila ada perilaku aneh terkait
  skema URL (contoh: `.env` → `TRUSTED_PROXIES=127.0.0.1`).
- Halaman peringatan ngrok hanya muncul untuk **browser**; request API dengan header
  `Accept: application/json` langsung lolos.
- Untuk produksi sungguhan, ganti ke domain sendiri — jangan pakai ngrok.

Semua endpoint di bawah **mengembalikan JSON**.

## Cara Autentikasi

1. Login dulu di `POST /api/login` → simpan nilai `token`.
2. Untuk semua endpoint selanjutnya, kirim header:

```
Authorization: Bearer <token>
Accept: application/json
```

3. Logout: `POST /api/logout` (token yang dipakai dicabut).

**Contoh login:**

```json
POST /api/login
{ "email": "user@finance.test", "password": "password" }

200 OK
{
  "message": "Login berhasil",
  "token": "1|xxxxxxxxxxxx",
  "user": { "id_user": 3, "nama": "User", "name": "User", "email": "user@finance.test", "role": "user" }
}
```

Login juga menerima `phone` (pengganti `email`) dan `remember` (boolean).
Akun demo: `admin@finance.test` / `user@finance.test`, password `password`.

**Kegagalan login → 422** (bukan 401/429):

```json
{ "message": "These credentials do not match our records.", "errors": { "email": ["..."] } }
```

Rate limit 5 percobaan per identifier juga membalas **422** dengan pesan throttle.

---

## 1. Autentikasi & Akun

| Method | Endpoint | Deskripsi |
|---|---|---|
| POST | `/api/login` | Login, mengembalikan token + data user |
| POST | `/api/register` | Registrasi akun baru via email (mengirim kode OTP) |
| POST | `/api/register/verify-otp` | Verifikasi 6 digit kode OTP (mengaktifkan akun & mengembalikan token) |
| POST | `/api/auth/google` | Login / Register via Google (wajib field `mode`: `login`\|`register`) |
| POST | `/api/forgot-password` | Kirim token/link reset password ke email |
| POST | `/api/reset-password` | Ganti password baru menggunakan token reset |
| GET | `/user` | Data user yang sedang login |
| GET | `/account` | Profil akun + `avatar_url` |
| PATCH | `/account` | Ubah profil (`nama`, `email`, `nomor_telepon`, `lokasi`, `mata_uang`) |
| PATCH | `/account/currency` | Ganti mata uang tampilan (`IDR`/`USD`/`EUR`) |
| POST | `/account/avatar` | Upload avatar (multipart `avatar`, jpg/png maks 2 MB) |
| DELETE | `/account/avatar` | Hapus avatar → **204** |

```json
PATCH /api/account
{ "nama": "Nama Baru", "mata_uang": "IDR" }

200 OK  { "status": "profile-updated", "currency": "IDR" }
```

> Tidak ada field `password` di endpoint ini — ubah password lewat halaman web Breeze.

---

## 2. Dashboard

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/dashboard/summary` | Ringkasan bulan berjalan (tanpa wrapper `data`) |

```json
200 OK
{
  "periode": { "mulai": "2026-11-01", "selesai": "2026-11-30" },
  "total_saldo": 1475000,
  "total_pemasukan_bulan_ini": 1000000,
  "total_pengeluaran_bulan_ini": 25000,
  "pengeluaran_per_kategori": [ { "id_kategori": 2, "nama_kategori": "Makan", "total": 25000 } ],
  "target_tabungan": [ { "id_target": 1, "nama_target": "...", "progress": 1.33, "tercapai": false } ]
}
```

Baris **transfer tidak dihitung** sebagai pemasukan/pengeluaran bulanan.

---

## 3. Dompet

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/dompet?page=1` | Daftar dompet (**paginated**, `per_page` tetap 10) |
| POST | `/dompet` | Tambah dompet |
| GET | `/dompet/{id}` | Detail dompet + riwayat mutasi |
| PATCH | `/dompet/{id}/manage` | Ubah data dompet / edit saldo |
| PATCH | `/dompet/{id}/toggle-transfer` | Aktif/nonaktifkan sebagai penerima transfer |
| DELETE | `/dompet/{id}?force=1` | Hapus dompet |

**POST `/dompet`** — `nama_dompet` (wajib), `jenis` wajib `physical`/`digital`/`bank`
(default `digital`), `deskripsi`, `warna` (`brand`/`income`/`warn`/`expense`/`dark`/`#rrggbb`),
dan **salah satu** dari `saldo` atau `saldo_awal`.

```json
201 Created  { "message": "Dompet berhasil ditambahkan." }
```

**GET `/dompet`** membalas objek paginator Laravel — kunci `data[]`, `current_page`,
`per_page`, `total`, `links[]`, dll. **Tidak ada key `meta`** (bukan API Resource).

**PATCH `/dompet/{id}/manage`** mewajibkan `nama_dompet`, `jenis`, `warna`, `saldo`.

```json
200 OK  { "status": "wallet-updated" }
```

**DELETE `/dompet/{id}`** → `422` bila masih ada riwayat transfer:

```json
{ "message": "Wallet tidak dapat dihapus karena masih memiliki riwayat transfer.", "transfer_count": 3 }
```

Tambahkan `?force=1` untuk memaksa (hanya bila transfer tidak menyentuh dompet user lain).
Sukses → `200 { "status": "wallet-deleted" }`.

**Catatan toggle:** hanya 1 dompet per user yang bisa `terima_transfer = true`.

---

## 4. Transfer Antar User

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/transfer/recipients/search?query=budi` | Cari penerima (maks 8 hasil) |
| POST | `/transfer` | Kirim transfer |

> ### ⚠️ Parameter pencarian adalah `query`, BUKAN `q`
> Mengirim `?q=budi` menghasilkan `query = ''` dan membalas **`200 []`** secara diam-diam
> (bukan error). Selalu pakai `?query=`.

```json
GET /api/transfer/recipients/search?query=budi

200 OK
[
  {
    "id_user": 5, "nama": "Budi Santoso", "email": "budi@mail.com",
    "is_banned": false, "has_any_wallet": true, "has_wallet": true,
    "wallets": [ { "id": 3, "name": "Dompet Budi" } ],
    "id_dompet_tujuan": 3, "id_dompet_tujuan_default": 3
  }
]
```

`id_dompet_tujuan` = `null` bila penerima belum mengaktifkan penerimaan transfer.
`wallets` hanya berisi dompet dengan `terima_transfer = true`.

```json
POST /api/transfer
{
  "id_dompet_asal": 1,
  "id_dompet_tujuan": 3,
  "jumlah": 100000.00,
  "tanggal_transfer": "2026-11-05 10:00:00",
  "catatan": "bayar hutang"
}

201 Created  { "message": "Transfer berhasil dikirim." }
```

Kegagalan: `422` validasi / saldo kurang / **duplikat 30 detik** (bukan 429),
`403` bila dompet tujuan belum aktif menerima transfer.

---

## 5. Transaksi

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/transactions` | Riwayat transaksi (**filter + pagination**) |
| POST | `/transactions` | Tambah transaksi |
| PATCH | `/transactions/{id}` | Ubah transaksi |
| DELETE | `/transactions/{id}` | Hapus transaksi |

**GET `/transactions`** menerima query param:

| Param | Tipe | Keterangan |
|---|---|---|
| `page` | int | Halaman (default 1) |
| `per_page` | int | 1–100, default 15 (nilai di luar rentang di-clamp) |
| `q` | string | Cari `nama_transaksi` (LIKE) |
| `jenis` | `pemasukan`/`pengeluaran` | Salah nilai → **422** |
| `id_dompet` | int | Filter dompet milik user |
| `id_kategori` | int | Filter kategori milik user |
| `tanggal_mulai` | date | `tanggal >=` |
| `tanggal_selesai` | date | `tanggal <=`, harus `>= tanggal_mulai` |

```json
GET /api/transactions?page=1&per_page=2&jenis=pengeluaran

200 OK  (objek paginator, sama seperti /dompet)
{
  "current_page": 1, "per_page": 2, "total": 4, "last_page": 2,
  "data": [
    { "id": 2, "name": "Makan Siang", "type": "expense", "category": "Makan",
      "wallet": "Dompet Utama", "to": null, "amount": 25000,
      "date": "2026-11-05", "jenis": "pengeluaran", "icon": "utensils" }
  ]
}
```

`type` = `income` / `expense` / `transfer`. `icon` = `transfer` | ikon kategori | fallback.

```json
POST /api/transactions
{
  "id_dompet": 1,
  "id_kategori": 2,
  "nama_transaksi": "Makan siang",
  "jumlah": 25000,
  "jenis": "pengeluaran",
  "tanggal": "2026-11-05 12:00:00"
}

201 Created  { "message": "Transaksi berhasil dicatat." }
```

Semua 6 field wajib. `id_kategori` harus bertipe sama dengan `jenis`.
`PATCH` membalas **200** `{ "message": "Transaksi berhasil diubah." }`.
Transaksi hasil transfer → **405** saat diubah/dihapus.

---

## 6. Kategori Transaksi

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/categories` | Daftar kategori + jumlah transaksi |
| POST | `/categories` | Tambah kategori (`nama_kategori`, `jenis`, `icon`) |
| PATCH | `/categories/{id}` | Ubah kategori |
| DELETE | `/categories/{id}` | Hapus kategori (422 jika masih dipakai transaksi) |

```json
GET /api/categories

200 OK
[ { "id": 1, "name": "Gaji", "type": "income", "icon": "landmark", "count": 1 } ]
```

`POST` membalas **201** `{ "message": "...", "kategori": { ... } }` — satu-satunya
endpoint create yang mengembalikan objek.

---

## 7. Target Tabungan

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/saving` | Daftar target + `progress` & `achieved` |
| POST | `/saving` | Buat target |
| PATCH | `/saving/{id}` | Ubah target |
| DELETE | `/saving/{id}` | Hapus target |

```json
GET /api/saving

200 OK
[
  { "id": 1, "name": "Beli Laptop", "note": "Upgrade", "wallet": "Dompet Tabungan",
    "saved": 100000, "target": 20000000, "progress": 0.5, "achieved": false }
]
```

`POST` — `id_dompet`, `nama_target`, `nominal_target` wajib; `deskripsi`,
`initial_savings` opsional → **201** `{ "message": "Target tabungan berhasil dibuat." }`

`PATCH` — field sama dengan POST → **200** `{ "message": "Target tabungan berhasil diubah." }`

---

## 8. Notifikasi

| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/notifications?read=0` | Daftar notifikasi (terbaru di atas), filter `read` opsional |
| PATCH | `/notifications/{id}/read` | Tandai satu sudah dibaca |
| PATCH | `/notifications/read-all` | Tandai semua sudah dibaca |

```json
GET /api/notifications?read=0

200 OK
[
  { "id": 101, "type": "saving", "title": "Savings goal reached",
    "text": "Target Liburan telah tercapai.", "scope": "2 jam yang lalu", "read": false }
]
```

`type` = `saving` | `transfer` | `transaction`. `read` = `true`/`false`.
`?read=1` → hanya yang sudah dibaca, `?read=0` → hanya yang belum (untuk badge).
`PATCH .../read` dan `.../read-all` membalas **200** `{ "success": true }`.

---

## Format Error

| Kode | Arti | Penanganan |
|---|---|---|
| **401** | Token hilang/salah → `{"message":"Unauthenticated."}` | Login ulang |
| **403** | Dompet tujuan tidak aktif menerima transfer | Minta penerima mengaktifkan |
| **404** | Data tidak ada / bukan milik user | Cek ID |
| **405** | Transaksi transfer diubah/dihapus | Tidak bisa — memang immutable |
| **419** | **URL tanpa prefix `/api`** → CSRF token mismatch | Perbaiki base URL |
| **422** | Validasi / saldo kurang / duplikat 30 detik / rate limit login | Baca `errors` |
| **403** | (Apache XAMPP di port 8000) | Pakai port 8003 |

Format 422:

```json
{ "message": "...", "errors": { "jumlah": ["Saldo dompet tidak mencukupi..."] } }
```

> **Tidak ada respons 429.** Proteksi kiriman kembar (tap ganda) dan rate limit login
> semuanya memakai `ValidationException` → **422**.

## Daftar endpoint lengkap (31 route)

```
POST   /api/login                          (publik)
POST   /api/logout
GET    /api/user
GET    /api/account
PATCH  /api/account
PATCH  /api/account/currency
POST   /api/account/avatar
DELETE /api/account/avatar
GET    /api/dashboard/summary
GET    /api/dompet                          ?page=
POST   /api/dompet
GET    /api/dompet/{id}
PATCH  /api/dompet/{id}/manage
PATCH  /api/dompet/{id}/toggle-transfer
DELETE /api/dompet/{id}                     ?force=1
GET    /api/transfer/recipients/search      ?query=
POST   /api/transfer
GET    /api/transactions                    ?page=&per_page=&q=&jenis=&id_dompet=&id_kategori=&tanggal_mulai=&tanggal_selesai=
POST   /api/transactions
PATCH  /api/transactions/{id}
DELETE /api/transactions/{id}
GET    /api/categories
POST   /api/categories
PATCH  /api/categories/{id}
DELETE /api/categories/{id}
GET    /api/saving
POST   /api/saving
PATCH  /api/saving/{id}
DELETE /api/saving/{id}
GET    /api/notifications                   ?read=0|1
PATCH  /api/notifications/{id}/read
PATCH  /api/notifications/read-all
```
