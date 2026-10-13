<?php
require '../config/conexao.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die("ID inválido");
}

$sql = "UPDATE baseinformacoes SET expiredate = NOW() WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);

if ($stmt->execute()) {
    header("Location: ../clientes.php?msg=excluido");
    exit;
} else {
    echo "Erro ao excluir cliente";
}