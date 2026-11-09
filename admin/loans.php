<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $title = 'Peminjaman';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $back = (isset($_POST['back']) && $_POST['back'] === 'dashboard.php') ? 'dashboard.php' : 'loans.php';

    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT * FROM loans WHERE id = ? FOR UPDATE'); $st->execute(array($id));
    $loan = $st->fetch();
    if (!$loan || $loan['status'] !== 'menunggu') {
        $pdo->rollBack();
        flash('error', 'Pengajuan tidak ditemukan atau sudah diproses.');
    } elseif ($action === 'approve') {
        $sb = $pdo->prepare('SELECT stok FROM books WHERE id = ? FOR UPDATE'); $sb->execute(array($loan['book_id']));
        if ($sb->fetchColumn() < 1) {
            $pdo->rollBack();
            flash('error', 'Stok buku habis, pengajuan tidak bisa disetujui.');
        } else {
            $pdo->prepare('UPDATE books SET stok = stok - 1 WHERE id = ?')->execute(array($loan['book_id']));
            $pdo->prepare("UPDATE loans SET status='dipinjam', tgl_pinjam=CURDATE(), jatuh_tempo=DATE_ADD(CURDATE(), INTERVAL " . (int)LAMA_PINJAM . " DAY) WHERE id=?")->execute(array($id));
            $pdo->commit();
            flash('success', 'Peminjaman disetujui.');
        }
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE loans SET status='ditolak' WHERE id=?")->execute(array($id));
        $pdo->commit();
        flash('success', 'Pengajuan ditolak.');
    } else {
        $pdo->rollBack();
    }
    redirect($back);
}

$status = isset($_GET['status']) ? $_GET['status'] : '';
$sql = 'SELECT l.*, u.name AS anggota, b.judul FROM loans l JOIN users u ON u.id=l.user_id JOIN books b ON b.id=l.book_id';
$p = array();
if (in_array($status, array('menunggu', 'dipinjam', 'dikembalikan', 'ditolak'))) { $sql .= ' WHERE l.status = ?'; $p[] = $status; }
$st = $pdo->prepare($sql . ' ORDER BY l.id DESC'); $st->execute($p);
$loans = $st->fetchAll();
include __DIR__ . '/../partials/header.php'; ?>
<h1>Peminjaman</h1>
<p class="sub">Setujui atau tolak pengajuan pinjam</p>
<form class="row" style="margin-bottom:16px" method="get">
    <select class="in" style="width:220px;margin:0" name="status" aria-label="Status" onchange="this.form.submit()">
        <option value="">Semua status</option>
        <?php foreach (array('menunggu' => 'Menunggu', 'dipinjam' => 'Dipinjam', 'dikembalikan' => 'Dikembalikan', 'ditolak' => 'Ditolak') as $v => $t): ?>
            <option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
    </select>
</form>
<div class="card"><div class="tw"><table>
    <tr><th>Anggota</th><th>Buku</th><th>Diajukan</th><th>Jatuh tempo</th><th>Status</th><th>Aksi</th></tr>
    <?php foreach ($loans as $l): ?>
    <tr><td><?= e($l['anggota']) ?></td><td><?= e($l['judul']) ?></td><td><?= tgl($l['created_at']) ?></td><td><?= tgl($l['jatuh_tempo']) ?></td>
        <td><?= badge($l) ?></td>
        <td><?php if ($l['status'] === 'menunggu'): ?>
            <div class="row">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn sm">Setujui</button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn2 sm dng">Tolak</button></form>
            </div>
        <?php else: ?><span class="mut">-</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="6" class="mut">Belum ada data.</td></tr><?php endif; ?>
</table></div></div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
