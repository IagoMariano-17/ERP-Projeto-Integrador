<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../auth/verifica_login.php';

function voltarItem(string $msg='', string $tipo='sucesso') {
    if ($msg !== '') { $_SESSION['itens_msg']=$msg; $_SESSION['itens_tipo']=$tipo; }
    header('Location: ../cadastro_itens.php'); exit;
}
function numItem($v): float { return (float)str_replace(',', '.', (string)($v ?? 0)); }
function uidItem(){ return $_SESSION['usuario_id'] ?? null; }

$acao=$_POST['acao']??'';
try {
    if (!in_array($acao, ['salvar_item','editar_item','inativar_item'], true)) voltarItem();
    if ($acao === 'inativar_item') {
        $tipoInativar = $_POST['tipo_item'] ?? ''; $idInativar = (int)($_POST['item_id'] ?? 0);
        if ($idInativar < 1 || !in_array($tipoInativar, ['PRODUTO','MATERIAL'], true)) throw new Exception('Item inválido para inativação.');
        $tabela = $tipoInativar === 'PRODUTO' ? 'produtos' : 'suprimentos';
        $q = $pdo->prepare("UPDATE {$tabela} SET status='INATIVO' WHERE id=? AND status='ATIVO'"); $q->execute([$idInativar]);
        if (!$q->rowCount()) throw new Exception('Item não encontrado ou já inativo.');
        voltarItem('Item inativado com sucesso. O histórico e as movimentações foram preservados.');
    }
    $tipo=$_POST['tipo_item']??'';
    $fornecedor=(int)($_POST['fornecedor_id']??0);
    $editando = $acao === 'editar_item';
    $itemId = (int)($_POST['item_id'] ?? 0);
    $tipoOriginal = $editando ? ($_POST['tipo_original'] ?? '') : '';
    if ($editando && ($itemId < 1 || !in_array($tipoOriginal, ['PRODUTO','MATERIAL'], true))) throw new Exception('Item para edição inválido.');
    if (!in_array($tipo,['PRODUTO','MATERIAL'],true)) throw new Exception('Selecione um tipo de item válido.');
    $st=$pdo->prepare("SELECT id,tipo_fornecimento,status FROM fornecedores WHERE id=?"); $st->execute([$fornecedor]);
    $fornecedorInfo=$st->fetch(PDO::FETCH_ASSOC);
    if (!$fornecedorInfo) throw new Exception('Selecione um fornecedor cadastrado.');
    if ($fornecedorInfo['status'] !== 'ATIVO') {
        if ($tipo === 'PRODUTO') $cur=$pdo->prepare("SELECT fornecedor_id FROM produtos WHERE id=?");
        else $cur=$pdo->prepare("SELECT fornecedor_id FROM fornecedor_suprimentos WHERE suprimento_id=? AND fornecedor_preferencial=1 LIMIT 1");
        $cur->execute([$itemId]);
        if (!$editando || (int)$cur->fetchColumn() !== $fornecedor) throw new Exception('Fornecedores inativos não podem ser selecionados em novos vínculos.');
    }
    $codigo=trim($_POST['codigo']??'') ?: null; $nome=trim($_POST['nome']??'');
    $tipoFornecedor=$fornecedorInfo['tipo_fornecimento'];
    if ($tipo==='PRODUTO' && !in_array($tipoFornecedor,['PRODUTO','AMBOS'],true)) throw new Exception('Selecione um fornecedor classificado como Produto de Venda ou Ambos.');
    if ($tipo==='MATERIAL' && !in_array($tipoFornecedor,['MATERIAL','AMBOS'],true)) throw new Exception('Selecione um fornecedor classificado como Material de Uso ou Ambos.');
    if ($tipo==='PRODUTO' && !$codigo) throw new Exception('Informe o código do produto.');
    $unidade=trim($_POST['unidade_medida']??'UN') ?: 'UN';
    if ($nome==='') throw new Exception('Informe o nome do item.');

    $pdo->beginTransaction();
    if ($editando && $tipo !== $tipoOriginal) {
        // A conversão de finalidade só é segura quando não há histórico operacional nem saldo em estoque.
        if ($tipoOriginal === 'PRODUTO') {
            $checks = ["SELECT COUNT(*) FROM compra_itens WHERE tipo_item='PRODUTO' AND produto_id=?", "SELECT COUNT(*) FROM venda_itens WHERE produto_id=?", "SELECT COUNT(*) FROM devolucoes_produtos WHERE produto_id=?", "SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id=?", "SELECT COUNT(*) FROM estoque WHERE produto_id=? AND quantidade<>0"];
            foreach ($checks as $sql) { $q=$pdo->prepare($sql); $q->execute([$itemId]); if ((int)$q->fetchColumn()>0) throw new Exception('Não é possível mudar o tipo: este produto já possui estoque ou histórico operacional. Inative-o e cadastre um novo item com o tipo correto.'); }
            $pdo->prepare("UPDATE produtos SET status='INATIVO' WHERE id=?")->execute([$itemId]);
        } else {
            $checks = ["SELECT COUNT(*) FROM compra_itens WHERE tipo_item='SUPRIMENTO' AND suprimento_id=?", "SELECT COUNT(*) FROM consumos_materiais WHERE material_id=?", "SELECT COUNT(*) FROM movimentacoes_suprimentos WHERE suprimento_id=?", "SELECT COUNT(*) FROM suprimentos WHERE id=? AND estoque_atual<>0"];
            foreach ($checks as $sql) { $q=$pdo->prepare($sql); $q->execute([$itemId]); if ((int)$q->fetchColumn()>0) throw new Exception('Não é possível mudar o tipo: este material já possui estoque ou histórico operacional. Inative-o e cadastre um novo item com o tipo correto.'); }
            $pdo->prepare("UPDATE suprimentos SET status='INATIVO' WHERE id=?")->execute([$itemId]);
        }
        $editando = false; // Cria um novo registro no tipo de destino, sem reaproveitar IDs entre tabelas.
    }
    if ($tipo==='PRODUTO') {
        $sku=trim($_POST['sku']??'') ?: null; $custo=numItem($_POST['preco_custo']??0); $venda=numItem($_POST['preco_venda']??0);
        $minLocal=numItem($_POST['estoque_minimo_local']??0); $minML=0;
        $qLocal=0; $qML=0;
        if($custo<0||$venda<0||$minLocal<0) throw new Exception('Informe valores de estoque mínimo e preços válidos.');
        if($sku){$q=$pdo->prepare("SELECT id FROM produtos WHERE sku=? AND status='ATIVO' AND id<>?");$q->execute([$sku,$editando?$itemId:0]);if($q->fetchColumn())throw new Exception('Já existe um produto ativo com este SKU.');}
        if ($editando) {
            $q=$pdo->prepare("UPDATE produtos SET codigo=?,sku=?,nome=?,unidade_medida=?,preco_custo=?,preco_venda=?,estoque_minimo=?,estoque_minimo_local=?,fornecedor_id=? WHERE id=? AND status='ATIVO'");
            $q->execute([$codigo,$sku,$nome,$unidade,$custo,$venda,$minLocal,$minLocal,$fornecedor,$itemId]);
            if(!$q->rowCount() && !$pdo->query("SELECT 1 FROM produtos WHERE id=".(int)$itemId." AND status='ATIVO'")->fetchColumn()) throw new Exception('Produto não encontrado para edição.');
            $pid=$itemId;
            $pdo->prepare("UPDATE fornecedor_produtos SET fornecedor_preferencial=0 WHERE produto_id=?")->execute([$pid]);
            $pdo->prepare("INSERT INTO fornecedor_produtos(fornecedor_id,produto_id,preco_unitario,fornecedor_preferencial,observacoes) VALUES(?,?,?,1,'Cadastro unificado V03') ON DUPLICATE KEY UPDATE preco_unitario=VALUES(preco_unitario),fornecedor_preferencial=1")->execute([$fornecedor,$pid,$custo]);
            $pdo->commit(); voltarItem('Produto atualizado com sucesso.');
        }
        $q=$pdo->prepare("INSERT INTO produtos(codigo,sku,nome,unidade_medida,preco_custo,preco_venda,estoque_minimo,estoque_minimo_local,estoque_minimo_mercado_livre,fornecedor_id,fornecedor_produto_id,status) VALUES(?,?,?,?,?,?,?,?,?,?,NULL,'ATIVO')");
        $q->execute([$codigo,$sku,$nome,$unidade,$custo,$venda,$minLocal,$minLocal,$minML,$fornecedor]); $pid=(int)$pdo->lastInsertId();
        foreach(['FISICO'=>$qLocal,'MERCADO_LIVRE'=>$qML] as $loc=>$qtd){$pdo->prepare("INSERT INTO estoque(produto_id,local_estoque,quantidade,quantidade_reservada) VALUES(?,?,?,0)")->execute([$pid,$loc,$qtd]); if($qtd>0)$pdo->prepare("INSERT INTO movimentacoes_estoque(produto_id,local_estoque,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id,referencia_tipo,referencia_id) VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([$pid,$loc,'AJUSTE_ENTRADA',$qtd,0,$qtd,'Estoque inicial - cadastro unificado',uidItem(),'CADASTRO_ITEM',$pid]);}
        $pdo->prepare("INSERT INTO fornecedor_produtos(fornecedor_id,produto_id,preco_unitario,fornecedor_preferencial,observacoes) VALUES(?,?,?,1,'Cadastro unificado V03') ON DUPLICATE KEY UPDATE preco_unitario=VALUES(preco_unitario),fornecedor_preferencial=1")->execute([$fornecedor,$pid,$custo]);
        $pdo->commit(); voltarItem('Produto cadastrado com sucesso.');
    }

    $estoque=0; $min=numItem($_POST['estoque_minimo']??0);
    if($min<0) throw new Exception('Informe um estoque mínimo válido.');
    if ($editando) {
        $q=$pdo->prepare("UPDATE suprimentos SET codigo=?,nome=?,unidade_medida=?,estoque_minimo=? WHERE id=? AND status='ATIVO'");
        $q->execute([$codigo,$nome,$unidade,$min,$itemId]);
        if(!$q->rowCount() && !$pdo->query("SELECT 1 FROM suprimentos WHERE id=".(int)$itemId." AND status='ATIVO'")->fetchColumn()) throw new Exception('Material não encontrado para edição.');
        $sid=$itemId;
        $pdo->prepare("UPDATE fornecedor_suprimentos SET fornecedor_preferencial=0 WHERE suprimento_id=?")->execute([$sid]);
        $pdo->prepare("INSERT INTO fornecedor_suprimentos(fornecedor_id,suprimento_id,preco_unitario,fornecedor_preferencial,observacoes) VALUES(?,?,0,1,'Cadastro unificado V03') ON DUPLICATE KEY UPDATE fornecedor_preferencial=1")->execute([$fornecedor,$sid]);
        $pdo->commit(); voltarItem('Material de uso atualizado com sucesso.');
    }
    $q=$pdo->prepare("INSERT INTO suprimentos(codigo,nome,unidade_medida,estoque_minimo,estoque_atual,status) VALUES(?,?,?,?,?,'ATIVO')");
    $q->execute([$codigo,$nome,$unidade,$min,$estoque]); $sid=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO fornecedor_suprimentos(fornecedor_id,suprimento_id,preco_unitario,fornecedor_preferencial,observacoes) VALUES(?,?,0,1,'Cadastro unificado V03') ON DUPLICATE KEY UPDATE fornecedor_preferencial=1")->execute([$fornecedor,$sid]);
    if($estoque>0)$pdo->prepare("INSERT INTO movimentacoes_suprimentos(suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)")->execute([$sid,'AJUSTE_ENTRADA',$estoque,0,$estoque,'Estoque inicial - cadastro unificado',uidItem()]);
    $pdo->commit(); $label = 'Material de Uso';
    voltarItem($label.' cadastrado com sucesso.');
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack(); voltarItem($e->getMessage(),'erro');
}
