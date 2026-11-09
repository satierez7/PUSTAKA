<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $title = 'Pengembalian';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT * FROM loans WHERE id = ? FOR UPDATE'); $st->execute(array($id));
    $loan = $st->fetch();
    if (!$loan || $loan['status'] !== 'dipinjam') {
        $pdo->rollBack();
        flash('error', 'Buku ini tidak sedang dipinjam.');
    } else {
        $denda = hari_terlambat($loan) * DENDA_PER_HARI; // dihitung sebelum tgl_kembali diisi
        $pdo->prepare("UPDATE loans SET status='dikembalikan', tgl_kembali=CURDATE(), denda=? WHERE id=?")->execute(array($denda, $id));
        $pdo->prepare('UPDATE books SET stok = stok + 1 WHERE id = ?')->execute(array($loan['book_id']));
        $pdo->commit();
        flash('success', 'Pengembalian diproses.' . ($denda > 0 ? ' Denda: ' . rupiah($denda) : ''));
    }
    redirect('returns.php');
}

$loans = $pdo->query("SELECT l.*, u.name AS anggota, b.judul FROM loans l JOIN users u ON u.id=l.user_id JOIN books b ON b.id=l.book_id WHERE l.status='dipinjam' ORDER BY l.jatuh_tempo")->fetchAll();
include __DIR__ . '/../partials/header.php'; ?>
<h1>Pengembalian</h1>
<p class="sub">Proses buku yang dikembalikan anggota. Denda <?= rupiah(DENDA_PER_HARI) ?> per hari keterlambatan.</p>
<div class="card"><div class="tw"><table>
    <tr><th>Anggota</th><th>Buku</th><th>Tgl pinjam</th><th>Jatuh tempo</th><th>Terlambat</th><th>Denda</th><th>Aksi</th></tr>
    <?php foreach ($loans as $l): $h = hari_terlambat($l); ?>
    <tr><td><?= e($l['anggota']) ?></td><td><b><?= e($l['judul']) ?></b></td><td><?= tgl($l['tgl_pinjam']) ?></td><td><?= tgl($l['jatuh_tempo']) ?></td>
        <td><?= $h > 0 ? '<span class="b er">' . $h . ' hari</span>' : '<span class="b ok">Tepat waktu</span>' ?></td>
        <td><?= rupiah(denda_loan($l)) ?></td>
        <td><form method="post" onsubmit="return confirm('Proses pengembalian buku ini?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><button class="btn sm">Proses</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="7" class="mut">Tidak ada buku yang sedang dipinjam.</td></tr><?php endif; ?>
</table></div></div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
