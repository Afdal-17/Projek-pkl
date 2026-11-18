# Dokumentasi Konfigurasi Frontend (Mobile & Web)

Panduan konfigurasi koneksi dan aturan integrasi frontend (Flutter & Vue.js) dengan backend Laravel Finance App.

---

## 1. Konfigurasi Base URL & Header Wajib

Setiap request dari frontend **wajib** mengarah ke base URL yang benar dan menyertakan header standar.

### 🌐 Base URL
* **Production / Remote (via ngrok HTTPS):** 
  `https://sinuous-fame-jazz.ngrok-free.dev/api`
* **Local Development (Backend di port 8003):** 
  `http://127.0.0.1:8003/api`

> ⚠️ **Catatan Penting untuk ngrok:** Jangan gunakan IP lokal (seperti `192.168.x.x` atau `10.x.x.x`) saat menghubungkan Google OAuth atau membagikan akses ke tim, karena Google OAuth dan kebijakan browser modern menolak *mixed content* / IP HTTP mentah. Selalu gunakan domain statis ngrok `https://sinuous-fame-jazz.ngrok-free.dev`.

### 📋 Header Wajib di Setiap Request
```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <sanctum_token>
```
*(Header `Authorization` hanya dikosongkan untuk endpoint publik seperti `/login`, `/register`, `/register/verify-otp`, `/register/resend-otp`, dan `/auth/google`).*

---

## 2. Aturan Penting Konversi Mata Currency (Penting untuk Tim Mobile/Web)

Backend Finance App **menyimpan seluruh data keuangan dalam USD** sebagai *base currency*. Default akun baru adalah USD; preferensi tampilan akun yang sudah ada tetap mengikuti pengaturan masing-masing.

1. **Backend Menangani Konversi Otomatis:**
   * Nilai uang yang dikirim ke endpoint penulisan dinyatakan dalam preferensi akun (`mata_uang` di tabel `user`, pilihannya: `IDR`, `USD`, `EUR`); backend mengonversinya ke USD sebelum menyimpan.
   * Field nominal API yang sudah ada tetap berisi angka dasar USD demi kompatibilitas. Response keuangan menambahkan objek seperti `amount_money`, `saldo_money`, `target_money`, atau `*_money`, dengan `base_amount`, `base_currency`, `display_amount`, `display_currency`, dan `rate`.
   * Gunakan `*_money.display_amount` dan `display_currency` untuk tampilan sesuai preferensi akun. Nilai display dibulatkan ke digit minor mata uang (IDR 0 desimal; USD/EUR 2 desimal).
2. **Aturan untuk Frontend (Flutter/Vue):**
   * Nilai dasar uang tersimpan dalam USD dengan presisi hingga 8 angka desimal.
   * Jangan menganggap field nominal lama sebagai mata uang tampilan. Gunakan pasangan `*_money` jika tersedia; jangan hardcode kurs.
   * `GET /api/currency/rates` tetap tersedia untuk informasi kurs (unit mata uang per 1 USD); response `*_money.rate` menunjukkan kurs yang digunakan untuk konversi item tersebut.
   * Format angka hasil konversi menggunakan kode mata uang yang dipilih pengguna.
   * Untuk mengganti mata uang akun, frontend cukup memanggil:
     `PATCH /api/account/currency` dengan body `{ "mata_uang": "USD" }` (pilihan: `IDR`, `USD`, `EUR`).

---

## 3. Daftar Endpoint Autentikasi Frontend

| Method | Endpoint | Deskripsi | Auth (Token) |
|---|---|---|---|
| `POST` | `/api/register` | Mendaftarkan akun baru via email (mengirim OTP) | Publik |
| `POST` | `/api/register/verify-otp` | Verifikasi 6 digit kode OTP (mengaktifkan akun & mengembalikan token) | Publik |
| `POST` | `/api/register/resend-otp` | Kirim ulang OTP registrasi (`user_id`) | Publik |
| `POST` | `/api/login` | Login dengan email/phone + password | Publik |
| `POST` | `/api/forgot-password` | Kirim link/token reset password ke email | Publik |
| `POST` | `/api/reset-password` | Ubah password baru menggunakan token reset | Publik |
| `POST` | `/api/forgot-password/phone` | Minta OTP pemulihan lewat WhatsApp | Publik |
| `POST` | `/api/forgot-password/phone/verify` | Verifikasi OTP, menerima `reset_token` | Publik |
| `POST` | `/api/reset-password/phone` | Ubah password menggunakan `reset_token` | Publik |
| `PUT` | `/api/password` | Mengganti password akun (wajib login) | **Wajib Bearer Token** |
| `POST` | `/api/logout` | Logout (menghapus token Sanctum di database) | **Wajib Bearer Token** |

---

Untuk registrasi dengan `phone`, body `/api/register` menerima field `phone`
dan dapat menerima `email` tambahan (opsional). Jika `phone` dikirim, OTP
verifikasi dikirim lewat WhatsApp meskipun email juga disertakan. Pastikan
nomor terdaftar pada WhatsApp dan Fonnte backend aktif. Verifikasi tetap
menggunakan `POST /api/register/verify-otp` dengan `user_id` dan `kode`.
Registrasi belum menghasilkan token sampai verifikasi sukses. Pesan OTP WhatsApp
dikirim dalam bahasa Inggris. Jika provider gagal atau Fonnte belum aktif untuk
registrasi telepon, API mengembalikan `503` dan akun tidak dibuat. OTP berlaku
10 menit, maksimal 5 percobaan. Verifikasi dibatasi 10 request/menit per IP;
endpoint kirim ulang dibatasi 10 request/menit per IP dan 6 kali/15 menit per akun.
Kirim ulang dengan `POST /api/register/resend-otp`, body
`{ "user_id": 12 }`; respons `503` berarti kode tidak berhasil dikirim.

### Pemulihan password nomor telepon (API)

Klien meminta OTP WhatsApp, memverifikasinya, lalu mengirim password baru.
Nomor Indonesia didukung dalam format `0812...`, `62812...`, atau `+62812...`.
Semua request ini publik dan memerlukan header `Accept: application/json` serta
`Content-Type: application/json`; jangan kirim token Sanctum.

1. `POST /api/forgot-password/phone` — body `{ "phone": "081234567890" }`.
   Respons `200` generik selalu sama, termasuk untuk nomor tidak terdaftar,
   akun Google, akun belum terverifikasi, atau pengiriman yang gagal. Karena itu,
   `200` bukan konfirmasi bahwa OTP benar-benar terkirim.
2. `POST /api/forgot-password/phone/verify` — body
   `{ "phone": "081234567890", "code": "481926" }`. OTP berlaku 10 menit,
   maksimal 5 percobaan. Sukses `200` mengembalikan `reset_token`; simpan hanya
   sementara dan jangan tulis ke log/penyimpanan persisten.
3. `POST /api/reset-password/phone` — body
   `{ "phone": "081234567890", "reset_token": "...", "password": "...", "password_confirmation": "..." }`.
   Token berlaku 10 menit dan hanya sekali pakai; sukses `200`.

Validasi/kode salah atau kedaluwarsa menghasilkan `422` dan detail `errors`;
rate limit IP menghasilkan `429` (minta pengguna menunggu sebelum mencoba
kembali). Pengiriman WhatsApp memerlukan `FONNTE_TOKEN` valid pada konfigurasi
BE. Jika respons request OTP `200` tetapi pesan tidak diterima, periksa log
`BE/storage/logs/laravel.log` dan koneksi/provider Fonnte.

Contoh pemanggilan terpisah dari event handler UI (OTP dan reset token hanya
disimpan sementara di state aplikasi):

```js
const BASE_URL = '<base-url-backend>/api';
const jsonHeaders = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

async function requestPhonePasswordReset(phone) {
  const response = await fetch(`${BASE_URL}/forgot-password/phone`, {
    method: 'POST',
    headers: jsonHeaders,
    body: JSON.stringify({ phone }),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.message);
  return data; // Pesan generik; lanjutkan UI meminta OTP dari WhatsApp.
}

async function verifyPhonePasswordReset(phone, code) {
  const response = await fetch(`${BASE_URL}/forgot-password/phone/verify`, {
    method: 'POST',
    headers: jsonHeaders,
    body: JSON.stringify({ phone, code }),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.message);
  return data.reset_token; // Simpan sementara; jangan persist/log.
}

async function setPhonePassword(phone, resetToken, password) {
  const response = await fetch(`${BASE_URL}/reset-password/phone`, {
    method: 'POST',
    headers: jsonHeaders,
    body: JSON.stringify({
      phone,
      reset_token: resetToken,
      password,
      password_confirmation: password,
    }),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.message);
  return data;
}
```

## 4. Contoh Implementasi di Flutter (Mobile)

### Setup Base URL & Dio / HTTP Client
```dart
class ApiConfig {
  static const String baseUrl = 'https://sinuous-fame-jazz.ngrok-free.dev/api';
}

// Contoh header otomatis dengan token
Future<Map<String, String>> getHeaders() async {
  final prefs = await SharedPreferences.getInstance();
  final token = prefs.getString('token');
  return {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };
}
```

### Contoh Alur Registrasi & OTP
```dart
// 1. Register
Future<int?> register(String nama, String email, String password) async {
  final response = await http.post(
    Uri.parse('${ApiConfig.baseUrl}/register'),
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
    body: jsonEncode({
      'nama': nama,
      'email': email,
      'password': password,
      'password_confirmation': password,
    }),
  );

  if (response.statusCode == 201) {
    final data = jsonDecode(response.body);
    return data['user_id'] as int; // Simpan user_id untuk verifikasi OTP
  }
  throw Exception(jsonDecode(response.body)['message'] ?? 'Gagal registrasi');
}

// 2. Verifikasi OTP
Future<void> verifyOtp(int userId, String kode) async {
  final response = await http.post(
    Uri.parse('${ApiConfig.baseUrl}/register/verify-otp'),
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
    body: jsonEncode({'user_id': userId, 'kode': kode}),
  );

  if (response.statusCode == 200) {
    final data = jsonDecode(response.body);
    final token = data['token'];
    
    // Simpan token ke SharedPreferences
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', token);
  } else {
    throw Exception(jsonDecode(response.body)['message'] ?? 'Kode OTP salah');
  }
}
```

---

## 5. Checklist Integrasi untuk Tim Frontend
- [ ] Memastikan base URL berakhiran `/api` dan menggunakan domain ngrok HTTPS.
- [ ] Memastikan header `Authorization: Bearer <token>` disertakan pada seluruh request privat.
- [ ] Tidak melakukan kalkulasi kurs manual di sisi client (frontend).
- [ ] Menggunakan endpoint `/api/register` → `/api/register/verify-otp`; token hanya disimpan setelah verifikasi sukses.
- [ ] Menyediakan pengiriman ulang OTP melalui `/api/register/resend-otp` dan menampilkan pesan error jika API membalas `503`.
- [ ] Menggunakan endpoint `/api/auth/google` untuk login Google native, dengan field `mode` (`login` dari halaman Login, `register` dari halaman Sign Up).
