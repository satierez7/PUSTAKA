<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $title = 'Dashboard';
$totalBuku = $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
$totalAnggota = $pdo->query("SELECT COUNT(*) FROM users WHERE role='anggota'")->fetchColumn();
$dipinjam = $pdo->query("SELECT COUNT(*) FROM loans WHERE status='dipinjam'")->fetchColumn();
$terlambat = $pdo->query("SELECT COUNT(*) FROM loans WHERE status='dipinjam' AND jatuh_tempo < CURDATE()")->fetchColumn();
$base = 'SELECT l.*, u.name AS anggota, b.judul FROM loans l JOIN users u ON u.id=l.user_id JOIN books b ON b.id=l.book_id';
$menunggu = $pdo->query($base . " WHERE l.status='menunggu' ORDER BY l.id DESC LIMIT 5")->fetchAll();
$terbaru = $pdo->query($base . ' ORDER BY l.id DESC LIMIT 6')->fetchAll();
include __DIR__ . '/../partials/header.php'; ?>
<h1>Dashboard</h1>
<p class="sub">Ringkasan perpustakaan hari ini</p>
<div class="grid">
    <div class="card"><div class="mut">Total buku</div><div class="num"><?= $totalBuku ?></div></div>
    <div class="card"><div class="mut">Anggota</div><div class="num"><?= $totalAnggota ?></div></div>
    <div class="card"><div class="mut">Sedang dipinjam</div><div class="num"><?= $dipinjam ?></div></div>
    <div class="card"><div class="mut">Terlambat</div><div class="num" style="color:#8A2A16"><?= $terlambat ?></div></div>
</div>
<div class="two">
    <div class="card" style="min-width:0">
        <div class="row" style="justify-content:space-between;margin-bottom:8px"><b>Peminjaman terbaru</b><a class="lnk" href="loans.php">Lihat semua</a></div>
        <div class="tw"><table>
            <tr><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th>Status</th></tr>
            <?php foreach ($terbaru as $l): ?>
            <tr><td><?= e($l['anggota']) ?></td><td><?= e($l['judul']) ?></td><td><?= tgl($l['jatuh_tempo']) ?></td><td><?= badge($l) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$terbaru): ?><tr><td colspan="4" class="mut">Belum ada data.</td></tr><?php endif; ?>
        </table></div>
    </div>
    <div class="card">
        <b>Perlu persetujuan</b>
        <?php if (!$menunggu): ?><p class="mut">Tidak ada pengajuan.</p><?php endif; ?>
        <?php foreach ($menunggu as $l): ?>
        <div class="li"><b><?= e($l['anggota']) ?></b><div class="mut"><?= e($l['judul']) ?></div>
            <form method="post" action="loans.php"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="back" value="dashboard.php"><button class="btn sm" style="margin-top:6px">Setujui</button></form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
