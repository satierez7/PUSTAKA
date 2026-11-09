<?php
require __DIR__ . '/config.php';
if (!user()) { redirect('login.php'); }
redirect(is_staff() ? 'admin/dashboard.php' : 'katalog.php');
