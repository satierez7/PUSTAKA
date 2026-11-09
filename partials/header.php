<?php
// Butuh: $R (awalan path), $title. Opsional: $active (nama file menu aktif)
$u = user();
$active = isset($active) ? $active : basename($_SERVER['SCRIPT_NAME']);
if ($u['role'] === 'anggota') {
    $nav = array(
        array('JELAJAH', array(array('katalog.php', 'Katalog Buku'))),
        array('BUKU SAYA', array(array('pinjaman_saya.php', 'Pinjaman Saya'))),
    );
} else {
    $nav = array(
        array('UTAMA', array(array('admin/dashboard.php', 'Dashboard'))),
        array('DATA', array(array('admin/books.php', 'Data Buku'), array('admin/categories.php', 'Kategori'), array('admin/members.php', 'Anggota'))),
        array('TRANSAKSI', array(array('admin/loans.php', 'Peminjaman'), array('admin/returns.php', 'Pengembalian'))),
        array('LAPORAN', array(array('admin/reports.php', 'Laporan'))),
    );
}
$f = flash();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Pustaka</title>
<link rel="stylesheet" href="<?= $R ?>assets/css/app.css">
</head>
<body>
<div class="frame">
    <div class="top">
        <div class="logo">Pustaka</div>
        <?php if ($u['role'] === 'anggota'): ?>
        <form class="srchf" action="<?= $R ?>katalog.php" method="get">
            <input class="srch" name="q" value="<?= e(isset($_GET['q']) ? $_GET['q'] : '') ?>" aria-label="Cari buku" placeholder="Cari buku, penulis, kategori">
        </form>
        <?php endif; ?>
        <div class="user">
            <span><?= e($u['name']) ?> · <?= e(ucfirst($u['role'])) ?></span>
            <div class="av"><?= e(strtoupper(substr($u['name'], 0, 2))) ?></div>
            <form method="post" action="<?= $R ?>logout.php"><?= csrf_field() ?><button class="btn2 lo">Keluar</button></form>
        </div>
    </div>
    <div class="app">
        <div class="side">
            <?php foreach ($nav as $g): ?>
                <div class="sec"><?= e($g[0]) ?></div>
                <?php foreach ($g[1] as $it): ?>
                    <a class="<?= basename($it[0]) === $active ? 'nav on' : 'nav' ?>" href="<?= $R . $it[0] ?>"><?= e($it[1]) ?></a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <div class="main">
            <?php if ($f): ?><div class="alert <?= $f[0] === 'success' ? 'ok' : 'er' ?>"><?= e($f[1]) ?></div><?php endif; ?>
