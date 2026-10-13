<?php
// =====================================================
// ENCOMENDAS CONTROLLER
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

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM baseinformacoes WHERE cpf = :cpf AND expiredate IS NULL");
            $stmt->execute([':cpf' => $cpf]);

            if ($stmt->fetchColumn() === 0) {
                $_SESSION['enc_msg']  = 'CPF não encontrado na base de clientes.';
                $_SESSION['enc_tipo'] = 'erro';
                header("Location: encomendas.php?aba=cadastro");
                exit;
            }

            $sql = "INSERT INTO encomendas (
                        cliente_cpf, produto_descricao, tamanho, cor,
                        quantidade, valor_sinal, valor_total,
                        data_pedido, data_prevista, status, observacoes
                    ) VALUES (
                        :cpf, :produto, :tamanho, :cor,
                        :qtd, :sinal, :total,
                        :data_ped, :data_prev, :status, :obs
                    )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cpf'       => $cpf,
                ':produto'   => $_POST['produto_descricao'],
                ':tamanho'   => $_POST['tamanho']       ?? null,
                ':cor'       => $_POST['cor']            ?? null,
                ':qtd'       => (int)($_POST['quantidade'] ?? 1),
                ':sinal'     => !empty($_POST['valor_sinal'])  ? $_POST['valor_sinal']  : null,
                ':total'     => !empty($_POST['valor_total'])  ? $_POST['valor_total']  : null,
                ':data_ped'  => $_POST['data_pedido'],
                ':data_prev' => $_POST['data_prevista'],
                ':status'    => $_POST['status']         ?? 'aguardando',
                ':obs'       => $_POST['observacoes']    ?? null,
            ]);

            $_SESSION['enc_msg']  = 'Encomenda registrada com sucesso.';
            $_SESSION['enc_tipo'] = 'sucesso';
            header("Location: encomendas.php?aba=cadastro");
            exit;
        }

        // =========================
        // UPDATE
        // =========================
        if ($acao === 'update') {

            $sql = "UPDATE encomendas SET
                        produto_descricao  = ?,
                        tamanho            = ?,
                        cor                = ?,
                        quantidade         = ?,
                        valor_sinal        = ?,
                        valor_total        = ?,
                        data_prevista      = ?,
                        data_entrega_real  = ?,
                        status             = ?,
                        observacoes        = ?,
                        updated_at         = NOW()
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['produto_descricao'],
                $_POST['tamanho']           ?: null,
                $_POST['cor']               ?: null,
                (int)($_POST['quantidade']  ?? 1),
                $_POST['valor_sinal']       ?: null,
                $_POST['valor_total']       ?: null,
                $_POST['data_prevista'],
                $_POST['data_entrega_real'] ?: null,
                $_POST['status'],
                $_POST['observacoes']       ?: null,
                $_POST['id'],
            ]);

            header("Location: encomendas.php?aba=lista");
            exit;
        }

        // =========================
        // DELETE (exclusão lógica)
        // =========================
        if ($acao === 'delete') {
            $stmt = $pdo->prepare("UPDATE encomendas SET status = 'cancelado', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            header("Location: encomendas.php?aba=lista");
            exit;
        }

    } catch (PDOException $e) {
        $_SESSION['enc_msg']  = 'Erro: ' . $e->getMessage();
        $_SESSION['enc_tipo'] = 'erro';
        header("Location: encomendas.php");
        exit;
    }
}

// =====================================================
// SELECT — LISTA
// =====================================================
$busca   = $_GET['busca']   ?? '';
$ordenar = $_GET['ordenar'] ?? 'e.data_prevista';
$ordem   = $_GET['ordem']   ?? 'ASC';
$pagina  = max(1, (int)($_GET['pagina'] ?? 1));
$limite  = 10;
$offset  = ($pagina - 1) * $limite;

$where  = ["e.status != 'cancelado'"];
$params = [];

if (!empty($busca)) {
    $buscaSemMascara = preg_replace('/\D/', '', $busca);

    $where[] = "e.cliente_cpf LIKE :buscaCpf";
    $params[':buscaCpf'] = "%$buscaSemMascara%";
}

if (!empty($_GET['filtro_status'])) {
    $where[]              = "e.status = :fstatus";
    $params[':fstatus']   = $_GET['filtro_status'];
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql_lista = "SELECT e.*, b.nome_completo, b.celular_whatsapp
              FROM encomendas e
              JOIN baseinformacoes b ON b.cpf = e.cliente_cpf
                  AND (b.expiredate IS NULL OR b.expiredate > NOW())
              $where_sql
              ORDER BY $ordenar $ordem
              LIMIT $limite OFFSET $offset";

$stmt       = $pdo->prepare($sql_lista);
$stmt->execute($params);
$encomendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// total para paginação
$sql_total = "SELECT COUNT(*) FROM encomendas e
              JOIN baseinformacoes b ON b.cpf = e.cliente_cpf
                  AND (b.expiredate IS NULL OR b.expiredate > NOW())
              $where_sql";
$stmt_total    = $pdo->prepare($sql_total);
$stmt_total->execute($params);
$total         = (int)$stmt_total->fetchColumn();
$total_paginas = ceil($total / $limite);

// =====================================================
// MÉTRICAS DO TOPO
// =====================================================
$hoje = date('Y-m-d');

$metricas_enc = $pdo->query("
    SELECT
        COUNT(*) AS total_ativas,
        SUM(status = 'aguardando' OR status = 'em_producao') AS em_andamento,
        SUM(status = 'pronto')    AS prontas,
        SUM(data_prevista < '$hoje' AND status NOT IN ('entregue','cancelado')) AS atrasadas
    FROM encomendas
    WHERE status != 'cancelado'
")->fetch(PDO::FETCH_ASSOC);

// =====================================================
// MENSAGEM DE SESSÃO
// =====================================================
$mensagem_enc = $_SESSION['enc_msg']  ?? '';
$tipo_enc     = $_SESSION['enc_tipo'] ?? '';
unset($_SESSION['enc_msg'], $_SESSION['enc_tipo']);
