-- Salinan database contoh PUSTAKA (dibuat otomatis dari data awal aplikasi)
BEGIN TRANSACTION;
CREATE TABLE books (
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
INSERT INTO "books" VALUES(1,NULL,'Laskar Pelangi','Andrea Hirata','Bentang',2005,1,'A-01','Kisah sepuluh anak di Belitung yang berjuang menempuh pendidikan dengan segala keterbatasan.',3,3,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(2,NULL,'Bumi Manusia','Pramoedya Ananta Toer','Lentera Dipantara',1980,1,'A-02','Novel pertama Tetralogi Buru tentang Minke, pelajar pribumi di masa kolonial Hindia Belanda.',2,1,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(3,NULL,'Atomic Habits','James Clear','Gramedia',2019,5,'C-01','Panduan membangun kebiasaan baik lewat perubahan kecil yang dilakukan konsisten.',4,3,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(4,NULL,'Filosofi Teras','Henry Manampiring','Kompas',2018,5,'C-02','Pengantar filsafat Stoa untuk menghadapi emosi negatif dan menjalani hidup lebih tenang.',3,3,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(5,NULL,'Sapiens','Yuval Noah Harari','KPG',2017,4,'D-01','Sejarah singkat umat manusia, dari zaman batu sampai era modern.',2,1,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(6,NULL,'Negeri 5 Menara','Ahmad Fuadi','Gramedia',2009,1,'A-03','Perjalanan enam santri di pesantren dengan semangat man jadda wajada.',3,3,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(7,NULL,'Clean Code','Robert C. Martin','Prentice Hall',2008,3,'T-01','Prinsip dan praktik menulis kode yang bersih, mudah dibaca, dan mudah dirawat.',1,0,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(8,NULL,'Cantik Itu Luka','Eka Kurniawan','Gramedia',2002,1,'A-04','Novel epik tentang Dewi Ayu dan keturunannya di tengah sejarah panjang Nusantara.',2,2,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(9,NULL,'Bumi','Tere Liye','Gramedia',2014,1,'A-05','Petualangan Raib, remaja yang menemukan dunia paralel di balik kehidupan sehari-hari.',3,3,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(10,NULL,'A Brief History of Time','Stephen Hawking','Bantam',1988,2,'S-01','Pengantar populer tentang alam semesta, lubang hitam, dan asal-usul waktu.',2,2,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(11,NULL,'Cosmos','Carl Sagan','Random House',1980,2,'S-02','Perjalanan menjelajahi alam semesta dan tempat manusia di dalamnya.',2,1,'2026-10-23 09:45:31');
INSERT INTO "books" VALUES(12,NULL,'The Pragmatic Programmer','Andrew Hunt & David Thomas','Addison-Wesley',1999,3,'T-02','Kumpulan kiat praktis untuk menjadi programmer yang efektif.',2,2,'2026-10-23 09:45:31');
CREATE TABLE categories (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);
INSERT INTO "categories" VALUES(1,'Fiksi');
INSERT INTO "categories" VALUES(2,'Sains');
INSERT INTO "categories" VALUES(3,'Teknologi');
INSERT INTO "categories" VALUES(4,'Sejarah');
INSERT INTO "categories" VALUES(5,'Pengembangan Diri');
CREATE TABLE favorites (
    member_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    book_id   INTEGER NOT NULL REFERENCES books(id) ON DELETE CASCADE,
    PRIMARY KEY (member_id, book_id)
);
INSERT INTO "favorites" VALUES(3,3);
INSERT INTO "favorites" VALUES(3,4);
INSERT INTO "favorites" VALUES(4,5);
CREATE TABLE loans (
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
INSERT INTO "loans" VALUES(1,2,3,'dipinjam','2026-10-22','2026-10-22','2026-10-29',NULL,0,2,NULL);
INSERT INTO "loans" VALUES(2,3,3,'dipinjam','2026-10-13','2026-10-13','2026-10-20',NULL,0,2,NULL);
INSERT INTO "loans" VALUES(3,1,3,'menunggu','2026-10-23',NULL,NULL,NULL,0,NULL,NULL);
INSERT INTO "loans" VALUES(4,5,4,'dipinjam','2026-10-18','2026-10-18','2026-10-25',NULL,0,2,NULL);
INSERT INTO "loans" VALUES(5,7,4,'dipinjam','2026-10-21','2026-10-21','2026-10-28',NULL,0,2,NULL);
INSERT INTO "loans" VALUES(6,6,4,'dikembalikan','2026-09-23','2026-09-23','2026-09-30','2026-09-29',0,2,2);
INSERT INTO "loans" VALUES(7,4,4,'dikembalikan','2026-09-13','2026-09-13','2026-09-20','2026-09-23',1500,2,2);
INSERT INTO "loans" VALUES(8,11,5,'dipinjam','2026-10-11','2026-10-11','2026-10-18',NULL,0,2,NULL);
INSERT INTO "loans" VALUES(9,10,5,'dikembalikan','2026-09-28','2026-09-28','2026-10-05','2026-10-05',0,2,2);
INSERT INTO "loans" VALUES(10,8,6,'menunggu','2026-10-22',NULL,NULL,NULL,0,NULL,NULL);
INSERT INTO "loans" VALUES(11,9,7,'dikembalikan','2026-10-03','2026-10-03','2026-10-10','2026-10-09',0,2,2);
CREATE TABLE ratings (
    member_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    book_id   INTEGER NOT NULL REFERENCES books(id) ON DELETE CASCADE,
    stars     INTEGER NOT NULL CHECK (stars BETWEEN 1 AND 5),
    PRIMARY KEY (member_id, book_id)
);
INSERT INTO "ratings" VALUES(4,6,5);
INSERT INTO "ratings" VALUES(4,4,4);
INSERT INTO "ratings" VALUES(5,10,5);
INSERT INTO "ratings" VALUES(7,9,4);
INSERT INTO "ratings" VALUES(3,2,5);
CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
INSERT INTO "settings" VALUES('loan_days','7');
INSERT INTO "settings" VALUES('fine_per_day','500');
INSERT INTO "settings" VALUES('max_loans','3');
CREATE TABLE users (
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
INSERT INTO "users" VALUES(1,'Administrator','admin@pustaka.id','scrypt:32768:8:1$L7qc1IsGwGd0wVkg$2e5dca50f2def1931f143856df18d79b27b2ccb818a3722b97db317796f3d5004f1ccb3a797f1556e365364b3b0a51096fe857d9c6aaa6b948f87319f089f7fd','admin',NULL,NULL,NULL,'aktif','2026-10-23 09:45:31');
INSERT INTO "users" VALUES(2,'Petugas Pustaka','petugas@pustaka.id','scrypt:32768:8:1$99yiQK0EYz5hDsl4$e822a72a7e38e7b1469b5ef58d982f0528f58d2b3188b36183a1baf0e646086b51b0312348ee3c447d4d2e8223623948529ac84698d38d470f7efccaf44be205','petugas',NULL,NULL,NULL,'aktif','2026-10-23 09:45:31');
INSERT INTO "users" VALUES(3,'Budi Santoso','budi@pustaka.id','scrypt:32768:8:1$aXjHuoydIiVMq23C$0f9063890cabf4886a43ffdbb14e3ebb7ec154b1fb519e2365ec5f07b986ee93d95206f20739bdd557cad42e73d1c9ac414c0570a8fae63ca7f0ceb231841da5','anggota','AG0003','081234567890','Jakarta','aktif','2026-10-23 09:45:31');
INSERT INTO "users" VALUES(4,'Siti Aminah','siti@pustaka.id','scrypt:32768:8:1$aXjHuoydIiVMq23C$0f9063890cabf4886a43ffdbb14e3ebb7ec154b1fb519e2365ec5f07b986ee93d95206f20739bdd557cad42e73d1c9ac414c0570a8fae63ca7f0ceb231841da5','anggota','AG0004','081298765432','Bekasi','aktif','2026-10-23 09:45:31');
INSERT INTO "users" VALUES(5,'Rahmat Hidayat','rahmat@pustaka.id','scrypt:32768:8:1$aXjHuoydIiVMq23C$0f9063890cabf4886a43ffdbb14e3ebb7ec154b1fb519e2365ec5f07b986ee93d95206f20739bdd557cad42e73d1c9ac414c0570a8fae63ca7f0ceb231841da5','anggota','AG0005','081311122233','Depok','aktif','2026-10-23 09:45:31');
INSERT INTO "users" VALUES(6,'Dewi Lestari','dewi@pustaka.id','scrypt:32768:8:1$aXjHuoydIiVMq23C$0f9063890cabf4886a43ffdbb14e3ebb7ec154b1fb519e2365ec5f07b986ee93d95206f20739bdd557cad42e73d1c9ac414c0570a8fae63ca7f0ceb231841da5','anggota','AG0006','081355566677','Bogor','aktif','2026-10-23 09:45:31');
INSERT INTO "users" VALUES(7,'Andi Pratama','andi@pustaka.id','scrypt:32768:8:1$aXjHuoydIiVMq23C$0f9063890cabf4886a43ffdbb14e3ebb7ec154b1fb519e2365ec5f07b986ee93d95206f20739bdd557cad42e73d1c9ac414c0570a8fae63ca7f0ceb231841da5','anggota','AG0007','081377788899','Tangerang','aktif','2026-10-23 09:45:31');
CREATE INDEX idx_books_title   ON books(title);
CREATE INDEX idx_books_cat     ON books(category_id);
CREATE INDEX idx_loans_member  ON loans(member_id, status);
CREATE INDEX idx_loans_book    ON loans(book_id, status);
CREATE INDEX idx_loans_due     ON loans(due_date);
DELETE FROM "sqlite_sequence";
INSERT INTO "sqlite_sequence" VALUES('users',7);
INSERT INTO "sqlite_sequence" VALUES('categories',5);
INSERT INTO "sqlite_sequence" VALUES('books',12);
INSERT INTO "sqlite_sequence" VALUES('loans',11);
COMMIT;
