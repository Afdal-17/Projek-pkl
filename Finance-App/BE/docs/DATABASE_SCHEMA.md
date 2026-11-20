# 🗄️ Dokumentasi Struktur Database & ERD (Entity Relationship Diagram)
**Proyek:** Finance App (Laravel Backend + Vue.js & Mobile Frontend)

Dokumen ini menjelaskan rancangan tabel, tipe data, dan relasi antar tabel di dalam database MySQL yang digunakan oleh sistem Finance App.

---

## 1. Daftar Tabel & Relasi Utama

Sistem ini menggunakan basis data relasional MySQL dengan relasi kunci asing (*Foreign Key*) yang terstruktur untuk menjaga integritas data keuangan.

### Mata uang dasar dan migrasi data

Seluruh nominal finansial disimpan sebagai USD. Migrasi
`2026_11_18_000000_convert_financial_data_to_usd_base` mengonversi data lama
berbasis IDR memakai satu kurs USD/IDR yang dicatat di tabel
`currency_base_migration_rates`, lalu menaikkan presisi kolom finansial menjadi
`DECIMAL(26,8)`. Snapshot kurs dipertahankan untuk rollback dan sebagai penanda
pemulihan jika migrasi gagal setelah konversi; jangan menghapusnya secara manual
sebelum memastikan status migrasi dan data.

| No | Nama Tabel | Fungsi Utama | Relasi Terkait |
|----|------------|--------------|----------------|
| 1 | `users` | Menyimpan data akun pengguna (user & admin) | Memiliki banyak `dompet`, `transaksi`, `target_tabungan`, `notifikasi` |
| 2 | `dompet` | Menyimpan rekening/dompet keuangan milik user | Milik 1 `user`, memiliki banyak `transaksi` |
| 3 | `transaksi` | Mencatat pemasukan, pengeluaran, dan penyesuaian saldo | Milik 1 `user` & 1 `dompet`, berelasi dengan `kategori` |
| 4 | `kategori` | Kategori transaksi (misal: Makanan, Transport, Gaji) | Milik 1 `user` (atau kategori bawaan sistem), memiliki banyak `transaksi` |
| 5 | `transfer` | Mencatat riwayat transfer antar dompet/antar user | Berelasi dengan pengirim & penerima (`users` & `dompet`) |
| 6 | `target_tabungan` | Menyimpan target finansial & progres tabungan | Milik 1 `user` & berelasi opsional dengan `dompet` |
| 7 | `notifikasi` | Menyimpan pesan pemberitahuan in-app untuk user | Milik 1 `user` |
| 8 | `personal_access_tokens` | Menyimpan token autentikasi API Laravel Sanctum | Polimorfik ke tabel `users` |

---

## 2. Skema Kolaborasi Detail per Tabel

### A. Tabel `users`
Menyimpan informasi profil, kredensial, role, dan status keamanan pengguna.
* `id_user` (Primary Key, INT, Auto Increment)
* `nama` (VARCHAR)
* `email` (VARCHAR, Unique)
* `password` (VARCHAR, Hashed)
* `role` (ENUM: `user`, `admin`)
* `status` (BOOLEAN: aktif / non-aktif)
* `email_verified_at` (TIMESTAMP, nullable)
* `google_id` (VARCHAR, nullable, unique - untuk login Google)
* `mata_uang` (VARCHAR, default: `USD`; migration baru tidak mengubah preferensi user yang sudah tersimpan)
* `banned_at`, `ban_reason` (untuk fitur hukuman banned admin)

### B. Tabel `dompet`
Menyimpan daftar dompet keuangan user (Cash, Bank, E-Wallet, dll).
* `id` (Primary Key, INT)
* `user_id` (Foreign Key ke `users.id_user`)
* `nama` (VARCHAR)
* `kategori` (VARCHAR)
* `saldo_awal`, `saldo` (`DECIMAL(26,8)`, nominal USD dasar)
* `terima_transfer` (BOOLEAN - status bisa menerima transfer dari user lain)

### C. Tabel `transaksi`
Mencatat seluruh arus kas masuk dan keluar.
* `id` (Primary Key, INT)
* `user_id` (Foreign Key ke `users`)
* `dompet_id` (Foreign Key ke `dompet`)
* `kategori_id` (Foreign Key ke `kategori`, nullable)
* `jenis` (ENUM: `pemasukan`, `pengeluaran`)
* `jumlah` (`DECIMAL(26,8)`, nominal USD dasar)
* `tanggal` (DATE)
* `catatan` (TEXT, nullable)

### D. Tabel `target_tabungan`
Mencatat impian atau target finansial user.
* `id` (Primary Key, INT)
* `user_id` (Foreign Key ke `users`)
* `dompet_id` (Foreign Key ke `dompet`, opsional)
* `nama_target` (VARCHAR)
* `nominal_target`, `nominal_terkumpul`, `saldo_awal_dompet` (`DECIMAL(26,8)`, nominal USD dasar)
* `tanggal_target` (DATE)
* `status` (ENUM: `aktif`, `tercapai`)

### E. Tabel `transfer` dan `notifikasi`
* `transfer.jumlah` (`DECIMAL(26,8)`, nominal USD dasar)
* `notifikasi.jumlah` (`DECIMAL(26,8)`, nullable, nominal USD dasar)

---

## 3. Diagram Relasi Konseptual (ERD Text)
```text
[ USERS ] 1 --------< (N) [ DOMPET ] 1 --------< (N) [ TRANSAKSI ] >-------- (1) [ KATEGORI ]
   |                        |
   |                        +--------< (N) [ TARGET_TABUNGAN ]
   |
   +--------< (N) [ NOTIFIKASI ]
   |
   +--------< (N) [ TRANSFER ]
```
