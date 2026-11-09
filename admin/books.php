<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $title = 'Data Buku';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    csrf_check();
    $id = (int)$_POST['delete'];
    $c = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE book_id = ?'); $c->execute(array($id));
    if ($c->fetchColumn() > 0) {
        flash('error', 'Buku tidak bisa dihapus karena sudah punya riwayat peminjaman.');
    } else {
        $pdo->prepare('DELETE FROM books WHERE id = ?')->execute(array($id));
        flash('success', 'Buku dihapus.');
    }
    redirect('books.php');
}

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$sql = 'SELECT b.*, c.nama AS kategori FROM books b JOIN categories c ON c.id=b.category_id';
$p = array();
if ($q !== '') { $sql .= ' WHERE b.judul LIKE ? OR b.penulis LIKE ?'; $p = array("%$q%", "%$q%"); }
$st = $pdo->prepare($sql . ' ORDER BY b.judul'); $st->execute($p);
$books = $st->fetchAll();
include __DIR__ . '/../partials/header.php'; ?>
<div class="row" style="justify-content:space-between"><div><h1>Data Buku</h1><p class="sub">Kelola koleksi perpustakaan</p></div>
    <a class="btn" href="book_form.php">+ Tambah Buku</a></div>
<form class="row" style="margin-bottom:16px" method="get">
    <input class="in" style="flex:1;min-width:200px;margin:0" name="q" value="<?= e($q) ?>" aria-label="Cari" placeholder="Cari judul atau penulis">
    <button class="btn">Cari</button>
</form>
<div class="card"><div class="tw"><table>
    <tr><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Tahun</th><th>Stok</th><th>Aksi</th></tr>
    <?php foreach ($books as $b): ?>
    <tr>
        <td><b><?= e($b['judul']) ?></b></td><td><?= e($b['penulis']) ?></td><td><?= e($b['kategori']) ?></td><td><?= e($b['tahun']) ?></td>
        <td><span class="b <?= $b['stok'] > 0 ? 'ok' : 'er' ?>"><?= (int)$b['stok'] ?></span></td>
        <td><div class="row">
            <a class="btn2 sm" href="book_form.php?id=<?= $b['id'] ?>">Edit</a>
            <form method="post" onsubmit="return confirm('Hapus buku ini?')"><?= csrf_field() ?><input type="hidden" name="delete" value="<?= $b['id'] ?>"><button class="btn2 sm dng">Hapus</button></form>
        </div></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$books): ?><tr><td colspan="6" class="mut">Belum ada buku.</td></tr><?php endif; ?>
</table></div></div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
