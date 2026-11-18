# 📊 Laporan Pengujian Sistem (Testing Report)
**Proyek:** Finance App (Laravel Backend + Vue.js & Mobile Frontend)

Dokumen ini berisi laporan resmi hasil pengujian otomatis (*Automated Testing*) pada sistem backend Laravel untuk menjamin keandalan, keamanan, dan kebenaran logika keuangan aplikasi.

---

## 1. Metode Pengujian
Pengujian dilakukan menggunakan **Laravel Testing Suite** (mencakup *Feature Test* dan *Unit Test* berbasis PHPUnit). Setiap fitur keuangan, mulai dari pembuatan dompet, pencatatan transaksi, transfer antar user, konversi mata uang, hingga sistem keamanan autentikasi diuji secara otomatis.

---

## 2. Ringkasan Hasil Pengujian Terkini

* **Total Test Suite:** 148 test cases
* **Total Assertions (Pemeriksaan Logika):** 744 assertions
* **Status Keseluruhan:** **100% LULUS (PASSED)** 🟢
* **Durasi Eksekusi:** 28.15 detik

---

## 3. Cakupan Modul yang Diuji (Test Coverage)

1. **Modul Autentikasi & Keamanan:**
   * Login API Sanctum (sukses & penolakan password salah).
   * Verifikasi Email & OTP, rate limit kirim ulang, serta kegagalan pengiriman email/WhatsApp.
   * Pesan OTP WhatsApp berbahasa Inggris.
   * Pembatasan akun yang diblokir (*Banned user check*).
   * Google OAuth & Mobile Google API login.

2. **Modul Keuangan & Dompet (`Wallet` & `Transaksi`):**
   * Pembuatan dompet dengan saldo awal (otomatis mencatat transaksi ledger).
   * Perhitungan saldo dompet yang akurat (mendukung angka hingga skala kuadriliun).
   * Filter dan pagination riwayat transaksi (`?query=`, `?jenis=`, `?per_page=`).

3. **Modul Transfer Antar User (`Transfer`):**
   * Pencarian dompet penerima berdasarkan nama user.
   * Validasi saldo mencukupi sebelum transfer diproses.
   * Konversi mata uang otomatis (IDR, USD, EUR) pada transfer lintas mata uang.
   * Pembuatan notifikasi otomatis untuk pengirim dan penerima.
   * USD sebagai mata uang dasar penyimpanan, konversi nilai lama dari IDR, dan kurs live dengan fallback.
   * Kontrak response API mempertahankan field nominal USD dan menyertakan nilai tampilan beserta kurs yang digunakan.

4. **Modul Target Tabungan (`TargetTabungan`):**
   * Pemantauan progres tabungan otomatis mengikuti saldo dompet.
   * Notifikasi tercapainya target finansial.

5. **Migrasi & kredensial deployment:**
   * Migrasi konversi USD memakai snapshot kurs dan dapat diulang dengan aman bila percobaan terdahulu menyimpan snapshot tetapi gagal saat commit.
   * Panduan rotasi Google OAuth Client Secret dan token Fonnte tersedia di `docs/DEPLOYMENT.md`; secret/token yang pernah dibagikan harus dicabut dan diganti.

6. **Modul Keamanan Ganda & Proteksi Data:**
   * Pencegahan pengiriman ganda (*Duplicate Submission Guard* / *Double-tap protection*).
   * Validasi otorisasi kepemilikan data (mencegah IDOR antar user).

---

## 4. Kesimpulan
Berdasarkan hasil pengujian di atas, backend **Finance App** dinyatakan stabil, aman dari celah manipulasi saldo, dan **siap untuk diintegrasikan secara penuh** dengan aplikasi frontend Vue.js maupun mobile Flutter.
