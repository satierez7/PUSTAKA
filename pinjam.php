<?php
require __DIR__ . '/config.php';
require_login(array('anggota'));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('katalog.php'); }
csrf_check();

$uid = user()['id'];
$bid = (int)(isset($_POST['book_id']) ? $_POST['book_id'] : 0);

$st = $pdo->prepare('SELECT * FROM books WHERE id = ?'); $st->execute(array($bid));
$book = $st->fetch();
if (!$book) { flash('error', 'Buku tidak ditemukan.'); redirect('katalog.php'); }

$st = $pdo->prepare("SELECT book_id FROM loans WHERE user_id = ? AND status IN ('menunggu','dipinjam')");
$st->execute(array($uid));
$aktif = $st->fetchAll(PDO::FETCH_COLUMN);

if (count($aktif) >= MAKS_PINJAM) {
    flash('error', 'Batas pinjam ' . MAKS_PINJAM . ' buku sudah tercapai.');
} elseif (in_array($bid, $aktif)) {
    flash('error', 'Buku ini sedang kamu pinjam atau ajukan.');
} elseif ($book['stok'] < 1) {
    flash('error', 'Stok buku habis.');
} else {
    $pdo->prepare("INSERT INTO loans (user_id, book_id, status) VALUES (?, ?, 'menunggu')")->execute(array($uid, $bid));
    flash('success', 'Pengajuan terkirim. Tunggu persetujuan petugas.');
}
redirect('katalog.php');
