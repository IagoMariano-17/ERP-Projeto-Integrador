<?php

// =====================================================
// CLIENTES CONTROLLER - PADRÃO UNIFICADO
// =====================================================

// garante request único
$method = $_SERVER['REQUEST_METHOD'];
$acao = $_POST['acao'] ?? null;

// =====================================================
// FUNÇÕES AUXILIARES (normalização)
// =====================================================
function onlyNumbers($v) {
    return preg_replace('/\D/', '', $v);
}

// =====================================================
// CREATE / UPDATE / DELETE (UNIFICADO)
// =====================================================
if ($method === 'POST' && $acao !== null) {

    try {

        // =========================
        // CREATE
        // =========================
        if ($acao === 'create') {

            $cpf = onlyNumbers($_POST['cpf']);
            $celular = onlyNumbers($_POST['celular_whatsapp']);
            $cep = onlyNumbers($_POST['cep']);

            // verifica duplicado
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM baseinformacoes WHERE cpf = :cpf");
            $stmt->execute([':cpf' => $cpf]);

            if ($stmt->fetchColumn() > 0) {
                $_SESSION['cadastro_msg'] = 'CPF já cadastrado.';
                $_SESSION['cadastro_tipo'] = 'erro';
                header("Location: clientes.php?aba=cadastro");
                exit;
            }

            $sql = "INSERT INTO baseinformacoes (
                cpf, nome_completo, sexo, data_de_nascimento,
                autorizacao, cep, endereco, numero, complemento, bairro, cidade, estado,
                e_mail, celular_whatsapp,
                forma_de_pagamento_preferida, cor_preferida, tecido_preferido,
                tamanho_de_camiseta, tamanho_de_calca, tamanho_de_sapato,
                insertdate
            ) VALUES (
                :cpf, :nome, :sexo, :nascimento,
                :autorizacao, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :estado,
                :email, :celular,
                :pagamento, :cor, :tecido,
                :camiseta, :calca, :sapato,
                NOW()
            )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cpf' => $cpf,
                ':nome' => $_POST['nome_completo'],
                ':sexo' => $_POST['sexo'],
                ':nascimento' => $_POST['data_de_nascimento'],
                ':autorizacao' => $_POST['autorizacao'],
                ':cep' => $cep,
                ':endereco' => $_POST['endereco'],
                ':numero' => trim($_POST['numero'] ?? ''),
                ':complemento' => trim($_POST['complemento'] ?? '') ?: null,
                ':bairro' => $_POST['bairro'],
                ':cidade' => $_POST['cidade'],
                ':estado' => $_POST['estado'],
                ':email' => $_POST['e_mail'],
                ':celular' => $celular,
                ':pagamento' => $_POST['forma_de_pagamento_preferida'],
                ':cor' => $_POST['cor_preferida'],
                ':tecido' => $_POST['tecido_preferido'],
                ':camiseta' => $_POST['tamanho_de_camiseta'],
                ':calca' => $_POST['tamanho_de_calca'],
                ':sapato' => $_POST['tamanho_de_sapato']
            ]);

            $_SESSION['cadastro_msg'] = 'Cliente cadastrado com sucesso.';
            $_SESSION['cadastro_tipo'] = 'sucesso';

            header("Location: clientes.php?aba=cadastro");
            exit;
        }

        // =========================
        // UPDATE
        // =========================
        if ($acao === 'update') {

            $sql = "UPDATE baseinformacoes SET
                cpf = ?,
                nome_completo = ?,
                sexo = ?,
                data_de_nascimento = ?,
                autorizacao = ?,
                cep = ?,
                endereco = ?,
                bairro = ?,
                cidade = ?,
                estado = ?,
                numero = ?,
                complemento = ?,
                e_mail = ?,
                celular_whatsapp = ?
            WHERE id = ?";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                preg_replace('/\D/', '', $_POST['cpf']),
                $_POST['nome_completo'],
                $_POST['sexo'],
                $_POST['data_de_nascimento'],
                $_POST['autorizacao'],
                preg_replace('/\D/', '', $_POST['cep']),
                $_POST['endereco'],
                $_POST['bairro'],
                $_POST['cidade'],
                $_POST['estado'],
                trim($_POST['numero'] ?? ''),
                trim($_POST['complemento'] ?? '') ?: null,
                $_POST['e_mail'],
                preg_replace('/\D/', '', $_POST['celular_whatsapp']),
                $_POST['id']
            ]);

            header("Location: clientes.php?aba=lista");
            exit;
        }

    } catch (PDOException $e) {

        $_SESSION['cadastro_msg'] = 'Erro: ' . $e->getMessage();
        $_SESSION['cadastro_tipo'] = 'erro';

        header("Location: clientes.php");
        exit;
    }
}

// =========================
// DELETE (exclusão lógica)
// =========================
if ($acao === 'delete') {
    $stmt = $pdo->prepare("UPDATE baseinformacoes SET expiredate = NOW() WHERE id = ?");
    $stmt->execute([$_POST['id']]);

    header("Location: clientes.php?aba=lista");
    exit;
}

// =====================================================
// CONSULTA AVANÇADA
// =====================================================
function calcularIdade($data_nascimento) {
    if (empty($data_nascimento)) return '-';
    $data = new DateTime($data_nascimento);
    $hoje = new DateTime();
    return $hoje->diff($data)->y;
}

$config_consulta = [
    'colunas' => ['nome_completo', 'cpf', 'autorizacao'],
    'filtros' => [],
    'ordenar' => 'nome_completo',
    'ordem'   => 'ASC',
    'limite'  => 50
];

$resultados_consulta = [];

if ($method === 'POST' && isset($_POST['aba_ativa']) && $_POST['aba_ativa'] === 'consulta') {

    $config_consulta = [
        'colunas' => $_POST['colunas']          ?? ['nome_completo', 'cpf', 'cidade'],
        'filtros' => $_POST['filtros']          ?? [],
        'ordenar' => $_POST['ordenar_consulta'] ?? 'nome_completo',
        'ordem'   => $_POST['ordem_consulta']   ?? 'ASC',
        'limite'  => (int)($_POST['limite']     ?? 50),
    ];

    $where = ["expiredate IS NULL"];
    $params = [];

    foreach ($config_consulta['filtros'] as $i => $filtro) {
        if (empty($filtro['coluna']) || !isset($filtro['valor']) || $filtro['valor'] === '') continue;

        $coluna   = $filtro['coluna'];
        $operador = $filtro['operador'];
        $valor    = $filtro['valor'];

        // CPF: remove máscara antes de comparar
        if ($coluna === 'cpf') {
            $valor = preg_replace('/\D/', '', $valor);
        }

        if ($operador === 'LIKE') {
            $where[]             = "$coluna LIKE :v$i";
            $params[":v$i"]      = "%$valor%";
        } elseif ($operador === 'IN') {
            $vals = array_map('trim', explode(',', $valor));
            $placeholders = [];
            foreach ($vals as $j => $v) {
                $placeholders[]      = ":v{$i}_$j";
                $params[":v{$i}_$j"] = $v;
            }
            $where[] = "$coluna IN (" . implode(',', $placeholders) . ")";
        } else {
            $where[]        = "$coluna $operador :v$i";
            $params[":v$i"] = $valor;
        }
    }

    $where_sql = "WHERE " . implode(" AND ", $where);

    $ord   = $config_consulta['ordenar'];
    $odir  = $config_consulta['ordem'];
    $lim   = $config_consulta['limite'];

    $stmt = $pdo->prepare("SELECT * FROM baseinformacoes $where_sql ORDER BY $ord $odir LIMIT $lim");
    $stmt->execute($params);
    $resultados_consulta = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// SELECT (LISTA)
// =====================================================

$busca = $_GET['busca'] ?? '';
$ordenar = $_GET['ordenar'] ?? 'nome_completo';
$ordem = $_GET['ordem'] ?? 'ASC';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$limite = 10;
$offset = ($pagina - 1) * $limite;

$sql = "SELECT * FROM baseinformacoes WHERE expiredate IS NULL";

$params = [];

if (!empty($busca)) {
    $buscaSemMascara = preg_replace('/\D/', '', $busca);
    $sql .= " AND (
        nome_completo LIKE :busca OR
        cpf LIKE :busca OR
        cpf LIKE :buscaSemMascara OR
        e_mail LIKE :busca OR
        cidade LIKE :busca
    )";
    $params[':busca'] = "%$busca%";
    $params[':buscaSemMascara'] = "%$buscaSemMascara%";
}
$sql .= " ORDER BY $ordenar $ordem LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);


$stmt->execute($params);

$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// total
$total = $pdo->query("SELECT COUNT(*) FROM baseinformacoes WHERE expiredate IS NULL")->fetchColumn();

$total_paginas = ceil($total / $limite);

$sql = "SELECT * FROM baseinformacoes WHERE expiredate IS NULL";