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
*(Header `Authorization` hanya dikosongkan untuk endpoint publik seperti `/login`, `/register`, `/register/verify-otp`, dan `/auth/google`).*

---

## 2. Aturan Penting Konversi Mata Currency (Penting untuk Tim Mobile/Web)

Backend Finance App **menyimpan seluruh data keuangan di database dalam bentuk Rupiah (IDR) murni** sebagai *base currency*. 

1. **Backend Menangani Konversi Otomatis:**
   * Backend otomatis membaca preferensi mata uang user (`mata_uang` di tabel `users`, pilihannya: `IDR`, `USD`, `EUR`).
   * Setiap nilai yang dikirim oleh endpoint API (`/api/dompet`, `/api/dashboard/summary`, `/api/transaksi`, dll) **sudah dikonversi otomatis** oleh backend ke mata uang pilihan user tersebut.
2. **Aturan untuk Frontend (Flutter/Vue):**
   * **JANGAN** melakukan perhitungan kurs / perkalian / pembagian kurs manual di aplikasi mobile atau web frontend.
   * Frontend cukup **menerima angka mentah** dari JSON response backend, lalu memformatnya menggunakan formatter mata uang lokal (misal `NumberFormat.currency` di Flutter atau `Intl.NumberFormat` di Vue).
   * Untuk mengganti mata uang akun, frontend cukup memanggil:
     `PATCH /api/account/currency` dengan body `{ "mata_uang": "USD" }` (pilihan: `IDR`, `USD`, `EUR`).

---

## 3. Daftar Endpoint Autentikasi Frontend

| Method | Endpoint | Deskripsi | Auth (Token) |
|---|---|---|---|
| `POST` | `/api/register` | Mendaftarkan akun baru via email (mengirim OTP) | Publik |
| `POST` | `/api/register/verify-otp` | Verifikasi 6 digit kode OTP (mengaktifkan akun & mengembalikan token) | Publik |
| `POST` | `/api/login` | Login dengan email/phone + password | Publik |
| `POST` | `/api/forgot-password` | Kirim link/token reset password ke email | Publik |
| `POST` | `/api/reset-password` | Ubah password baru menggunakan token reset | Publik |
| `POST` | `/api/forgot-password/phone` | Minta OTP pemulihan lewat WhatsApp | Publik |
| `POST` | `/api/forgot-password/phone/verify` | Verifikasi OTP, menerima `reset_token` | Publik |
| `POST` | `/api/reset-password/phone` | Ubah password menggunakan `reset_token` | Publik |
| `PUT` | `/api/password` | Mengganti password akun (wajib login) | **Wajib Bearer Token** |
| `POST` | `/api/logout` | Logout (menghapus token Sanctum di database) | **Wajib Bearer Token** |

---

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
- [ ] Menggunakan endpoint `/api/register` dan `/api/register/verify-otp` untuk alur pendaftaran email baru.
- [ ] Menggunakan endpoint `/api/auth/google` untuk login Google native, dengan field `mode` (`login` dari halaman Login, `register` dari halaman Sign Up).
