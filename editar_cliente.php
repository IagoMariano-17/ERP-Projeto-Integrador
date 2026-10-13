<?php
session_start();
require '../config/conexao.php';
require '../auth/verifica_login.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) die('ID inválido.');

$stmt = $pdo->prepare('SELECT * FROM baseinformacoes WHERE id = :id');
$stmt->execute([':id' => (int)$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cliente) die('Cliente não encontrado.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('UPDATE baseinformacoes SET nome_completo=:nome, e_mail=:email, cidade=:cidade WHERE id=:id');
    $stmt->execute([
        ':nome' => trim($_POST['nome_completo'] ?? ''),
        ':email' => trim($_POST['e_mail'] ?? ''),
        ':cidade' => trim($_POST['cidade'] ?? ''),
        ':id' => (int)$id
    ]);
    header('Location: ../clientes.php?msg=atualizado');
    exit;
}
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">Editar Cliente</div>
            <div class="topbar-sub">Atualização de dados cadastrais</div>
        </div>
    </div>
    <div class="content">
        <div class="card nested-client-card">
            <h2>Editar Cliente</h2>
            <form method="POST" class="form-editar">
                <div class="form-editar-grid">
                    <div class="field"><label>Nome completo</label><input type="text" name="nome_completo" value="<?= htmlspecialchars($cliente['nome_completo'] ?? '') ?>" required></div>
                    <div class="field"><label>E-mail</label><input type="email" name="e_mail" value="<?= htmlspecialchars($cliente['e_mail'] ?? '') ?>"></div>
                    <div class="field"><label>Cidade</label><input type="text" name="cidade" value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>"></div>
                </div>
                <div class="form-acoes">
                    <a href="../clientes.php" class="btn btn-sec">Cancelar</a>
                    <button type="submit" class="btn btn-pri">Salvar alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
