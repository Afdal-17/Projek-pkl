# 📱 Dokumentasi Integrasi Mobile (Flutter) → Backend Laravel

> **Untuk:** Developer Mobile
> **Versi dokumen:** 2026-11-09
> **Jumlah endpoint API:** 33 route
> **Autentikasi:** Laravel Sanctum (Bearer Token)

---

## Daftar Isi

1. [Ringkasan & Base URL](#1-ringkasan--base-url)
2. [Header Wajib](#2-header-wajib)
3. [Autentikasi](#3-autentikasi)
4. [Troubleshooting 401](#4-troubleshooting-401--baca-ini-dulu)
5. [Daftar Seluruh Endpoint](#5-daftar-seluruh-endpoint)
6. [Detail Endpoint per Modul](#6-detail-endpoint-per-modul)
7. [Aturan Validasi Penting](#7-aturan-validasi-penting)
8. [Kode Contoh Flutter](#8-kode-contoh-flutter)
9. [Akun Demo](#9-akun-demo)
10. [Checklist Integrasi](#10-checklist-integrasi)

---

## 1. Ringkasan & Base URL

### Base URL

| Kondisi | Base URL |
|---|---|
| **Development lokal (server di mesin sendiri)** | `http://127.0.0.1:8003/api` |
| **Development lokal (dari emulator Android)** | `http://10.0.2.2:8003/api` |
| **Diakses dari HP/PC lain (LAN)** | `http://<IP-PEMILIK-PC>:8003/api` |
| **Jarak jauh via ngrok (REKOMENDASI)** | `https://sinuous-fame-jazz.ngrok-free.dev/api` |

> ⚠️ **Base URL HARUS diakhiri `/api`.** Tanpa `/api`, request jatuh ke route web dan mengembalikan HTML / redirect / error **419**.

> 💡 **Gunakan ngrok** untuk pengembangan. Domain ngrok statis (tidak berubah tiap restart) dan sudah HTTPS sehingga aman untuk Google Sign-In.

### Cara Menjalankan Backend

```bash
cd BE
php artisan serve --port=8003
```

> ⚠️ **JANGAN jalankan `npm run dev` di folder `BE`** selama mobile terhubung. Perintah itu membuat file `public/hot`, sehingga asset diarahkan ke Vite dev server loopback (`[::1]:5173`) dan halaman web jadi rusak. Gunakan `npm run build` bila perlu rebuild aset.

---

## 2. Header Wajib

### Untuk SEMUA request

```http
Accept: application/json
Content-Type: application/json
```

`Accept: application/json` **wajib**. Tanpa header ini, endpoint auth akan mengembalikan redirect HTML (bukan JSON), dan parsing response di Flutter gagal.

### Untuk endpoint yang dilindungi (semua kecuali `/login` dan `/auth/google`)

```http
Authorization: Bearer <token>
```

Format **harus persis**: kata `Bearer`, **satu spasi**, lalu token.

| ❌ Salah | ✅ Benar |
|---|---|
| `Token: <token>` | `Bearer <token>` |
| `token: <token>` | `Bearer <token>` |
| `X-Auth-Token: <token>` | `Bearer <token>` |
| `Bearer<token>` (tanpa spasi) | `Bearer <token>` |

---

## 3. Autentikasi & Registrasi

Ada **3 jalur autentikasi**, semuanya menghasilkan Sanctum token.

### 3a. Registrasi Akun Baru via Email (dengan OTP)

```http
POST /api/register
Content-Type: application/json
Accept: application/json

{
  "nama": "Afdal",
  "email": "afdal@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Sukses → 201 Created** (Kode OTP dikirim ke email)

```json
{
  "message": "Registrasi berhasil. Kode OTP telah dikirim ke email Anda.",
  "user_id": 12,
  "email": "afdal@example.com"
}
```

---

### 3b. Verifikasi Kode OTP Registrasi

```http
POST /api/register/verify-otp
Content-Type: application/json
Accept: application/json

{
  "user_id": 12,
  "kode": "481926"
}
```

**Sukses → 200 OK** (Akun aktif & langsung mendapat token login)

```json
{
  "message": "Verifikasi OTP berhasil. Akun Anda aktif.",
  "token": "26|abcdef...",
  "user": {
    "id_user": 12,
    "nama": "Afdal",
    "email": "afdal@example.com",
    "email_verified_at": "2026-11-09T05:00:00.000000Z"
  }
}
```

---

### 3c. Login Email & Password

```http
POST /api/login
Content-Type: application/json
Accept: application/json

{
  "email": "user@finance.test",
  "password": "password"
}
```

**Sukses → 200**

```json
{
  "message": "Login berhasil",
  "token": "25|IMGn73KN3y67ap1t4U1NqCFMKaPGG4lIvbIcw81Beecf5d5e",
  "user": {
    "id_user": 3,
    "nama": "User",
    "email": "user@finance.test",
    "role": "user",
    "mata_uang": "IDR",
    "...": "field lain"
  }
}
```

**Gagal → 422**

```json
{
  "message": "These credentials do not match our records.",
  "errors": { "email": ["These credentials do not match our records."] }
}
```

**Akun belum diverifikasi OTP / Admin → 422**

```json
{
  "message": "Akun Anda belum diverifikasi. Silakan masukkan kode OTP yang dikirim ke email Anda.",
  "errors": { "email": ["Akun Anda belum diverifikasi..."] }
}
```

**Akun dibanned → 422**

```json
{
  "message": "Akun Anda ditangguhkan: <alasan>",
  "errors": { "email": ["Akun Anda ditangguhkan: <alasan>"] }
}
```

---

### 3d. Login Google (untuk tombol *Sign in with Google* native)

```http
POST /api/auth/google
Content-Type: application/json
Accept: application/json

{
  "email": "user@gmail.com",
  "google_id": "1029384756...",
  "nama": "Nama User",
  "avatar_path": "https://lh3.googleusercontent.com/..."
}
```

**Field:**

| Field | Wajib | Keterangan |
|---|---|---|
| `email` | ✅ | Email Google user |
| `google_id` | ✅ | ID unik dari Google (sub) |
| `nama` | ❌ | Default `"Google User"` |
| `avatar_path` | ❌ | URL foto profil Google |

> ⚠️ `google_id` **wajib**. Jika tidak dikirim, API mengembalikan **422**.

**Sukses → 200** (user baru dibuat otomatis bila belum ada)

```json
{
  "message": "Login Google berhasil",
  "token": "20|Lz1kq3MG7ZFZPXygyeEM5OvrVTh1V9lcE6jOpZ793cef5c10",
  "user": {
    "id_user": 8,
    "nama": "Nama User",
    "email": "user@gmail.com",
    "google_id": "1029384756...",
    "role": "user",
    "status": true,
    "email_verified_at": "2026-11-09T03:44:06.000000Z",
    "mata_uang": "IDR"
  }
}
```

**Akun dibanned → 403**

```json
{ "message": "Akun Anda ditangguhkan: <alasan>" }
```

**Akun non-aktif → 403**

```json
{ "message": "Akun Anda sedang tidak aktif. Silakan hubungi admin." }
```

> 🔎 **Perilaku:** user ditemukan dulu berdasarkan `google_id`, lalu fallback ke `email`. Bila keduanya tidak ada, user baru dibuat dengan `role=user`, `status=true`, dan `email_verified_at=now()`.

---

### 3c. Simpan & Pakai Token

```
Login sukses
   │
   ├──► simpan token (SharedPreferences / flutter_secure_storage)
   │
   └──► setiap request berikutnya:
          Authorization: Bearer <token>
   │
   ├── 200 / 201 → sukses
   ├── 401       → token hilang/kedaluwarsa → HAPUS token → paksa login ulang
   ├── 403       → akun dibanned / tidak aktif → tampilkan pesan
   └── 422       → validasi gagal → tampilkan errors
```

### 3d. Logout

```http
POST /api/logout
Authorization: Bearer <token>
Accept: application/json
```

**→ 200**

```json
{ "message": "Logout berhasil" }
```

> ⚠️ Token **langsung dihapus permanen** dari database setelah logout. Token lama tidak bisa dipakai lagi.

---

## 4. Troubleshooting 401 — baca ini dulu

`401 Unauthorized` **bukan bug backend**. Artinya request **tidak membawa token yang valid**.

### Urutan cek

**1. Apakah endpoint-nya memang dipanggil tanpa token?**

`POST /api/dompet` tanpa header `Authorization` → memang sengaja dibalas **401** oleh middleware `auth:sanctum`. Itu perilaku yang benar.

**2. Apakah token sudah diambil dari hasil login?**

Pastikan `token` dari response login benar-benar **disimpan**, lalu **dibaca lagi** saat request berikutnya. Jika `token` masih `null` di state, header otomatis tidak terkirim.

**3. Apakah format header benar?**

```dart
'Authorization': 'Bearer $token'   // ✅
```

**4. Apakah token sudah kadaluarsa / dicabut?**

Token mati bila:
- memanggil `POST /api/logout`
- login ulang di jalur web (memanggil `tokens()->delete()`)
- backend menjalankan `php artisan migrate:fresh`

**Solusi:** login ulang untuk mendapat token baru.

**5. Apakah Base URL benar?**

Harus berakhiran `/api`. Kalau salah, request bisa jatuh ke server lain (mis. Apache XAMPP port 8000) yang tidak punya route ini.

### Cara membuktikan dari sisi mobile

Buka tab **Network** (Chrome DevTools remote / Charles / Http Inspector) saat menekan tombol simpan, lalu periksa:

| Baris | Lihat | Arti |
|---|---|---|
| `auth/google` | status | **200** → ada field `token` di Response |
| `dompet` | **Request Headers** | Ada `Authorization: Bearer ...`? |
| — | kalau **tidak ada** header | 🔴 Bug di FE, token belum terkirim |
| — | header **ada** tapi 401 | 🟡 Token stale → login ulang |

---

## 5. Daftar Seluruh Endpoint

### 🔓 Publik (tanpa token)

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/login` | Login email + password |
| POST | `/api/auth/google` | Login / register via Google |

### 🔒 Terproteksi (wajib `Authorization: Bearer`)

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/user` | Profil user login |
| POST | `/api/logout` | Cabut token |
| GET | `/api/account` | Data profil + avatar |
| PATCH | `/api/account` | Update profil |
| PATCH | `/api/account/currency` | Ganti mata uang |
| POST | `/api/account/avatar` | Upload avatar |
| DELETE | `/api/account/avatar` | Hapus avatar |
| GET | `/api/dashboard/summary` | Ringkasan dashboard |
| GET | `/api/dompet` | Daftar dompet (paginasi) |
| POST | `/api/dompet` | Tambah dompet |
| GET | `/api/dompet/{id}` | Detail dompet |
| PATCH | `/api/dompet/{id}/manage` | Ubah dompet |
| DELETE | `/api/dompet/{id}` | Hapus dompet |
| PATCH | `/api/dompet/{id}/toggle-transfer` | Izin terima transfer |
| GET | `/api/transactions` | Daftar transaksi (filter + paginasi) |
| POST | `/api/transactions` | Tambah transaksi |
| PATCH | `/api/transactions/{id}` | Ubah transaksi |
| DELETE | `/api/transactions/{id}` | Hapus transaksi |
| GET | `/api/categories` | Daftar kategori |
| POST | `/api/categories` | Tambah kategori |
| PATCH | `/api/categories/{id}` | Ubah kategori |
| DELETE | `/api/categories/{id}` | Hapus kategori |
| GET | `/api/saving` | Daftar target tabungan |
| POST | `/api/saving` | Tambah target |
| PATCH | `/api/saving/{id}` | Ubah target |
| DELETE | `/api/saving/{id}` | Hapus target |
| GET | `/api/notifications` | Daftar notifikasi |
| PATCH | `/api/notifications/{id}/read` | Tandai 1 sudah dibaca |
| PATCH | `/api/notifications/read-all` | Tandai semua sudah dibaca |
| GET | `/api/transfer/recipients/search` | Cari penerima transfer |
| POST | `/api/transfer` | Eksekusi transfer |

---

## 6. Detail Endpoint per Modul

### 6.1 Profil User — `GET /api/user`

```json
{
  "id_user": 3,
  "nama": "User",
  "email": "user@finance.test",
  "google_id": null,
  "role": "user",
  "status": true,
  "email_verified_at": "2026-11-09T03:57:11.000000Z",
  "nomor_telepon": null,
  "lokasi": null,
  "avatar_path": null,
  "mata_uang": "IDR",
  "banned_at": null,
  "ban_reason": null
}
```

---

### 6.2 Dashboard — `GET /api/dashboard/summary`

```json
{
  "periode": { "mulai": "2026-11-01", "selesai": "2026-11-30" },
  "total_saldo": 1475000,
  "total_pemasukan_bulan_ini": 1000000,
  "total_pengeluaran_bulan_ini": 25000,
  "pengeluaran_per_kategori": [
    { "id_kategori": 2, "nama_kategori": "Makan", "total": 25000 }
  ],
  "target_tabungan": [
    {
      "id_target": 1,
      "nama_target": "Beli Laptop Baru",
      "nama_dompet": "Dompet Tabungan",
      "saldo_dompet": 100000,
      "jumlah_terkumpul": 100000,
      "nominal_target": 20000000,
      "progress": 0.5,
      "tercapai": false
    }
  ]
}
```

> `progress` berupa **desimal 0–1** (0.5 = 50%). Kalau ingin persen, kalikan 100.

---

### 6.3 Dompet

#### `GET /api/dompet` — paginasi

```json
{
  "current_page": 1,
  "per_page": 10,
  "total": 2,
  "data": [
    {
      "id_dompet": 3,
      "id_user": 3,
      "nama_dompet": "Dompet Utama",
      "deskripsi": "Dompet untuk kebutuhan sehari-hari",
      "saldo_awal": "500000.00",
      "saldo": "1375000.00",
      "jenis": "digital",
      "warna": "brand",
      "terima_transfer": true
    }
  ],
  "links": ["..."],
  "next_page_url": null
}
```

> 🔎 `saldo` bertipe **string** (`"1375000.00"`) — konversi ke `double` di Dart.

#### `POST /api/dompet` — tambah

```json
{
  "nama_dompet": "Dompet Baru",
  "deskripsi": "Opsional",
  "jenis": "digital",
  "warna": "brand",
  "saldo_awal": 150000,
  "terima_transfer": true
}
```

**→ 201**

```json
{ "message": "Dompet berhasil ditambahkan." }
```

**Field:**

| Field | Wajib | Nilai yang diperbolehkan |
|---|---|---|
| `nama_dompet` | ✅ | string, maks 100 |
| `jenis` | ✅ | `physical` \| `digital` \| `bank` |
| `warna` | ❌ | `brand` \| `income` \| `warn` \| `expense` \| `dark` \| `#RRGGBB` |
| `deskripsi` | ❌ | string bebas |
| `saldo_awal` | ❌ | angka ≥ 0 |
| `saldo` | ❌ | angka ≥ 0 |
| `terima_transfer` | ❌ | boolean |

> ⚠️ **Hanya boleh kirim SATU dari `saldo` ATAU `saldo_awal`.** Lihat [bagian 7](#7-aturan-validasi-penting).

#### `PATCH /api/dompet/{id}/manage` — ubah
#### `DELETE /api/dompet/{id}` — hapus
#### `PATCH /api/dompet/{id}/toggle-transfer` — izin terima transfer

---

### 6.4 Transaksi

#### `GET /api/transactions` — filter + paginasi

**Query params (semua opsional):**

| Param | Contoh | Keterangan |
|---|---|---|
| `page` | `1` | Nomor halaman |
| `per_page` | `15` | 1–100, default 15 |
| `q` | `gaji` | Cari `nama_transaksi` |
| `jenis` | `pemasukan` / `pengeluaran` | Filter jenis |
| `id_dompet` | `3` | Filter dompet |
| `id_kategori` | `1` | Filter kategori |
| `tanggal_mulai` | `2026-11-01` | Dari tanggal |
| `tanggal_selesai` | `2026-11-30` | Sampai tanggal |

**Contoh:** `GET /api/transactions?page=1&per_page=10&jenis=pengeluaran&tanggal_mulai=2026-11-01`

```json
{
  "current_page": 1,
  "per_page": 1,
  "total": 4,
  "data": [
    {
      "id": 1,
      "name": "Gaji Bulan Ini",
      "type": "income",
      "category": "Gaji",
      "wallet": "Dompet Utama",
      "to": null,
      "amount": 1000000,
      "date": "2026-11-05",
      "jenis": "pemasukan",
      "icon": "landmark"
    }
  ]
}
```

#### `POST /api/transactions` — tambah

```json
{
  "id_dompet": 3,
  "id_kategori": 1,
  "nama_transaksi": "Beli Bakso",
  "jumlah": 25000,
  "jenis": "pengeluaran",
  "tanggal": "2026-11-09"
}
```

#### `PATCH /api/transactions/{id}` / `DELETE /api/transactions/{id}`

---

### 6.5 Kategori

#### `GET /api/categories`

```json
[
  { "id": 1, "name": "Gaji",          "type": "income",  "icon": "landmark", "count": 1 },
  { "id": 2, "name": "Makan",         "type": "expense", "icon": "landmark", "count": 1 },
  { "id": 3, "name": "Transportasi",  "type": "expense", "icon": "landmark", "count": 0 }
]
```

> 🔎 `type` memakai **English** (`income`/`expense`), sedangkan field transaksi `jenis` memakai **Bahasa** (`pemasukan`/`pengeluaran`). Saat membuat transaksi, cocokkan:
> - `type: "income"` ↔ `jenis: "pemasukan"`
> - `type: "expense"` ↔ `jenis: "pengeluaran"`

> `count` = jumlah transaksi pada kategori tersebut.

#### `POST /api/categories`

```json
{ "nama_kategori": "Hiburan", "jenis": "pengeluaran", "icon": "landmark" }
```

**Field:**

| Field | Wajib | Nilai |
|---|---|---|
| `nama_kategori` | ✅ | string, maks 100 |
| `jenis` | ✅ | `pemasukan` \| `pengeluaran` |
| `icon` | ❌ | string, maks 30 |

> ⚠️ Saat **membuat kategori** pakai `nama_kategori` + `jenis` Bahasa (`pemasukan`/`pengeluaran`).
> Saat **membaca** (`GET`), field-nya berubah jadi `name` + `type` Inggris (`income`/`expense`). Jangan tertukar.

---

### 6.6 Target Tabungan

#### `GET /api/saving`

```json
[
  {
    "id": 1,
    "name": "Beli Laptop Baru",
    "note": "Upgrade",
    "wallet": "Dompet Tabungan",
    "saved": 100000,
    "target": 20000000,
    "progress": 0.5,
    "achieved": false
  }
]
```

- `progress` → desimal 0–1
- `achieved` → `true` bila target tercapai

#### `POST /api/saving`

```json
{
  "nama_target": "Beli Laptop Baru",
  "nominal_target": 20000000,
  "id_dompet": 4,
  "deskripsi": "Upgrade"
}
```

**Field:**

| Field | Wajib | Keterangan |
|---|---|---|
| `nama_target` | ✅ | string, maks 150 |
| `nominal_target` | ✅ | angka > 0 |
| `id_dompet` | ✅ | harus milik user yang login |
| `deskripsi` | ❌ | string, maks 1000 |
| `initial_savings` | ❌ | angka ≥ 0 — awal tabungan terkumpul |

> 🔎 Field **request** `deskripsi` → dikembalikan sebagai **`note`** di response (bila kosong, otomatis diisi nama dompet).
> Field **request** `nama_target` → dikembalikan sebagai **`name`**.

#### ⚠️ Gotcha: `initial_savings` default = saldo dompet

Kalau `initial_savings` **tidak dikirim**, backend mengisinya **otomatis dengan saldo dompet saat itu**:

```php
// TargetTabunganController::store()
$initialSavings = $data['initial_savings'] ?? $wallet->saldo;
```

Nilai itu lalu divalidasi:

```
initial_savings  ≤  min(nominal_target, saldo_dompet)
```

Jika melanggar → **422** `"Initial savings tidak boleh melebihi saldo wallet atau target."`

**Bukti pengujian** (dompet `id=3`, saldo **1.375.000**):

| Payload | Hasil |
|---|---|
| tanpa `initial_savings`, `nominal_target: 500000` | ❌ **422** (500rb < 1.375rb) |
| tanpa `initial_savings`, `nominal_target: 5000000` | ✅ **201** (5jt > 1.375rb) |
| `initial_savings: 0`, `nominal_target: 500000` | ✅ **201** |
| `initial_savings: 1000000`, `nominal_target: 500000` | ❌ **422** (1jt > 500rb) |

**Rekomendasi untuk mobile — kirim `initial_savings` secara eksplisit:**

```json
{
  "nama_target": "Beli Laptop Baru",
  "nominal_target": 500000,
  "id_dompet": 3,
  "deskripsi": "Upgrade",
  "initial_savings": 0
}
```

Dengan begitu perilakunya deterministik dan tidak bergantung pada saldo dompet yang bisa berubah sewaktu-waktu.

#### `PATCH /api/saving/{id}` — **update target (sudah tersedia)**
#### `DELETE /api/saving/{id}`

---

### 6.7 Notifikasi

#### `GET /api/notifications`

**Filter opsional:** `?read=0` (belum dibaca) atau `?read=1` (sudah dibaca)

| Query | Keterangan |
|---|---|
| `/api/notifications` | Semua |
| `/api/notifications?read=0` | Belum dibaca saja |
| `/api/notifications?read=1` | Sudah dibaca saja |

```json
[
  {
    "id": 12,
    "type": "target",
    "title": "Savings goal reached",
    "text": "Target tabungan Anda telah tercapai",
    "scope": "2 hours ago",
    "read": false
  }
]
```

> 🔎 Kosong = `[]` (array, bukan object).

#### `PATCH /api/notifications/{id}/read`

```json
{ "success": true }
```

#### `PATCH /api/notifications/read-all`

```json
{ "success": true }
```

---

### 6.8 Transfer

#### `GET /api/transfer/recipients/search?query=<kata>`

> ⚠️ Param namanya **`query`**, **bukan** `q`.

```json
[
  {
    "id_user": 5,
    "nama": "Rofi_ Ngeditz",
    "email": "rofingeditz@gmail.com",
    "is_banned": false,
    "has_any_wallet": false,
    "has_wallet": false,
    "wallets": [],
    "id_dompet_tujuan": null,
    "id_dompet_tujuan_default": null
  }
]
```

- `is_banned: true` → **tidak boleh** dipilih sebagai penerima
- `has_any_wallet: false` → user belum punya dompet, tidak bisa menerima transfer

#### `POST /api/transfer`

```json
{
  "id_dompet_asal": 3,
  "id_dompet_tujuan": 4,
  "jumlah": 50000,
  "catatan": "Bayar makan",
  "tanggal_transfer": "2026-11-09"
}
```

---

### 6.9 Profil & Akun

| Method | Endpoint | Body |
|---|---|---|
| GET | `/api/account` | — → `{ "user": {...}, "avatar_url": null }` |
| PATCH | `/api/account` | `{ "nama": "...", "nomor_telepon": "...", "lokasi": "..." }` |
| PATCH | `/api/account/currency` | `{ "mata_uang": "IDR" }` |
| POST | `/api/account/avatar` | `multipart/form-data`, field **`avatar`** |
| DELETE | `/api/account/avatar` | — |

**Mata uang yang didukung:** `IDR`, `USD`, `EUR` (cek `ProfileController::switchCurrency`).

> Upload avatar pakai **multipart**, BUKAN JSON:
> ```dart
> http.MultipartRequest('POST', Uri.parse('$base/account/avatar'))
>   ..headers['Authorization'] = 'Bearer $token'
>   ..headers['Accept'] = 'application/json'
>   ..files.add(await http.MultipartFile.fromPath('avatar', path));
> ```

---

## 7. Aturan Validasi Penting

### 🔴 `saldo` vs `saldo_awal` — hanya boleh SATU

Pada `StoreDompetRequest`:

```php
'saldo'      => ['nullable', 'numeric', ..., 'prohibits:saldo_awal'],
'saldo_awal' => ['nullable', 'numeric', ..., 'prohibits:saldo'],
```

| Kirim | Hasil |
|---|---|
| `saldo_awal` saja | ✅ **201** |
| `saldo` saja | ✅ 201 |
| keduanya | ❌ 422 |
| tidak keduanya | ✅ 201 (default 0) |

> ✅ **Pendekatan mobile saat ini sudah benar** — cukup kirim `saldo_awal` saja.

### 🔴 `initial_savings` pada `/api/saving`

Jika **tidak dikirim**, nilai default-nya **bukan 0**, melainkan **saldo dompet**. Wajib berlaku:

```
initial_savings ≤ min(nominal_target, saldo_dompet)
```

Kalau tidak → **422** `"Initial savings tidak boleh melebihi saldo wallet atau target."`

**Solusi:** kirim `initial_savings` secara eksplisit (mis. `0`). Detail & tabel uji ada di [bagian 6.6](#66-target-tabungan).

### 🟡 Dua nama field berbeda antara GET dan POST

| Modul | Saat **GET** (response) | Saat **POST** (request) |
|---|---|---|
| Kategori | `name`, `type` (`income`/`expense`) | `nama_kategori`, `jenis` (`pemasukan`/`pengeluaran`) |
| Target Tabungan | `name`, `note`, `saved`, `target`, `progress` | `nama_target`, `deskripsi`, `nominal_target` |

Jangan tertukar — memakai nama GET saat POST → **422 field required**.

### Format `Content-Type`

- Endpoint biasa → `application/json`
- Upload avatar → `multipart/form-data`

### Tipe data yang sering bikin salah

| Field | Tipe di response | Catatan |
|---|---|---|
| `saldo`, `saldo_awal` | **string** | `"1375000.00"` → parse ke double |
| `amount`, `saved`, `target` | number | langsung |
| `progress` | number | desimal 0–1 |
| `tanggal` / `date` | string | format `YYYY-MM-DD` |
| `email_verified_at` | string / null | ISO-8601 |

---

## 8. Kode Contoh Flutter

### 8.1 Konfigurasi dasar

```dart
// lib/core/api_config.dart
class ApiConfig {
  static const String base =
      'https://sinuous-fame-jazz.ngrok-free.dev/api';   // ngrok
  // static const String base = 'http://127.0.0.1:8003/api'; // emulator pakai 10.0.2.2
}
```

### 8.2 HTTP client dengan token otomatis

```dart
// lib/core/api_client.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'api_config.dart';

class ApiClient {
  Future<Map<String, String>> _headers({bool jsonBody = false}) async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');
    return {
      'Accept': 'application/json',
      if (jsonBody) 'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Future<http.Response> get(String path) =>
      http.get(Uri.parse('${ApiConfig.base}$path'), headers: await _headers());

  Future<http.Response> post(String path, Map<String, dynamic> body) =>
      http.post(Uri.parse('${ApiConfig.base}$path'),
          headers: await _headers(jsonBody: true),
          body: jsonEncode(body));

  Future<http.Response> patch(String path, Map<String, dynamic> body) =>
      http.patch(Uri.parse('${ApiConfig.base}$path'),
          headers: await _headers(jsonBody: true),
          body: jsonEncode(body));

  Future<http.Response> delete(String path) =>
      http.delete(Uri.parse('${ApiConfig.base}$path'), headers: await _headers());
}
```

### 8.3 Registrasi & Verifikasi OTP Email

```dart
// 1. Request Register
Future<int?> registerEmail({
  required String nama,
  required String email,
  required String password,
  required String passwordConfirmation,
}) async {
  final res = await api.post('/register', {
    'nama': nama,
    'email': email,
    'password': password,
    'password_confirmation': passwordConfirmation,
  });

  if (res.statusCode == 201) {
    final body = jsonDecode(res.body);
    return body['user_id'] as int; // Simpan user_id untuk verifikasi OTP
  } else {
    // 422 error validasi
    final errors = jsonDecode(res.body)['errors'];
    throw Exception(errors.toString());
  }
}

// 2. Verifikasi OTP & Ambil Token
Future<String?> verifyOtp({
  required int userId,
  required String kode,
}) async {
  final res = await api.post('/register/verify-otp', {
    'user_id': userId,
    'kode': kode,
  });

  if (res.statusCode == 200) {
    final body = jsonDecode(res.body);
    final token = body['token'] as String;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', token); // Akun langsung login

    return token;
  } else {
    final msg = jsonDecode(res.body)['message'];
    throw Exception(msg);
  }
}
```

### 8.4 Login Google

```dart
Future<void> loginWithGoogle({
  required String email,
  required String googleId,
  String? nama,
  String? avatarPath,
}) async {
  final res = await api.post('/auth/google', {
    'email': email,
    'google_id': googleId,
    if (nama != null) 'nama': nama,
    if (avatarPath != null) 'avatar_path': avatarPath,
  });

  if (res.statusCode == 200) {
    final body = jsonDecode(res.body);
    final token = body['token'] as String;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', token);      // ← SIMPAN TOKEN
    await prefs.setString('user', jsonEncode(body['user']));

    // lanjut ke Home
  } else if (res.statusCode == 403) {
    // akun dibanned / tidak aktif → tampilkan body['message']
  } else {
    // 422 → jsonDecode(res.body)['errors']
  }
}
```

### 8.4 Tambah Dompet (kasus yang sempat 401)

```dart
Future<void> addWallet(String nama, double saldoAwal) async {
  final res = await api.post('/dompet', {
    'nama_dompet': nama,
    'jenis': 'digital',
    'warna': 'brand',
    'saldo_awal': saldoAwal,     // ← hanya ini, JANGAN kirim 'saldo'
  });

  switch (res.statusCode) {
    case 201:                     // sukses
      break;
    case 401:                     // token hilang → paksa login ulang
      await SharedPreferences.getInstance()
          .then((p) => p.remove('token'));
      goToLogin();
      break;
    case 422:                     // validasi
      final errors = jsonDecode(res.body)['errors'];
      showMessage(errors.toString());
      break;
    default:
      showMessage(jsonDecode(res.body)['message'] ?? 'Gagal');
  }
}
```

### 8.5 Interceptor penanganan 401 (pakai `dio`)

```dart
// tambahkan SEBELUM request dikirim
dio.interceptors.add(InterceptorsWrapper(
  onRequest: (options, handler) async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    options.headers['Accept'] = 'application/json';
    handler.next(options);
  },
  onError: (e, handler) async {
    if (e.response?.statusCode == 401) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('token');
      // arahkan ke layar login
    }
    handler.next(e);
  },
));
```

---

## 9. Akun Demo

| Email | Password | Role | Verified | Status |
|---|---|---|---|---|
| `user@finance.test` | `password` | user | ✅ YA | ✅ Siap dipakai |
| `admin@finance.test` | `password` | admin | ❌ | Lewati cek verifikasi (role admin) → tetap bisa login |
| `mobiletest@finance.test` | *(berbeda)* | user | ✅ YA | ⚠️ Password bukan `password` |

> ✅ **`user@finance.test` / `password`** — akun utama untuk uji coba mobile.

**Uji cepat dengan cURL:**

```bash
# Login
curl -X POST https://sinuous-fame-jazz.ngrok-free.dev/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@finance.test","password":"password"}'

# Pakai token (ganti <TOKEN>)
curl https://sinuous-fame-jazz.ngrok-free.dev/api/dompet \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

---

## 10. Checklist Integrasi

Sebelum laporan "sudah terhubung", pastikan semua tercentang:

### Konfigurasi
- [ ] Base URL diakhiri `/api`
- [ ] Header `Accept: application/json` pada semua request
- [ ] Header `Content-Type: application/json` pada request body
- [ ] Google OAuth memakai domain **ngrok HTTPS**, bukan IP mentah

### Autentikasi
- [ ] `POST /api/auth/google` → **200** dan ada field `token`
- [ ] Token **disimpan** ke penyimpanan persisten
- [ ] Token **dibaca ulang** saat request berikutnya
- [ ] Header `Authorization: Bearer <token>` benar-benar terkirim
- [ ] `POST /api/logout` menghapus token dari penyimpanan lokal

### Fitur
- [ ] `GET /api/user` → 200
- [ ] `GET /api/dompet` → 200 (array `data`)
- [ ] `POST /api/dompet` → **201**, data masuk tabel `dompet`
- [ ] `GET /api/transactions` → 200, filter & pagination berfungsi
- [ ] `POST /api/saving` → 201, `PATCH /api/saving/{id}` → 200
- [ ] `GET /api/notifications?read=0` → 200
- [ ] `POST /api/transfer` → sukses
- [ ] `GET /api/transfer/recipients/search?query=<nama>` → 200

### Penanganan Error
- [ ] `401` → hapus token, paksa login ulang
- [ ] `403` → tampilkan pesan banned / non-aktif
- [ ] `422` → tampilkan isi `errors`
- [ ] `500` → laporkan ke backend (kemungkinan bug)

---

## 📌 Catatan Perbaikan Backend (riwayat)

| Tanggal | Masalah | Status |
|---|---|---|
| 2026-11-09 | `POST /api/login` mengembalikan **500** `Class "App\Http\Controllers\Auth\ValidationException" not found` — import `Illuminate\Validation\ValidationException` hilang di `AuthenticatedSessionController` | ✅ **Diperbaiki** |
| 2026-11-09 | Akun `user@finance.test` belum diverifikasi sehingga diblokir login | ✅ **Diverifikasi** |
| Sebelumnya | Route `POST /api/auth/google` belum ada (404) | ✅ Sudah ada & teruji (200 + token) |
| Sebelumnya | Validasi `prohibits` `saldo`/`saldo_awal` | ✅ Sesuai, mobile sudah benar |

**Hasil regresi:** `php artisan test` → **120 passed (608 assertions)**.

---

## ❓ Kontak Troubleshooting Cepat

| Gejala | Penyebab paling mungkin | Tindakan |
|---|---|---|
| **401** di semua endpoint | Token tidak terkirim / stale | Login ulang, cek header `Authorization` |
| **419** | Base URL tanpa `/api` atau cookie session | Tambah `/api`, pakai token bukan session |
| **404** di `/api/auth/google` | Base URL salah | Pastikan berakhiran `/api` |
| **422** `google_id` | Field tidak dikirim | Sertakan `google_id` |
| **422** `prohibits` | Kirim `saldo` **dan** `saldo_awal` | Kirim salah satu saja |
| **422** `initial_savings` | Default = saldo dompet, melebihi target | Kirim `initial_savings` eksplisit |
| **422** field `nama kategori` / `id dompet` required | Pakai nama field GET saat POST | Cek [bagian 7 — dua nama field](#7-aturan-validasi-penting) |
| **403** | Akun dibanned / non-aktif | Baca `message` dari response |
| **500** | Bug backend | Laporkan ke backend developer |
| **HTML / redirect** | Header `Accept: application/json` hilang | Tambahkan header |
| **CORS gagal (web)** | Preflight | Pastikan `Accept` & `Authorization` diizinkan |
