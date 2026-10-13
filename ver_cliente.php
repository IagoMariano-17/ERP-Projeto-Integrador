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

$cpf = preg_replace('/\D/', '', (string)($cliente['cpf'] ?? ''));
$cpf_formatado = strlen($cpf) === 11 ? substr($cpf,0,3).'.'.substr($cpf,3,3).'.'.substr($cpf,6,3).'-'.substr($cpf,9,2) : $cpf;
$cel = preg_replace('/\D/', '', (string)($cliente['celular_whatsapp'] ?? ''));
$cel_formatado = strlen($cel) === 11 ? '('.substr($cel,0,2).') '.substr($cel,2,5).'-'.substr($cel,7,4) : $cel;
$data = !empty($cliente['data_de_nascimento']) ? date('d/m/Y', strtotime($cliente['data_de_nascimento'])) : '-';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">Cliente</div>
            <div class="topbar-sub">Visualização dos dados cadastrais</div>
        </div>
    </div>
    <div class="content">
        <div class="card nested-client-card">
            <div class="nested-client-title"><?= htmlspecialchars($cliente['nome_completo']) ?></div>
            <div class="nested-client-grid">
                <div><strong>CPF</strong><span><?= htmlspecialchars($cpf_formatado) ?></span></div>
                <div><strong>Sexo</strong><span><?= htmlspecialchars($cliente['sexo'] ?? '-') ?></span></div>
                <div><strong>Nascimento</strong><span><?= htmlspecialchars($data) ?></span></div>
                <div><strong>Cidade</strong><span><?= htmlspecialchars($cliente['cidade'] ?? '-') ?></span></div>
                <div><strong>E-mail</strong><span><?= htmlspecialchars($cliente['e_mail'] ?? '-') ?></span></div>
                <div><strong>Celular</strong><span><?= htmlspecialchars($cel_formatado) ?></span></div>
                <div><strong>Autorização</strong><span><?= htmlspecialchars($cliente['autorizacao'] ?? '-') ?></span></div>
            </div>
            <div class="form-acoes"><a href="../clientes.php" class="btn btn-sec">← Voltar para Clientes</a></div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
