<?php
session_start();

require '../config/conexao.php';

$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

$sql = "SELECT * FROM usuarios WHERE email = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);

$usuario = $stmt->fetch();

if ($usuario && password_verify($senha, $usuario['senha'])) {

    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];

    header('Location: ../gestao_loja.php');
    exit;

} else {

    $_SESSION['erro_login'] = 'E-mail ou senha inválidos';

    header('Location: login.php');
    exit;
}
?>