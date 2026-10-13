<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth/verifica_login.php';

// Base URL para links do sistema.
$hostAtual = $_SERVER['HTTP_HOST'] ?? '';
$ehLocal = (stripos($hostAtual, 'localhost') !== false || stripos($hostAtual, '127.0.0.1') !== false);
$baseUrl = $ehLocal ? '/Site' : '';

// Base relativa para arquivos estáticos.
// No InfinityFree o site fica na raiz do domínio; usamos caminho relativo
// para evitar problemas de URL/redirect do servidor.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($ehLocal) {
    $assetBase = '/Site';
} else {
    $cleanDir = trim($scriptDir, '/');
    $depth = ($cleanDir === '') ? 0 : substr_count($cleanDir, '/') + 1;
    $assetBase = $depth > 0 ? str_repeat('../', $depth) : './';
    $assetBase = rtrim($assetBase, '/');
    if ($assetBase === '') {
        $assetBase = '.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema LGPD</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/favicon.ico">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/assets/css/estilo.css?v=20261003_03">
</head>
<body>
<script>window.SITE_BASE_URL = <?= json_encode($baseUrl) ?>;</script>
<button class="mobile-menu-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" onclick="document.body.classList.toggle('menu-aberto'); this.setAttribute('aria-expanded', document.body.classList.contains('menu-aberto'))">☰</button>
