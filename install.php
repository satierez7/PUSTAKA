<?php
define('NO_DB', true);
require __DIR__ . '/config.php';

$log = array();
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . DB_NAME . '`');
        $log[] = 'Database "' . DB_NAME . '" siap.';

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (array('loans', 'books', 'categories', 'users') as $t) { $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`'); }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $pdo->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','petugas','anggota') NOT NULL DEFAULT 'anggota',
            nis VARCHAR(30) NULL,
            no_hp VARCHAR(20) NULL,
            alamat VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE books (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NOT NULL,
            judul VARCHAR(200) NOT NULL,
            penulis VARCHAR(150) NOT NULL,
            penerbit VARCHAR(150) NULL,
            tahun SMALLINT NULL,
            stok INT NOT NULL DEFAULT 0,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE loans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            book_id INT NOT NULL,
            tgl_pinjam DATE NULL,
            jatuh_tempo DATE NULL,
            tgl_kembali DATE NULL,
            status ENUM('menunggu','dipinjam','dikembalikan','ditolak') NOT NULL DEFAULT 'menunggu',
            denda INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (book_id) REFERENCES books(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $log[] = '4 tabel berhasil dibuat.';

        $hash = password_hash('password', PASSWORD_DEFAULT);
        $iu = $pdo->prepare('INSERT INTO users (name,email,password,role,nis,no_hp,alamat) VALUES (?,?,?,?,?,?,?)');
        $iu->execute(array('Admin', 'admin@perpus.test', $hash, 'admin', null, null, null));
        $iu->execute(array('Petugas', 'petugas@perpus.test', $hash, 'petugas', null, null, null));
        foreach (array(array('Budi Santoso', 'anggota@perpus.test', '2024001'), array('Rizky Pratama', 'rizky@perpus.test', '2024002'), array('Dimas Saputra', 'dimas@perpus.test', '2024003')) as $a) {
            $iu->execute(array($a[0], $a[1], $hash, 'anggota', $a[2], '081234567890', 'Jakarta'));
        }

        $ic = $pdo->prepare('INSERT INTO categories (nama) VALUES (?)');
        $cat = array();
        foreach (array('Novel', 'Sains', 'Pengembangan Diri', 'Sejarah') as $n) { $ic->execute(array($n)); $cat[$n] = $pdo->lastInsertId(); }

        $data = array(
            array('Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', 2005, 'Novel', 4),
            array('Bumi Manusia', 'Pramoedya Ananta Toer', 'Hasta Mitra', 1980, 'Novel', 2),
            array('Atomic Habits', 'James Clear', 'Gramedia', 2019, 'Pengembangan Diri', 3),
            array('Filosofi Teras', 'Henry Manampiring', 'Kompas', 2018, 'Pengembangan Diri', 5),
            array('Sapiens', 'Yuval Noah Harari', 'KPG', 2017, 'Sejarah', 2),
            array('Negeri 5 Menara', 'Ahmad Fuadi', 'Gramedia', 2009, 'Novel', 3),
            array('Ronggeng Dukuh Paruk', 'Ahmad Tohari', 'Gramedia', 2003, 'Novel', 2),
            array('Dilan 1990', 'Pidi Baiq', 'Pastel Books', 2014, 'Novel', 6),
            array('Cantik Itu Luka', 'Eka Kurniawan', 'Gramedia', 2002, 'Novel', 1),
            array('Rich Dad Poor Dad', 'Robert Kiyosaki', 'Gramedia', 2000, 'Pengembangan Diri', 4),
            array('Pulang', 'Tere Liye', 'Republika', 2015, 'Novel', 3),
            array('Cosmos', 'Carl Sagan', 'Random House', 1980, 'Sains', 2),
        );
        $ib = $pdo->prepare('INSERT INTO books (category_id,judul,penulis,penerbit,tahun,stok) VALUES (?,?,?,?,?,?)');
        foreach ($data as $d) { $ib->execute(array($cat[$d[4]], $d[0], $d[1], $d[2], $d[3], $d[5])); }

        // Contoh transaksi: user 3-5 adalah anggota, buku 1-12
        $il = $pdo->prepare('INSERT INTO loans (user_id,book_id,tgl_pinjam,jatuh_tempo,status) VALUES (?,?,?,?,?)');
        $il->execute(array(3, 2, date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+5 days')), 'dipinjam'));
        $il->execute(array(4, 3, date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-3 days')), 'dipinjam'));
        $pdo->exec('UPDATE books SET stok = stok - 1 WHERE id IN (2,3)');
        $il->execute(array(3, 1, null, null, 'menunggu'));
        $il->execute(array(5, 5, null, null, 'menunggu'));
        $log[] = 'Data contoh (akun, kategori, buku, transaksi) berhasil dimasukkan.';
        $done = true;
    } catch (PDOException $ex) {
        $log[] = 'GAGAL: ' . $ex->getMessage();
    }
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalasi - Pustaka</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body><div class="frame" style="align-items:center;justify-content:center;background:#D9C7A0;padding:24px">
<div class="card" style="max-width:520px;width:100%">
<h1>Instalasi Pustaka</h1>
<p class="sub">Membuat database <b><?= e(DB_NAME) ?></b> beserta tabel dan data contoh.</p>
<?php foreach ($log as $l): ?><div class="alert <?= strpos($l, 'GAGAL') === 0 ? 'er' : 'ok' ?>"><?= e($l) ?></div><?php endforeach; ?>
<?php if ($done): ?>
    <p>Selesai. Login dengan sandi <b>password</b>:</p>
    <p class="mut">admin@perpus.test (Admin)<br>petugas@perpus.test (Petugas)<br>anggota@perpus.test (Anggota)</p>
    <a class="btn" href="login.php">Ke halaman login</a>
    <p class="mut" style="margin-top:16px">Setelah selesai, hapus file install.php agar tidak dijalankan ulang.</p>
<?php else: ?>
    <div class="alert wa">Perhatian: tabel lama (jika ada) akan dihapus dan dibuat ulang.</div>
    <form method="post"><button class="btn">Pasang database</button></form>
    <p class="mut" style="margin-top:12px">Pastikan MySQL sudah menyala. Atur user dan sandi MySQL di config.php.</p>
<?php endif; ?>
</div></div></body></html>
