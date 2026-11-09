<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $title = 'Kategori';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete'])) {
        $id = (int)$_POST['delete'];
        $c = $pdo->prepare('SELECT COUNT(*) FROM books WHERE category_id = ?'); $c->execute(array($id));
        if ($c->fetchColumn() > 0) { flash('error', 'Kategori masih dipakai oleh buku.'); }
        else { $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute(array($id)); flash('success', 'Kategori dihapus.'); }
    } else {
        $nama = trim(isset($_POST['nama']) ? $_POST['nama'] : '');
        $c = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE nama = ?'); $c->execute(array($nama));
        if ($nama === '') { flash('error', 'Nama kategori wajib diisi.'); }
        elseif ($c->fetchColumn() > 0) { flash('error', 'Kategori sudah ada.'); }
        else { $pdo->prepare('INSERT INTO categories (nama) VALUES (?)')->execute(array($nama)); flash('success', 'Kategori ditambahkan.'); }
    }
    redirect('categories.php');
}
$cats = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM books b WHERE b.category_id=c.id) AS jml FROM categories c ORDER BY c.nama')->fetchAll();
include __DIR__ . '/../partials/header.php'; ?>
<h1>Kategori</h1>
<p class="sub">Kelompokkan buku berdasarkan kategori</p>
<div class="two">
<div class="card" style="min-width:0"><div class="tw"><table>
    <tr><th>Nama</th><th>Jumlah buku</th><th>Aksi</th></tr>
    <?php foreach ($cats as $c): ?>
    <tr><td><b><?= e($c['nama']) ?></b></td><td><?= (int)$c['jml'] ?></td>
        <td><form method="post" onsubmit="return confirm('Hapus kategori ini?')"><?= csrf_field() ?><input type="hidden" name="delete" value="<?= $c['id'] ?>"><button class="btn2 sm dng">Hapus</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$cats): ?><tr><td colspan="3" class="mut">Belum ada kategori.</td></tr><?php endif; ?>
</table></div></div>
<div class="card">
    <form method="post"><?= csrf_field() ?>
        <label for="nama">Kategori baru</label>
        <input class="in" id="nama" name="nama" required>
        <button class="btn">Tambah</button>
    </form>
</div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
