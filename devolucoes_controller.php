<?php
// =====================================================
// DEVOLUCOES CONTROLLER
// =====================================================

$method = $_SERVER['REQUEST_METHOD'];
$acao   = $_POST['acao'] ?? null;

// =====================================================
// CREATE / UPDATE / DELETE
// =====================================================
if ($method === 'POST' && $acao !== null) {

    try {

        // =========================
        // CREATE
        // =========================
        if ($acao === 'create') {

            $cpf = preg_replace('/\D/', '', $_POST['cliente_cpf']);

            // verifica se CPF existe em baseinformacoes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM baseinformacoes WHERE cpf = :cpf AND expiredate IS NULL");
            $stmt->execute([':cpf' => $cpf]);

            if ($stmt->fetchColumn() === 0) {
                $_SESSION['dev_msg']  = 'CPF não encontrado na base de clientes.';
                $_SESSION['dev_tipo'] = 'erro';
                header("Location: devolucoes.php?aba=cadastro");
                exit;
            }

            $sql = "INSERT INTO devolucoes (
                        cliente_cpf, data_solicitacao, produto, tamanho,
                        motivo, descricao, resolucao, status,
                        data_resolucao, observacoes
                    ) VALUES (
                        :cpf, :data_sol, :produto, :tamanho,
                        :motivo, :descricao, :resolucao, :status,
                        :data_res, :obs
                    )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cpf'       => $cpf,
                ':data_sol'  => $_POST['data_solicitacao'],
                ':produto'   => $_POST['produto'],
                ':tamanho'   => $_POST['tamanho']          ?? null,
                ':motivo'    => $_POST['motivo'],
                ':descricao' => $_POST['descricao']        ?? null,
                ':resolucao' => $_POST['resolucao']        ?? 'pendente',
                ':status'    => $_POST['status']           ?? 'aguardando',
                ':data_res'  => $_POST['data_resolucao']   ?: null,
                ':obs'       => $_POST['observacoes']      ?? null,
            ]);

            $_SESSION['dev_msg']  = 'Devolução registrada com sucesso.';
            $_SESSION['dev_tipo'] = 'sucesso';
            header("Location: devolucoes.php?aba=cadastro");
            exit;
        }

        // =========================
        // UPDATE
        // =========================
        if ($acao === 'update') {

            $sql = "UPDATE devolucoes SET
                        produto         = ?,
                        tamanho         = ?,
                        motivo          = ?,
                        descricao       = ?,
                        resolucao       = ?,
                        status          = ?,
                        data_resolucao  = ?,
                        observacoes     = ?,
                        updated_at      = NOW()
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['produto'],
                $_POST['tamanho']        ?: null,
                $_POST['motivo'],
                $_POST['descricao']      ?: null,
                $_POST['resolucao'],
                $_POST['status'],
                $_POST['data_resolucao'] ?: null,
                $_POST['observacoes']    ?: null,
                $_POST['id'],
            ]);

            header("Location: devolucoes.php?aba=lista");
            exit;
        }

        // =========================
        // DELETE (exclusão lógica via status)
        // =========================
        if ($acao === 'delete') {
            $stmt = $pdo->prepare("UPDATE devolucoes SET status = 'cancelada', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            header("Location: devolucoes.php?aba=lista");
            exit;
        }

    } catch (PDOException $e) {
        $_SESSION['dev_msg']  = 'Erro: ' . $e->getMessage();
        $_SESSION['dev_tipo'] = 'erro';
        header("Location: devolucoes.php");
        exit;
    }
}

// =====================================================
// SELECT — LISTA / COMPATIBILIDADE
// =====================================================
$busca = $_GET['busca'] ?? '';
$ordenar = $_GET['ordenar'] ?? 'd.created_at';
$ordem = strtoupper($_GET['ordem'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$limite = max(1, min(100, (int)($_GET['limite'] ?? 20)));
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$devColunas = [];
try {
    $devColunas = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devolucoes'")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

if (!in_array('cliente_cpf', $devColunas, true)) {
    $_SESSION['dev_msg'] = 'A tabela devolucoes do banco está em uma estrutura diferente da tela original. Execute database/reparar_devolucoes_legadas.sql e recarregue a página.';
    $_SESSION['dev_tipo'] = 'erro';
    $devolucoes = [];
    $total = 0;
    $total_paginas = 1;
    $metricas = ['total_mes'=>0,'aguardando'=>0,'concluidas'=>0,'recusadas'=>0];
} else {
$offset  = ($pagina - 1) * $limite;
$where   = [];
$params  = [];

$colunasOrdenacao = ['d.created_at','d.data_solicitacao','d.status','d.motivo','d.produto'];
if (!in_array($ordenar, $colunasOrdenacao, true)) $ordenar = 'd.created_at';

if ($busca !== '') {
    $buscaSemMascara = preg_replace('/\D/', '', $busca);
    $where[] = '(d.cliente_cpf LIKE :buscaCpf OR d.produto LIKE :buscaTexto)';
    $params[':buscaCpf'] = "%$buscaSemMascara%";
    $params[':buscaTexto'] = "%$busca%";
}
if (!empty($_GET['filtro_status'])) { $where[]='d.status=:fstatus'; $params[':fstatus']=$_GET['filtro_status']; }
if (!empty($_GET['filtro_motivo'])) { $where[]='d.motivo=:fmotivo'; $params[':fmotivo']=$_GET['filtro_motivo']; }
$where_sql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$sql_lista = "SELECT d.*, b.nome_completo, b.celular_whatsapp
              FROM devolucoes d
              LEFT JOIN baseinformacoes b ON b.cpf = d.cliente_cpf
                  AND (b.expiredate IS NULL OR b.expiredate > NOW())
              $where_sql
              ORDER BY $ordenar $ordem
              LIMIT $limite OFFSET $offset";
$stmt=$pdo->prepare($sql_lista); $stmt->execute($params); $devolucoes=$stmt->fetchAll(PDO::FETCH_ASSOC);
$sql_total="SELECT COUNT(*) FROM devolucoes d LEFT JOIN baseinformacoes b ON b.cpf=d.cliente_cpf AND (b.expiredate IS NULL OR b.expiredate>NOW()) $where_sql";
$stmt_total=$pdo->prepare($sql_total); $stmt_total->execute($params); $total=(int)$stmt_total->fetchColumn(); $total_paginas=max(1,(int)ceil($total/$limite));
$mes_atual=date('Y-m');
$metricas=$pdo->query("SELECT COUNT(*) total_mes,SUM(status IN ('aguardando','em_analise')) aguardando,SUM(status='concluida') concluidas,SUM(status='recusada') recusadas FROM devolucoes WHERE DATE_FORMAT(created_at,'%Y-%m')='$mes_atual' AND status!='cancelada'")->fetch(PDO::FETCH_ASSOC);
}

// =====================================================
// MENSAGEM DE SESSÃO
// =====================================================
$mensagem_dev = $_SESSION['dev_msg']  ?? '';
$tipo_dev     = $_SESSION['dev_tipo'] ?? '';
unset($_SESSION['dev_msg'], $_SESSION['dev_tipo']);
