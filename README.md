# PUSTAKA — Sistem Informasi Perpustakaan

Aplikasi web peminjaman buku perpustakaan. Dibuat dengan **Python (Flask)** dan database **SQLite**.
Anggota mengajukan peminjaman dari website, petugas menyetujui, lalu pengembalian dan denda dihitung otomatis.

## Cara menjalankan

Butuh **Python 3.9+** (centang "Add python.exe to PATH" saat memasang di Windows).

```bash
# 1. buka terminal di folder ini, lalu buat virtual environment
python -m venv venv

# 2. aktifkan
venv\Scripts\activate            # Windows CMD
venv\Scripts\Activate.ps1        # Windows PowerShell
source venv/bin/activate         # Mac / Linux

# 3. pasang Flask
pip install -r requirements.txt

# 4. jalankan
python app.py
```

Buka **http://127.0.0.1:5050**. Database `pustaka.db` dibuat otomatis (tabel dari `schema.sql` + data contoh)
saat pertama kali dijalankan. Berhenti dengan `Ctrl + C`.

- Port bentrok? Jalankan dengan port lain: `set PORT=8123` lalu `python app.py` (Windows CMD),
  atau `$env:PORT=8123; python app.py` (PowerShell).
- Mengulang data dari awal: hentikan aplikasi, hapus file `pustaka.db`, jalankan lagi.

## Akun contoh

| Peran | Email | Kata sandi |
|---|---|---|
| Admin | admin@pustaka.id | admin123 |
| Petugas | petugas@pustaka.id | petugas123 |
| Anggota | andi@pustaka.id | anggota123 |

Anggota contoh lain: budi, siti, rahmat, dewi (@pustaka.id, sandi `anggota123`).
Budi dan Rahmat sengaja punya buku terlambat untuk mendemokan denda dan pemblokiran pinjam.
**Ganti semua kata sandi ini sebelum dipakai sungguhan.**

## Peran dan fitur

| Peran | Yang bisa dilakukan |
|---|---|
| **Anggota** | Daftar sendiri · masuk dengan email · cari katalog (judul, penulis, kategori) · lihat detail buku · **ajukan pinjam** · batalkan pengajuan · **Pinjaman Saya** (aktif/riwayat, sisa hari, denda) · favorit · beri rating · rekomendasi buku · ganti profil/sandi |
| **Petugas** | Dashboard · CRUD **buku** dan **kategori** · CRUD **anggota** · **setujui/tolak pengajuan** · catat peminjaman langsung di meja layanan · **proses pengembalian** + denda otomatis · **laporan** (filter tanggal, cetak, unduh CSV) |
| **Admin** | Semua fitur petugas + kelola akun **petugas** + **pengaturan** (lama pinjam, denda per hari, maksimal buku) |

## Alur peminjaman

```
Anggota daftar/masuk → cari buku → "Ajukan Pinjam"           status: menunggu
        ↓
Petugas buka Peminjaman → "Setujui" (atau "Tolak")           status: dipinjam / ditolak
   (stok berkurang, jatuh tempo = hari ini + 7 hari)
        ↓
Anggota mengambil buku di perpustakaan
        ↓
Anggota mengembalikan → Petugas buka Pengembalian → "Proses"  status: dikembalikan
   (stok bertambah, denda = hari terlambat × Rp500)
```

Anggota yang datang langsung ke meja boleh dicatat petugas lewat **Peminjaman → Catat Peminjaman**
(langsung berstatus dipinjam, tanpa pengajuan).

## Aturan bisnis

- Maksimal **3 buku** sekaligus (dipinjam + menunggu persetujuan). Bisa diubah admin.
- Lama pinjam **7 hari**, denda **Rp500 per hari** terlambat. Bisa diubah admin.
- Stok habis → tidak bisa diajukan. Stok baru berkurang saat petugas **menyetujui**.
- Anggota yang masih punya buku terlambat tidak bisa meminjam lagi sampai mengembalikannya.
- Satu buku tidak bisa diajukan dua kali oleh anggota yang sama selama masih aktif.
- Buku yang punya riwayat peminjaman tidak bisa dihapus. Jumlah stok tidak boleh kurang dari yang sedang dipinjam.
- Rating hanya untuk buku yang pernah dipinjam anggota itu. Akun nonaktif langsung tidak bisa masuk.
- Admin terakhir tidak bisa diturunkan atau dinonaktifkan.

## Struktur database (`schema.sql`)

| Tabel | Isi | Relasi |
|---|---|---|
| `users` | akun admin, petugas, anggota (login pakai email, sandi di-hash) | — |
| `categories` | kategori buku | — |
| `books` | katalog + stok (`stock_total`, `stock_available`) | `category_id` → categories |
| `loans` | pengajuan dan transaksi: status, tanggal, denda | `book_id` → books, `member_id` / `processed_by` / `returned_by` → users |
| `favorites` | buku favorit anggota | users, books |
| `ratings` | rating 1–5 per anggota per buku | users, books |
| `settings` | lama pinjam, denda per hari, maksimal buku | — |

Status `loans.status`: `menunggu` → `dipinjam` → `dikembalikan` (cabang lain: `ditolak`, `dibatalkan`).
File `database/pustaka_contoh.sql` adalah salinan database contoh dalam bentuk SQL (bisa dibuka dengan
DB Browser for SQLite atau diimpor: `sqlite3 baru.db < database/pustaka_contoh.sql`).

## Struktur folder

```
pustaka/
├── app.py            ← semua logika: route, aturan peminjaman, laporan
├── schema.sql        ← definisi tabel
├── requirements.txt  ← library (hanya Flask)
├── templates/        ← halaman HTML (Jinja2)
├── static/style.css  ← tampilan tema PUSTAKA
└── database/pustaka_contoh.sql
```

## Skenario demo (urutan yang disarankan untuk penguji)

1. Buka halaman login, klik **Daftar**, buat akun anggota baru (atau masuk sebagai `andi@pustaka.id`).
2. Di **Katalog Buku**, buka sebuah buku, klik **Ajukan Pinjam**. Lihat statusnya di **Pinjaman Saya**
   (*Menunggu persetujuan*). Coba buku **Clean Code**: stoknya habis jadi tidak bisa diajukan.
3. Keluar, masuk sebagai **petugas**. Dashboard menampilkan peringatan pengajuan menunggu.
   Buka **Peminjaman → Menunggu**, klik **Setujui**.
4. Kembali sebagai anggota: status berubah *Dipinjam* dengan sisa 7 hari. Coba **favorit** dan **rating**.
5. Sebagai petugas, buka **Pengembalian**, cari `Budi` atau kode `PJ-0002`: buku terlambat 3 hari,
   denda **Rp1.500** (3 × Rp500). Klik **Proses pengembalian**, stok bertambah.
6. Buka **Laporan**, pilih rentang tanggal, lalu **Cetak** atau **Unduh CSV**.
7. Sebagai **admin**: kelola **Petugas** dan ubah **Pengaturan** (misal denda jadi Rp1.000), lalu lihat dampaknya.

## Catatan keamanan

Kata sandi disimpan dengan hash (bukan teks asli), semua query memakai parameter (aman dari SQL injection),
dan setiap halaman dicek per peran. Aplikasi ini **belum memakai proteksi CSRF** dan dijalankan dengan
server pengembangan Flask; untuk penggunaan sungguhan tambahkan Flask-WTF dan jalankan dengan
`waitress` atau `gunicorn`, serta isi `SECRET_KEY` lewat variabel lingkungan.
