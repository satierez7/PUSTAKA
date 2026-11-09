<?php
require __DIR__ . '/config.php';
require_login(array('anggota'));
$R = ''; $title = 'Katalog Buku';

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$kat = (int)(isset($_GET['kategori']) ? $_GET['kategori'] : 0);
$sql = 'SELECT b.*, c.nama AS kategori FROM books b JOIN categories c ON c.id = b.category_id WHERE 1=1';
$p = array();
if ($q !== '') { $sql .= ' AND (b.judul LIKE ? OR b.penulis LIKE ? OR c.nama LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; $p[] = "%$q%"; }
if ($kat) { $sql .= ' AND b.category_id = ?'; $p[] = $kat; }
$sql .= ' ORDER BY b.judul LIMIT 60';
$st = $pdo->prepare($sql); $st->execute($p);
$books = $st->fetchAll();
$cats = $pdo->query('SELECT * FROM categories ORDER BY nama')->fetchAll();

$st = $pdo->prepare("SELECT l.*, b.judul FROM loans l JOIN books b ON b.id = l.book_id WHERE l.user_id = ? AND l.status IN ('menunggu','dipinjam') ORDER BY l.id DESC");
$st->execute(array(user()['id']));
$aktif = $st->fetchAll();

ob_start(); ?>
<div class="card">
    <h2>Pinjaman Aktif</h2>
    <?php if (!$aktif): ?><p class="mut">Belum ada pinjaman aktif.</p><?php endif; ?>
    <?php foreach ($aktif as $l): ?>
        <div class="li"><b><?= e($l['judul']) ?></b>
            <div class="mut"><?= $l['jatuh_tempo'] ? 'Jatuh tempo ' . tgl($l['jatuh_tempo']) : 'Menunggu persetujuan' ?></div>
            <?= badge($l) ?>
        </div>
    <?php endforeach; ?>
    <div class="mut" style="margin-top:12px">Kuota pinjam: <?= count($aktif) ?> dari <?= MAKS_PINJAM ?> buku</div>
</div>
<?php $aside = ob_get_clean();

include __DIR__ . '/partials/header.php'; ?>
<h1>Katalog Buku</h1>
<p class="sub">Cari dan pinjam buku favoritmu</p>
<form class="row" style="margin-bottom:24px" method="get">
    <input class="in" style="flex:1;min-width:200px;margin:0" name="q" value="<?= e($q) ?>" aria-label="Cari" placeholder="Cari judul atau penulis">
    <select class="in" style="width:200px;margin:0" name="kategori" aria-label="Kategori" onchange="this.form.submit()">
        <option value="">Semua kategori</option>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $kat == $c['id'] ? 'selected' : '' ?>><?= e($c['nama']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn">Cari</button>
</form>
<div class="books">
    <?php foreach ($books as $b) { echo book_card($b); } ?>
    <?php if (!$books): ?><p class="mut">Buku tidak ditemukan.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
