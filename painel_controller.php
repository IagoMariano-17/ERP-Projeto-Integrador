<?php

// TOTAL CLIENTES
$sql = "SELECT COUNT(*) AS total FROM baseinformacoes where expiredate is null";
$total_clientes = $pdo->query($sql)->fetch()['total'];


// AUTORIZADOS
$sql = "SELECT COUNT(*) AS total FROM baseinformacoes WHERE autorizacao = 'SIM' and expiredate is null";
$total_autorizados = $pdo->query($sql)->fetch()['total'];


// NÃO AUTORIZADOS
$sql = "SELECT COUNT(*) AS total FROM baseinformacoes WHERE autorizacao = 'NAO' and expiredate is null";
$total_nao_autorizados = $pdo->query($sql)->fetch()['total'];


// PENDENTES (exibição no painel)
$sql = "
    SELECT nome_completo
    FROM baseinformacoes
    WHERE autorizacao = 'NAO'
    and expiredate is null
    ORDER BY id DESC
    LIMIT 3
";
$pendentes = $pdo->query($sql)->fetchAll();


// RECENTES
$sql = "
    SELECT *
    FROM baseinformacoes
    where expiredate is null
    ORDER BY id DESC
    LIMIT 4
";
$clientes_recentes = $pdo->query($sql)->fetchAll();



// CONFORMIDADE (consentimento)
$total = $total_clientes > 0 ? $total_clientes : 1;

$conformidade = round(($total_autorizados / $total) * 100);