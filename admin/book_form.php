<?php
require __DIR__ . '/../config.php';
require_login(array('admin', 'petugas'), '../');
$R = '../'; $active = 'books.php';
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$b = array('category_id' => '', 'judul' => '', 'penulis' => '', 'penerbit' => '', 'tahun' => '', 'stok' => 0);
if ($id) {
    $st = $pdo->prepare('SELECT * FROM books WHERE id = ?'); $st->execute(array($id));
    $b = $st->fetch();
    if (!$b) { flash('error', 'Buku tidak ditemukan.'); redirect('books.php'); }
}
$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (array('category_id', 'judul', 'penulis', 'penerbit', 'tahun', 'stok') as $k) { $b[$k] = trim(isset($_POST[$k]) ? $_POST[$k] : ''); }
    if ($b['judul'] === '') { $errors[] = 'Judul wajib diisi.'; }
    if ($b['penulis'] === '') { $errors[] = 'Penulis wajib diisi.'; }
    if (!$b['category_id']) { $errors[] = 'Kategori wajib dipilih.'; }
    if ($b['stok'] === '' || !ctype_digit((string)$b['stok'])) { $errors[] = 'Stok harus berupa angka 0 atau lebih.'; }
    if ($b['tahun'] !== '' && (!ctype_digit((string)$b['tahun']) || $b['tahun'] < 1000 || $b['tahun'] > date('Y'))) { $errors[] = 'Tahun terbit tidak valid.'; }
    if (!$errors) {
        $vals = array((int)$b['category_id'], $b['judul'], $b['penulis'], $b['penerbit'] ?: null, $b['tahun'] !== '' ? (int)$b['tahun'] : null, (int)$b['stok']);
        if ($id) {
            $pdo->prepare('UPDATE books SET category_id=?, judul=?, penulis=?, penerbit=?, tahun=?, stok=? WHERE id=?')->execute(array_merge($vals, array($id)));
            flash('success', 'Buku diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO books (category_id,judul,penulis,penerbit,tahun,stok) VALUES (?,?,?,?,?,?)')->execute($vals);
            flash('success', 'Buku ditambahkan.');
        }
        redirect('books.php');
    }
}
$cats = $pdo->query('SELECT * FROM categories ORDER BY nama')->fetchAll();
$title = $id ? 'Edit Buku' : 'Tambah Buku';
include __DIR__ . '/../partials/header.php'; ?>
<h1><?= e($title) ?></h1>
<p class="sub">Lengkapi data buku</p>
<?php if ($errors): ?><div class="alert er"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="card" style="max-width:680px">
<form method="post">
    <?= csrf_field() ?>
    <label for="judul">Judul</label>
    <input class="in" id="judul" name="judul" value="<?= e($b['judul']) ?>" required>
    <div class="fg">
        <div><label for="penulis">Penulis</label><input class="in" id="penulis" name="penulis" value="<?= e($b['penulis']) ?>" required></div>
        <div><label for="penerbit">Penerbit</label><input class="in" id="penerbit" name="penerbit" value="<?= e($b['penerbit']) ?>"></div>
        <div><label for="category_id">Kategori</label>
            <select class="in" id="category_id" name="category_id" required>
                <option value="">Pilih kategori</option>
                <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $b['category_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nama']) ?></option><?php endforeach; ?>
            </select></div>
        <div><label for="tahun">Tahun terbit</label><input class="in" id="tahun" name="tahun" type="number" value="<?= e($b['tahun']) ?>"></div>
        <div><label for="stok">Stok</label><input class="in" id="stok" name="stok" type="number" min="0" value="<?= e($b['stok']) ?>" required></div>
    </div>
    <div class="row"><button class="btn">Simpan</button><a class="btn2" href="books.php">Batal</a></div>
</form></div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
