<?php
require __DIR__ . '/config.php';
if (user()) { redirect('index.php'); }
$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute(array(trim(isset($_POST['email']) ? $_POST['email'] : '')));
    $u = $st->fetch();
    if ($u && password_verify(isset($_POST['password']) ? $_POST['password'] : '', $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = array('id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']);
        redirect('index.php');
    }
    $errors[] = 'Email atau kata sandi salah.';
}
$title = 'Masuk'; $heading = 'Masuk Sekarang'; $sub = 'Pinjam buku favoritmu dengan mudah';
$altlink = 'register.php'; $alttext = 'Daftar';
include __DIR__ . '/partials/auth_header.php';
?>
<form method="post">
    <?= csrf_field() ?>
    <label for="email">Email</label>
    <input class="in" id="email" name="email" type="email" value="<?= e(isset($_POST['email']) ? $_POST['email'] : '') ?>" required autofocus>
    <label for="password">Kata sandi</label>
    <input class="in" id="password" name="password" type="password" required>
    <button class="btn" style="width:100%">Masuk</button>
    <p class="mut" style="text-align:center;margin-top:16px">Demo: anggota@perpus.test, petugas@perpus.test, admin@perpus.test · sandi: password</p>
</form>
<?php include __DIR__ . '/partials/auth_footer.php'; ?>
