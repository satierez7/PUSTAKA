<?php
require __DIR__ . '/config.php';
require_login(array('anggota'));
$R = ''; $title = 'Pinjaman Saya';
$tab = (isset($_GET['tab']) && $_GET['tab'] === 'riwayat') ? 'riwayat' : 'aktif';
$status = $tab === 'riwayat' ? "('dikembalikan','ditolak')" : "('menunggu','dipinjam')";
$st = $pdo->prepare("SELECT l.*, b.judul FROM loans l JOIN books b ON b.id = l.book_id WHERE l.user_id = ? AND l.status IN $status ORDER BY l.id DESC");
$st->execute(array(user()['id']));
$loans = $st->fetchAll();
include __DIR__ . '/partials/header.php'; ?>
<h1>Pinjaman Saya</h1>
<p class="sub">Pantau buku yang sedang kamu pinjam</p>
<div class="row" style="margin-bottom:16px">
    <a class="<?= $tab === 'aktif' ? 'btn' : 'btn2' ?>" href="pinjaman_saya.php">Aktif</a>
    <a class="<?= $tab === 'riwayat' ? 'btn' : 'btn2' ?>" href="pinjaman_saya.php?tab=riwayat">Riwayat</a>
</div>
<?php if (!$loans): ?><p class="mut">Belum ada data.</p><?php endif; ?>
<?php foreach ($loans as $l): $d = denda_loan($l); ?>
<div class="card" style="margin-bottom:12px">
    <div class="row" style="justify-content:space-between">
        <div>
            <b><?= e($l['judul']) ?></b>
            <div class="mut">
                <?php if ($l['tgl_pinjam']): ?>
                    Dipinjam <?= tgl($l['tgl_pinjam']) ?> · Jatuh tempo <?= tgl($l['jatuh_tempo']) ?>
                    <?php if ($l['tgl_kembali']): ?> · Kembali <?= tgl($l['tgl_kembali']) ?><?php endif; ?>
                <?php else: ?>Diajukan <?= tgl($l['created_at']) ?><?php endif; ?>
            </div>
        </div>
        <div class="row">
            <?php if ($d > 0): ?><span class="mut">Denda <?= rupiah($d) ?></span><?php endif; ?>
            <?= badge($l) ?>
            <?php if ($l['status'] === 'dipinjam' && hari_terlambat($l) > 0): ?><span class="mut"><?= hari_terlambat($l) ?> hari</span><?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<p class="mut" style="margin-top:16px">Maksimal <?= MAKS_PINJAM ?> buku sekaligus · Lama pinjam <?= LAMA_PINJAM ?> hari · Denda <?= rupiah(DENDA_PER_HARI) ?> per hari</p>
<?php include __DIR__ . '/partials/footer.php'; ?>
