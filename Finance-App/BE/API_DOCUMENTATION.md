# Dokumentasi API - Finance App (Frontend User & Vue.js Integration)

Dokumentasi ini mencakup daftar endpoint API yang tersedia di backend Laravel untuk dikonsumsi oleh Frontend Vue.js.

Base URL: `http://127.0.0.1:8000/api`

---

## 1. Autentikasi (Authentication)

### Login API
* **Endpoint:** `POST /api/login`
* **Deskripsi:** Melakukan autentikasi pengguna dan mengembalikan token Laravel Sanctum.
* **Body (JSON):**
  ```json
  {
    "email": "user@example.com",
    "password": "password123"
  }
  ```
* **Response Sukses (200 OK):**
  ```json
  {
    "message": "Login berhasil",
    "token": "1|LaravelSanctumTokenString...",
    "user": {
      "id_user": 1,
      "name": "Nama User",
      "email": "user@example.com",
      "role": "user"
    }
  }
  ```

### Get Authenticated User
* **Endpoint:** `GET /api/user`
* **Header:** 
  * `Authorization: Bearer <token>`
  * `Accept: application/json`
* **Response Sukses (200 OK):** Mengembalikan data user yang sedang login.

---

## 2. Dompet (Wallets)

### Ambil Daftar Dompet
* **Endpoint:** `GET /api/dompet`
* **Header:** `Authorization: Bearer <token>`
* **Response Sukses (200 OK):** Mengembalikan daftar dompet beserta status `terima_transfer` (0 atau 1) dan saldo presisi tinggi (hingga kuadriliun).

### Toggle Status Penerima Transfer
* **Endpoint:** `PATCH /api/dompet/{id}/toggle-transfer`
* **Header:** `Authorization: Bearer <token>`
* **Deskripsi:** Mengaktifkan/menonaktifkan dompet sebagai penerima transfer lintas user (hanya 1 dompet aktif per user).

---

## 3. Transfer Antar User

### Cari Penerima Transfer
* **Endpoint:** `GET /api/transfer/recipients/search?q=nama`
* **Header:** `Authorization: Bearer <token>`
* **Deskripsi:** Mencari dompet user lain yang aktif melakukan transfer (`terima_transfer = true`).

### Kirim Transfer
* **Endpoint:** `POST /api/transfer`
* **Header:** `Authorization: Bearer <token>`
* **Body (JSON):**
  ```json
  {
    "penerima_id": 2,
    "jumlah": 500000.00,
    "catatan": "Bayar hutang"
  }
  ```
* **Response Sukses (201 Created):** Transfer berhasil diproses dengan proteksi *duplicate submission* dan pencatatan transaksi mutasi ganda.

---

## 4. Transaksi & Tabungan

### Daftar Transaksi
* **Endpoint:** `GET /api/transactions`
* **Header:** `Authorization: Bearer <token>`

### Tambah Transaksi
* **Endpoint:** `POST /api/transactions`
* **Header:** `Authorization: Bearer <token>`

### Daftar Target Tabungan
* **Endpoint:** `GET /api/saving`
* **Header:** `Authorization: Bearer <token>`

---
*(Dokumentasi ini otomatis mendukung format JSON API untuk integrasi SPA Vue.js / Axios)*
