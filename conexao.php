<?php
date_default_timezone_set('America/Sao_Paulo');

$hostAtual = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = stripos($hostAtual, 'localhost') !== false || stripos($hostAtual, '127.0.0.1') !== false;

if ($isLocal) {
    $host = 'localhost';
    $dbname = 'projetointegrador_dev';
    $user = 'root';
    $pass = '';
} else {
    $host = 'sql208.infinityfree.com';
    $dbname = 'if0_41748767_projetointegrador';
    $user = 'if0_41748767';
    $pass = 'Univesp2026';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erro na conexão com o banco de dados. Verifique as configurações de conexão.');
}
