<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $title = 'Laporan';
$dari = isset($_GET['dari']) ? $_GET['dari'] : '';
$sampai = isset($_GET['sampai']) ? $_GET['sampai'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sql = 'SELECT l.*, u.name AS anggota, b.judul FROM loans l JOIN users u ON u.id=l.user_id JOIN books b ON b.id=l.book_id WHERE 1=1';
$p = array();
if ($dari !== '') { $sql .= ' AND DATE(l.created_at) >= ?'; $p[] = $dari; }
if ($sampai !== '') { $sql .= ' AND DATE(l.created_at) <= ?'; $p[] = $sampai; }
if (in_array($status, array('menunggu', 'dipinjam', 'dikembalikan', 'ditolak'))) { $sql .= ' AND l.status = ?'; $p[] = $status; }
$st = $pdo->prepare($sql . ' ORDER BY l.id DESC'); $st->execute($p);
$loans = $st->fetchAll();
$totalDenda = 0; foreach ($loans as $l) { $totalDenda += denda_loan($l); }
include __DIR__ . '/../partials/header.php'; ?>
<h1>Laporan Peminjaman</h1>
<p class="sub">Filter berdasarkan tanggal pengajuan, lalu cetak</p>
<form class="row noprint" style="margin-bottom:16px" method="get">
    <div><label for="dari">Dari</label><input class="in" style="margin:4px 0 0" id="dari" type="date" name="dari" value="<?= e($dari) ?>"></div>
    <div><label for="sampai">Sampai</label><input class="in" style="margin:4px 0 0" id="sampai" type="date" name="sampai" value="<?= e($sampai) ?>"></div>
    <div><label for="status">Status</label>
        <select class="in" style="margin:4px 0 0" id="status" name="status">
            <option value="">Semua</option>
            <?php foreach (array('menunggu', 'dipinjam', 'dikembalikan', 'ditolak') as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select></div>
    <button class="btn" style="align-self:flex-end">Tampilkan</button>
    <button type="button" class="btn2" style="align-self:flex-end" onclick="window.print()">Cetak</button>
</form>
<div class="card"><div class="tw"><table>
    <tr><th>No</th><th>Anggota</th><th>Buku</th><th>Tgl pinjam</th><th>Jatuh tempo</th><th>Kembali</th><th>Status</th><th>Denda</th></tr>
    <?php foreach ($loans as $i => $l): ?>
    <tr><td><?= $i + 1 ?></td><td><?= e($l['anggota']) ?></td><td><?= e($l['judul']) ?></td>
        <td><?= tgl($l['tgl_pinjam']) ?></td><td><?= tgl($l['jatuh_tempo']) ?></td><td><?= tgl($l['tgl_kembali']) ?></td>
        <td><?= badge($l) ?></td><td><?= rupiah(denda_loan($l)) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="8" class="mut">Tidak ada data pada filter ini.</td></tr><?php endif; ?>
</table></div>
<p style="margin:16px 0 0"><b>Total transaksi:</b> <?= count($loans) ?> · <b>Total denda:</b> <?= rupiah($totalDenda) ?></p>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
