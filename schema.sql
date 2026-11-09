-- Skema database PUSTAKA - Sistem Informasi Perpustakaan (SQLite)
-- Dijalankan otomatis oleh app.py saat aplikasi pertama kali dijalankan.

PRAGMA foreign_keys = ON;

-- Semua akun: admin, petugas, dan anggota. Login memakai email.
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'anggota'
                  CHECK (role IN ('admin', 'petugas', 'anggota')),
    member_code   TEXT UNIQUE,              -- hanya untuk anggota, contoh AG0004
    phone         TEXT,
    address       TEXT,
    status        TEXT NOT NULL DEFAULT 'aktif'
                  CHECK (status IN ('aktif', 'nonaktif')),
    created_at    TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS categories (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS books (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    isbn            TEXT UNIQUE,
    title           TEXT NOT NULL,
    author          TEXT NOT NULL,
    publisher       TEXT,
    year            INTEGER,
    category_id     INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    shelf           TEXT,                   -- lokasi rak
    description     TEXT,
    stock_total     INTEGER NOT NULL DEFAULT 1 CHECK (stock_total >= 0),
    stock_available INTEGER NOT NULL DEFAULT 1 CHECK (stock_available >= 0),
    created_at      TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

-- Alur status peminjaman:
--   menunggu     : anggota mengajukan, menunggu persetujuan petugas
--   dipinjam     : disetujui (atau dicatat langsung oleh petugas), stok berkurang
--   dikembalikan : sudah dikembalikan, denda dihitung, stok bertambah
--   ditolak      : pengajuan ditolak petugas
--   dibatalkan   : pengajuan dibatalkan oleh anggota
CREATE TABLE IF NOT EXISTS loans (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    book_id      INTEGER NOT NULL REFERENCES books(id),
    member_id    INTEGER NOT NULL REFERENCES users(id),
    status       TEXT NOT NULL DEFAULT 'menunggu'
                 CHECK (status IN ('menunggu', 'dipinjam', 'dikembalikan', 'ditolak', 'dibatalkan')),
    requested_at TEXT NOT NULL DEFAULT (date('now', 'localtime')),
    loan_date    TEXT,
    due_date     TEXT,
    return_date  TEXT,
    fine         INTEGER NOT NULL DEFAULT 0,
    processed_by INTEGER REFERENCES users(id),   -- petugas yang menyetujui / menolak
    returned_by  INTEGER REFERENCES users(id)    -- petugas yang memproses pengembalian
);

CREATE TABLE IF NOT EXISTS favorites (
    member_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    book_id   INTEGER NOT NULL REFERENCES books(id) ON DELETE CASCADE,
    PRIMARY KEY (member_id, book_id)
);

CREATE TABLE IF NOT EXISTS ratings (
    member_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    book_id   INTEGER NOT NULL REFERENCES books(id) ON DELETE CASCADE,
    stars     INTEGER NOT NULL CHECK (stars BETWEEN 1 AND 5),
    PRIMARY KEY (member_id, book_id)
);

-- Pengaturan yang bisa diubah admin
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

INSERT OR IGNORE INTO settings (key, value) VALUES
    ('loan_days', '7'),        -- lama pinjam (hari)
    ('fine_per_day', '500'),   -- denda per hari terlambat (Rp)
    ('max_loans', '3');        -- maksimal buku yang boleh dipinjam/diajukan sekaligus

CREATE INDEX IF NOT EXISTS idx_books_title   ON books(title);
CREATE INDEX IF NOT EXISTS idx_books_cat     ON books(category_id);
CREATE INDEX IF NOT EXISTS idx_loans_member  ON loans(member_id, status);
CREATE INDEX IF NOT EXISTS idx_loans_book    ON loans(book_id, status);
CREATE INDEX IF NOT EXISTS idx_loans_due     ON loans(due_date);
