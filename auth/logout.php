<?php
// ============================================================
// auth/logout.php  –  Destroy session and redirect
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

session_unset();
session_destroy();
setcookie(SESSION_NAME, '', time() - 3600, '/');

header('Location: ' . SITE_URL . '/auth/login.php');
exit;
