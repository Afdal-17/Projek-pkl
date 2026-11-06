# 🚀 Panduan Instalasi & Menjalankan Proyek (Installation Guide)
**Proyek:** Finance App (Laravel Backend + Vue.js & Mobile Frontend)

Dokumen ini berisi panduan lengkap langkah demi langkah bagi penguji, guru, atau anggota tim untuk menginstal dan menjalankan seluruh sistem Finance App di komputer lokal.

---

## 📋 Prasyarat Sistem (Prerequisites)
Sebelum menjalankan proyek, pastikan komputer Anda telah terinstal:
1. **PHP** (Versi 8.2 atau terbaru)
2. **Composer** (Dependency manager untuk PHP)
3. **Node.js & NPM** (Untuk frontend Vue.js)
4. **MySQL / MariaDB** (Database server, misal via XAMPP)

---

## 🛠️ Langkah 1: Setup Backend Laravel (Port 8003)

1. **Buka terminal**, lalu masuk ke folder backend (`BE`):
   ```bash
   cd BE
   ```

2. **Install dependency PHP**:
   ```bash
   composer install
   ```

3. **Salin file konfigurasi lingkungan**:
   ```bash
   cp .env.example .env
   ```
   *(Jika di Windows PowerShell, gunakan: `Copy-Item .env.example .env`)*

4. **Generate App Key**:
   ```bash
   php artisan key:generate
   ```

5. **Konfigurasi Database**:
   Buka file `.env`, sesuaikan koneksi database MySQL Anda:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=finance_app
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. **Jalankan Migrasi & Seeder Database**:
   Perintah ini akan membuat seluruh tabel dan memasukkan akun demo secara otomatis:
   ```bash
   php artisan migrate:fresh --seed
   ```

7. **Jalankan Server Backend**:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8003
   ```
   *Backend kini aktif di: `http://127.0.0.1:8003`*

---

## 🌐 Langkah 2: Setup Frontend Vue.js (Opsional untuk Web)

1. **Buka terminal baru**, lalu masuk ke folder frontend (`Frontend User`):
   ```cd "Frontend User"
   ```

2. **Install dependency Node**:
   ```bash
   npm install
   ```

3. **Jalankan server development Vue**:
   ```bash
   npm run dev
   ```
   *Frontend Vue aktif di: `http://localhost:5173`*

---

## 🧪 Langkah 3: Menjalankan Automated Testing (Opsional)
Untuk membuktikan bahwa seluruh sistem berjalan normal dan bebas bug, Anda dapat menjalankan test suite Laravel:
```bash
cd BE
php artisan test
```
*(Hasil yang diharapkan: 120 test sukses / Passed).*

---

## 👤 Akun Demo untuk Login
Gunakan akun berikut untuk menguji aplikasi:
* **User Biasa:** `user@finance.test` / `password`
* **Admin:** `admin@finance.test` / `password`
