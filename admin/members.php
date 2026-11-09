<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $title = 'Anggota';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    csrf_check();
    $id = (int)$_POST['delete'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id = ?'); $c->execute(array($id));
    if ($c->fetchColumn() > 0) { flash('error', 'Anggota tidak bisa dihapus karena punya riwayat peminjaman.'); }
    else { $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'anggota'")->execute(array($id)); flash('success', 'Anggota dihapus.'); }
    redirect('members.php');
}

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$sql = "SELECT u.*, (SELECT COUNT(*) FROM loans l WHERE l.user_id=u.id AND l.status IN ('menunggu','dipinjam')) AS aktif FROM users u WHERE u.role='anggota'";
$p = array();
if ($q !== '') { $sql .= ' AND (u.name LIKE ? OR u.nis LIKE ?)'; $p = array("%$q%", "%$q%"); }
$st = $pdo->prepare($sql . ' ORDER BY u.name'); $st->execute($p);
$members = $st->fetchAll();
include __DIR__ . '/../partials/header.php'; ?>
<div class="row" style="justify-content:space-between"><div><h1>Anggota</h1><p class="sub">Kelola data anggota perpustakaan</p></div>
    <a class="btn" href="member_form.php">+ Tambah Anggota</a></div>
<form class="row" style="margin-bottom:16px" method="get">
    <input class="in" style="flex:1;min-width:200px;margin:0" name="q" value="<?= e($q) ?>" aria-label="Cari" placeholder="Cari nama atau NIS">
    <button class="btn">Cari</button>
</form>
<div class="card"><div class="tw"><table>
    <tr><th>Nama</th><th>NIS</th><th>Email</th><th>No. HP</th><th>Pinjaman aktif</th><th>Aksi</th></tr>
    <?php foreach ($members as $m): ?>
    <tr><td><b><?= e($m['name']) ?></b></td><td><?= e($m['nis']) ?></td><td><?= e($m['email']) ?></td><td><?= e($m['no_hp']) ?></td><td><?= (int)$m['aktif'] ?></td>
        <td><div class="row">
            <a class="btn2 sm" href="member_form.php?id=<?= $m['id'] ?>">Edit</a>
            <form method="post" onsubmit="return confirm('Hapus anggota ini?')"><?= csrf_field() ?><input type="hidden" name="delete" value="<?= $m['id'] ?>"><button class="btn2 sm dng">Hapus</button></form>
        </div></td></tr>
    <?php endforeach; ?>
    <?php if (!$members): ?><tr><td colspan="6" class="mut">Belum ada anggota.</td></tr><?php endif; ?>
</table></div></div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
