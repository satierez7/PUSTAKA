PUSTAKA - Sistem Peminjaman Buku Perpustakaan (PHP Native + MySQL)

CARA MENJALANKAN (XAMPP / Laragon)
1. Extract zip ini. Pindahkan folder "pustaka" ke:
     XAMPP   : C:\xampp\htdocs\pustaka
     Laragon : C:\laragon\www\pustaka
2. Nyalakan Apache dan MySQL (XAMPP Control Panel atau tombol Start All di Laragon).
3. Buka di browser:  http://localhost/pustaka/install.php
   Klik "Pasang database". Database, tabel, dan data contoh dibuat otomatis.
4. Buka  http://localhost/pustaka/  lalu login.

Jika MySQL kamu memakai sandi, ubah DB_USER / DB_PASS di config.php.
Syarat: PHP 7.4 atau lebih baru (ekstensi PDO MySQL, sudah aktif bawaan XAMPP/Laragon).

AKUN DEMO (sandi: password)
  admin@perpus.test     (Admin)
  petugas@perpus.test   (Petugas)
  anggota@perpus.test   (Anggota)

FITUR
- Login, registrasi anggota, logout, 3 role (admin, petugas, anggota)
- CRUD buku, kategori, anggota
- Anggota: katalog + cari/filter, ajukan pinjam, pinjaman saya (aktif/riwayat)
- Petugas/Admin: dashboard, setujui/tolak pinjam, proses pengembalian, laporan + cetak
- Aturan: maks 3 buku, lama pinjam 7 hari, denda Rp1.000/hari, stok otomatis
  (ubah di config.php: DENDA_PER_HARI, MAKS_PINJAM, LAMA_PINJAM)
- Keamanan dasar: PDO prepared statement, password_hash, token CSRF, escape output

STRUKTUR
  config.php        koneksi DB, aturan, fungsi bantu
  install.php       pembuat database + data contoh
  login/register/logout.php
  katalog.php, pinjam.php, pinjaman_saya.php      (anggota)
  admin/*.php       dashboard, books, categories, members, loans, returns, reports
  partials/         tampilan bersama (header, footer)
  assets/css/app.css

ALUR DEMO
Login anggota -> pinjam buku -> login petugas -> setujui -> pengembalian -> laporan.
