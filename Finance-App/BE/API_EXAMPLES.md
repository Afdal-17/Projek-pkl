# Contoh Koneksi API — Finance App

Panduan praktis untuk menyambungkan **frontend web** dan **mobile** ke backend Laravel.
Baca `API_DOCUMENTATION.md` untuk daftar endpoint lengkap.

---

## 0. Hal yang sering bikin gagal (baca dulu)

| Salah | Benar |
|---|---|
| `http://127.0.0.1:8000/api` | `http://127.0.0.1:8003/**api**` — port 8000 = Apache XAMPP (403) |
| `POST http://127.0.0.1:8003/login` → **419** | `POST http://127.0.0.1:8003/**api**/login` |
| Tanpa `Accept: application/json` → HTML/302 | Selalu kirim header ini |
| Tanpa `Authorization` → **401** | `Authorization: Bearer <token>` |
| `?q=budi` → selalu `[]` | `?query=budi` |

**Jalankan server dulu:**
```bash
cd BE
php artisan serve --host=0.0.0.0 --port=8003   # --host=0.0.0.0 agar HP bisa akses
```

**Akun demo:** `user@finance.test` / `password` (juga `admin@finance.test`)

### Base URL — pilih salah satu

| Untuk | Base URL |
|---|---|
| Frontend di mesin yang sama | `http://127.0.0.1:8003/api` |
| HP emulator di mesin yang sama | `http://127.0.0.1:8003/api` |
| HP fisik / laptop lain (Wi-Fi sama) | `http://<IP-komputer>:8003/api` |
| **Frontend/mobile di mana saja (ngrok)** | `https://sinuous-fame-jazz.ngrok-free.dev/api` |

**Cara pakai ngrok:**
```bash
ngrok http 8003          # tunnel ke port Laravel
# Ambil public_url dari layar, atau:
curl -s http://127.0.0.1:4040/api/tunnels | jq -r '.tunnels[0].public_url'
```

> URL ngrok gratisan **berubah setiap restart** — cek ulang lewat API `:4040`, jangan
> hardcode. Halaman peringatan ngrok hanya untuk browser; request API dengan header
> `Accept: application/json` langsung lolos. Untuk produksi, pakai domain sendiri.

Verifikasi nyata lewat ngrok (semua lolos):
```
POST /api/login                        → 200 + token
GET  /api/dompet                       → 200
GET  /api/dashboard/summary            → 200
GET  /api/transactions?page=1          → 200
GET  /api/transfer/recipients/search   → 200
POST /api/logout                       → 200
OPTIONS (preflight CORS)               → 204 + Access-Control-Allow-Origin
```

---

## 1. Test cepat pakai curl

```bash
BASE=http://127.0.0.1:8003/api

# 1. Login — ambil token
TOKEN=$(curl -s -X POST $BASE/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@finance.test","password":"password"}' | jq -r .token)

echo "Token: $TOKEN"

# 2. Pakai token di endpoint berikutnya
curl -s $BASE/dompet \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# 3. Filter transaksi + pagination
curl -s "$BASE/transactions?page=1&per_page=10&jenis=pengeluaran" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# 4. Cari penerima transfer (param: query, bukan q)
curl -s "$BASE/transfer/recipients/search?query=budi" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# 5. Logout (mencabut token)
curl -s -X POST $BASE/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 2. Frontend Web — vanilla JavaScript (fetch)

```js
const BASE = 'http://127.0.0.1:8003/api';
let token = localStorage.getItem('token');

async function api(path, { method = 'GET', body, headers = {} } = {}) {
  const res = await fetch(BASE + path, {
    method,
    headers: {
      'Accept': 'application/json',
      ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      ...headers,
    },
    body: body instanceof FormData ? body : (body ? JSON.stringify(body) : undefined),
  });

  // 204 = sukses tanpa body (mis. hapus avatar)
  if (res.status === 204) return null;

  const data = await res.json().catch(() => null);

  if (res.status === 401) {          // token expired / dicabut
    token = null;
    localStorage.removeItem('token');
    throw { status: 401, message: 'Sesi berakhir, silakan login ulang.' };
  }
  if (!res.ok) throw { status: res.status, ...(data || {}) };

  return data;
}

// --- Pakai ---
async function login(email, password) {
  const res = await api('/login', { method: 'POST', body: { email, password } });
  token = res.token;
  localStorage.setItem('token', token);
  return res.user;
}

const dompet  = await api('/dompet?page=1');
const ringkasan = await api('/dashboard/summary');
const transaksi = await api('/transactions?page=1&per_page=15&jenis=pengeluaran');

console.log(dompet.data);            // array dompet (paginator)
console.log(ringkasan.total_saldo);  // angka
console.log(transaksi.total);        // total baris setelah filter
```

---

## 3. Frontend Web — Axios

```js
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://127.0.0.1:8003/api',
  headers: { 'Accept': 'application/json' },
});

// Sisipkan token otomatis di setiap request
api.interceptors.request.use((config) => {
  const t = localStorage.getItem('token');
  if (t) config.headers.Authorization = `Bearer ${t}`;
  return config;
});

// Tangani 401 sekali di tempat
api.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(err.response?.data ?? err);
  }
);

// Pakai
const { data: user } = await api.post('/login', { email, password });
localStorage.setItem('token', user.token ?? user);   // token di key 'token'

const { data } = await api.get('/transactions', {
  params: { page: 1, per_page: 15, jenis: 'pengeluaran' },
});
```

> Login membalas `{ message, token, user }` — token ada di **`data.token`**, bukan `data.user.token`.

---

## 4. Mobile — Dart / Flutter

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class FinanceApi {
  static const baseUrl = 'http://127.0.0.1:8003/api';   // emulator
  // HP fisik: ganti 127.0.0.1 dengan IP komputer, mis. http://192.168.1.5:8003/api

  String? _token;
  void setToken(String? t) => _token = t;

  Future<dynamic> _req(String path, {String method = 'GET', Map<String, dynamic>? body}) async {
    final res = await http.request(
      Uri.parse('$baseUrl$path'),
      method: method,
      headers: {
        'Accept': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
        if (body != null) 'Content-Type': 'application/json',
      },
      body: body == null ? null : jsonEncode(body),
    );

    if (res.statusCode == 204) return null;
    final data = jsonDecode(res.body.isEmpty ? '{}' : res.body);

    if (res.statusCode == 401) { _token = null; throw ApiException(401, 'Sesi berakhir'); }
    if (res.statusCode >= 400) {
      // data: { message, errors: { field: [pesan] } }
      throw ApiException(res.statusCode,
          data['message'] ?? 'Terjadi kesalahan', errors: data['errors']);
    }
    return data;
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    final data = await _req('/login', method: 'POST',
        body: {'email': email, 'password': password});
    _token = data['token'];               // simpan ke SecureStorage
    return data['user'];
  }

  Future<List<dynamic>> dompet({int page = 1}) async {
    final data = await _req('/dompet?page=$page');
    return data['data'];                  // paginator: kunci 'data'
  }

  Future<List<dynamic>> transaksi({int page = 1, int perPage = 15, String? jenis}) async {
    final q = Uri(queryParameters: {
      'page': '$page', 'per_page': '$perPage',
      if (jenis != null) 'jenis': jenis,
    }).query;
    final data = await _req('/transactions?$q');
    return data['data'];
  }

  Future<List<dynamic>> cariPenerima(String q) async {
    // PENTING: param 'query', bukan 'q'
    return await _req('/transfer/recipients/search?query=${Uri.encodeComponent(q)}');
  }

  Future<void> logout() async {
    await _req('/logout', method: 'POST');
    _token = null;
  }
}

class ApiException implements Exception {
  final int status; final String message; final Map<String, dynamic>? errors;
  ApiException(this.status, this.message, {this.errors});
  @override String toString() => '[$status] $message';
}
```

**Android:** tambah `android:usesCleartextTraffic="true"` di `<application>` (HTTP lokal).
**iOS:** tambah `NSAllowsArbitraryLoads` di `Info.plist`.

---

## 5. Mobile — React Native

```js
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE = 'http://127.0.0.1:8003/api';   // HP fisik: IP komputer

export async function api(path, { method = 'GET', body } = {}) {
  const token = await AsyncStorage.getItem('token');

  const res = await fetch(BASE + path, {
    method,
    headers: {
      'Accept': 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (res.status === 204) return null;
  const data = await res.json().catch(() => ({}));

  if (res.status === 401) {
    await AsyncStorage.removeItem('token');
    throw { status: 401, message: 'Sesi berakhir' };
  }
  if (!res.ok) throw { status: res.status, ...data };   // { message, errors }
  return data;
}

// Login
const { token, user } = await api('/login', {
  method: 'POST',
  body: { email: 'user@finance.test', password: 'password' },
});
await AsyncStorage.setItem('token', token);

// Ambil data
const dompet = await api('/dompet?page=1');
```

---

## 6. Pola respons yang perlu diketahui klien

**Login**
```json
200 { "message": "Login berhasil", "token": "1|xxx", "user": { ... } }
```

**List ber-pagination** — `/dompet` dan `/transactions` membalas objek paginator:
```json
{ "current_page": 1, "per_page": 15, "total": 42, "last_page": 3,
  "data": [ ... ], "next_page_url": "...", "links": [ ... ] }
```
→ ambil dari **`.data`**. Tidak ada key `meta`.

**List tanpa pagination** — `/categories`, `/saving`, `/notifications` membalas **array langsung**:
```json
[ { "id": 1, "name": "Makanan", ... } ]
```

**Create/update** membalas pesan saja, **bukan objek**:
```json
201 { "message": "Transaksi berhasil dicatat." }
```
→ **kecuali** `POST /categories` yang mengembalikan `{ message, kategori }`.
Setelah create, panggil ulang GET untuk mendapat data terbaru.

**Error 422** (validasi, saldo kurang, duplikat 30 detik, rate limit login):
```json
{ "message": "Saldo dompet tidak mencukupi...", "errors": { "jumlah": ["..."] } }
```

**Kode status:**

| Kode | Arti | Tindakan |
|---|---|---|
| 200/201/204 | Sukses | — |
| 401 | Token salah/kedaluwarsa | Login ulang |
| 403 | Dompet tujuan tidak aktif menerima transfer | Minta penerima mengaktifkan |
| 404 | Tidak ada / bukan milik Anda | Cek ID |
| 405 | Transaksi transfer diubah/dihapus | Memang tidak bisa |
| 419 | URL tanpa `/api` | Perbaiki base URL |
| 422 | Validasi / saldo kurang / duplikat | Baca `errors` |

---

## 7. Checklist integrasi

- [ ] Base URL diakhiri `/api` dan port **8003**
- [ ] Header `Accept: application/json` di **semua** request
- [ ] Token disimpan setelah login, dikirim sebagai `Authorization: Bearer ...`
- [ ] Tangani **401** → redirect login / bersihkan token
- [ ] Tangani **422** → tampilkan `errors[field][0]`
- [ ] Pencarian penerima pakai `?query=`, bukan `?q=`
- [ ] Baca list paginator dari `data`, bukan dari root respons
- [ ] Setelah create, refetch list (respons create hanya berisi `message`)
- [ ] HP fisik: pakai IP komputer **atau** URL ngrok, bukan `127.0.0.1`
- [ ] Pakai ngrok: `BASE = https://<subdomain>.ngrok-free.dev/api` (cek ulang via `:4040/api/tunnels`, URL berubah saat restart)
- [ ] Android: `usesCleartextTraffic="true"` · iOS: `NSAllowsArbitraryLoads`
