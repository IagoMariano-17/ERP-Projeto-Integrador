<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../auth/verifica_login.php';

function redirecionarEstoque($m = '', $tipo = 'sucesso', $aba = 'visao') {
    if ($m !== '') {
        $_SESSION['estoque_mensagem'] = $m;
        $_SESSION['estoque_mensagem_tipo'] = $tipo;
    }
    header('Location: ../gestao_loja.php?aba=' . urlencode($aba));
    exit;
}
function num($v) { return (float) str_replace(',', '.', (string)($v ?? 0)); }
function uid() { return $_SESSION['usuario_id'] ?? null; }
function estoque(PDO $pdo, int $produtoId, string $local, bool $lock = false): float {
    $sql = "SELECT quantidade FROM estoque WHERE produto_id=? AND local_estoque=?" . ($lock ? " FOR UPDATE" : "");
    $s = $pdo->prepare($sql); $s->execute([$produtoId,$local]);
    $v = $s->fetchColumn();
    if ($v === false) throw new Exception('Registro de estoque não encontrado.');
    return (float)$v;
}
function movimento(PDO $pdo, int $produtoId, string $local, string $tipo, float $q, float $antes, float $depois, ?string $motivo, ?string $refTipo=null, ?int $refId=null) {
    $s=$pdo->prepare("INSERT INTO movimentacoes_estoque (produto_id,local_estoque,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id,referencia_tipo,referencia_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->execute([$produtoId,$local,$tipo,$q,$antes,$depois,$motivo,uid(),$refTipo,$refId]);
}
function alterarEstoque(PDO $pdo, int $produtoId, string $local, float $delta, string $tipo, ?string $motivo, ?string $refTipo=null, ?int $refId=null) {
    $antes=estoque($pdo,$produtoId,$local,true); $depois=$antes+$delta;
    if ($depois < -0.000001) throw new Exception('O estoque não pode ficar negativo.');
    $pdo->prepare("UPDATE estoque SET quantidade=? WHERE produto_id=? AND local_estoque=?")->execute([max(0,$depois),$produtoId,$local]);
    movimento($pdo,$produtoId,$local,$tipo,abs($delta),$antes,max(0,$depois),$motivo,$refTipo,$refId);
}
function fornecedorAtivo(PDO $pdo, int $id): bool {
    if ($id <= 0) return false;
    $s=$pdo->prepare("SELECT COUNT(*) FROM fornecedores WHERE id=? AND status='ATIVO'"); $s->execute([$id]);
    return (int)$s->fetchColumn() > 0;
}
function fornecedorProdutoAtivo(PDO $pdo, int $id): bool { return fornecedorAtivo($pdo,$id); }
function obterOuCriarFornecedorProduto(PDO $pdo, string $selecionado, string $novoNome): int {
    $id=(int)$selecionado;
    if ($id<=0 || !fornecedorAtivo($pdo,$id)) throw new Exception('Selecione um fornecedor ativo no cadastro único de fornecedores.');
    return $id;
}

$acao=$_POST['acao']??'';
try {
    if ($acao==='atualizar_minimo_ml') {
        $id=(int)($_POST['produto_id']??0); $min=num($_POST['estoque_minimo_mercado_livre']??0);
        if($id<=0||$min<0) redirecionarEstoque('Informe um produto e um estoque mínimo válido.','erro','mercado_livre');
        $s=$pdo->prepare("UPDATE produtos SET estoque_minimo_mercado_livre=? WHERE id=? AND status<>'INATIVO'");
        $s->execute([$min,$id]);
        redirecionarEstoque('Estoque mínimo do Mercado Livre atualizado. As sugestões foram recalculadas.','sucesso','mercado_livre');
    }
    if ($acao==='salvar_produto' || $acao==='editar_produto') {
        $id=(int)($_POST['produto_id']??0); $codigo=trim($_POST['codigo']??''); $sku=trim($_POST['sku']??''); $nome=trim($_POST['nome']??'');
        $unidade='UN'; $minLocal=num($_POST['estoque_minimo_local']??0); $minML=num($_POST['estoque_minimo_mercado_livre']??0);
        $fornecedorSelecionado=(string)($_POST['fornecedor_id'] ?? ''); $novoFornecedor=''; $precoCusto=num($_POST['preco_custo']??0); $precoVenda=num($_POST['preco_venda']??0); $descricao=trim($_POST['descricao']??'');
        if(!$codigo||!$nome) redirecionarEstoque('Código e nome são obrigatórios.','erro','produtos');
        if($minLocal<0||$minML<0||$precoCusto<0||$precoVenda<0) redirecionarEstoque('Informe valores válidos.','erro','produtos');
        if($fornecedorSelecionado==='') redirecionarEstoque('Selecione o fornecedor do produto.','erro','produtos');
        $pdo->beginTransaction();
        // O código não é identificador único. O produto é identificado internamente pelo ID automático.
        // O SKU continua sendo validado entre produtos ativos quando informado.
        if($sku!==''){
            $sqlDup="SELECT id,nome FROM produtos WHERE status='ATIVO' AND sku=?";
            $paramsDup=[$sku];
            if($acao==='editar_produto'){ $sqlDup.=' AND id<>?'; $paramsDup[]=$id; }
            $sd=$pdo->prepare($sqlDup); $sd->execute($paramsDup);
            if($sd->fetch()) throw new Exception('Já existe um produto ativo com este SKU.');
        }
        $fornecedorId=obterOuCriarFornecedorProduto($pdo,$fornecedorSelecionado,$novoFornecedor);
        if($acao==='salvar_produto') {
            $s=$pdo->prepare("INSERT INTO produtos (codigo,sku,nome,descricao,unidade_medida,preco_custo,preco_venda,estoque_minimo,estoque_minimo_local,estoque_minimo_mercado_livre,fornecedor_id,fornecedor_produto_id,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,'ATIVO')");
            $s->execute([$codigo,$sku?:null,$nome,$descricao?:null,$unidade,$precoCusto,$precoVenda,$minLocal,$minLocal,$minML,$fornecedorId]);
            $id=(int)$pdo->lastInsertId();
            foreach(['FISICO','MERCADO_LIVRE'] as $loc) $pdo->prepare("INSERT INTO estoque(produto_id,local_estoque,quantidade,quantidade_reservada) VALUES(?,?,0,0)")->execute([$id,$loc]);
            $qLocal=num($_POST['estoque_local_inicial']??0); $qML=num($_POST['estoque_ml_inicial']??0);
            if($qLocal>0) alterarEstoque($pdo,$id,'FISICO',$qLocal,'AJUSTE_ENTRADA','Estoque inicial');
            if($qML>0) alterarEstoque($pdo,$id,'MERCADO_LIVRE',$qML,'AJUSTE_ENTRADA','Estoque inicial Mercado Livre');
            $pdo->commit(); redirecionarEstoque('Produto cadastrado.','sucesso','produtos');
        }
        $s=$pdo->prepare("SELECT * FROM produtos WHERE id=? FOR UPDATE"); $s->execute([$id]); if(!$s->fetch()) throw new Exception('Produto não encontrado.');
        $s=$pdo->prepare("UPDATE produtos SET codigo=?,sku=?,nome=?,descricao=?,unidade_medida=?,preco_custo=?,preco_venda=?,estoque_minimo=?,estoque_minimo_local=?,estoque_minimo_mercado_livre=?,fornecedor_id=?,fornecedor_produto_id=NULL WHERE id=?");
        $s->execute([$codigo,$sku?:null,$nome,$descricao?:null,$unidade,$precoCusto,$precoVenda,$minLocal,$minLocal,$minML,$fornecedorId,$id]);
        $pdo->commit(); redirecionarEstoque('Produto atualizado.','sucesso','produtos');
    }

    if ($acao==='excluir_produto') {
        $id=(int)($_POST['produto_id']??0);
        if($id<=0) throw new Exception('Produto inválido.');
        $pdo->beginTransaction();
        $s=$pdo->prepare("SELECT id FROM produtos WHERE id=? FOR UPDATE");$s->execute([$id]);
        if(!$s->fetch()) throw new Exception('Produto não encontrado.');

        // Produtos que já participaram de movimentações/transações não devem ser apagados
        // fisicamente, pois isso quebraria o histórico. Nesse caso, a exclusão da lista
        // é feita como inativação: deixa de aparecer nos produtos ativos, mas o histórico
        // de compras, vendas, devoluções e movimentações permanece íntegro.
        $checks=[
            "SELECT COUNT(*) FROM movimentacoes_estoque WHERE produto_id=?",
            "SELECT COUNT(*) FROM compra_itens WHERE produto_id=?",
            "SELECT COUNT(*) FROM venda_itens WHERE produto_id=?",
            "SELECT COUNT(*) FROM devolucoes_produtos WHERE produto_id=?"
        ];
        $temHistorico=false;
        foreach($checks as $sql){$q=$pdo->prepare($sql);$q->execute([$id]);if((int)$q->fetchColumn()>0){$temHistorico=true;break;}}

        if($temHistorico){
            $pdo->prepare("UPDATE produtos SET status='INATIVO' WHERE id=?")->execute([$id]);
            $pdo->commit();
            redirecionarEstoque('Produto excluído da lista de produtos. O cadastro foi inativado para preservar o histórico.','sucesso','produtos');
        }

        $pdo->prepare("DELETE FROM estoque WHERE produto_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM produtos WHERE id=?")->execute([$id]);
        $pdo->commit();
        redirecionarEstoque('Produto excluído.','sucesso','produtos');
    }

    if ($acao==='salvar_compra' || $acao==='editar_compra') {
        $id=(int)($_POST['compra_id']??0); $fornProduto=(int)($_POST['fornecedor_id']??0); $prod=(int)($_POST['produto_id']??0); $data=$_POST['data_compra']??date('Y-m-d'); $q=num($_POST['quantidade']??0); $preco=num($_POST['preco_unitario']??0); $numero=trim($_POST['numero']??'');
        if(!$fornProduto||!fornecedorAtivo($pdo,$fornProduto)||!$prod||$q<=0||$preco<0) redirecionarEstoque('Preencha fornecedor, produto, quantidade e valor corretamente.','erro','compras');
        $sp=$pdo->prepare("SELECT id FROM produtos WHERE id=? AND status='ATIVO' AND fornecedor_id=?"); $sp->execute([$prod,$fornProduto]);
        if(!$sp->fetchColumn()) redirecionarEstoque('O produto selecionado não pertence ao fornecedor informado.','erro','compras');
        $pdo->beginTransaction();
        if($acao==='salvar_compra') {
            $s=$pdo->prepare("INSERT INTO compras(numero,fornecedor_id,fornecedor_produto_id,data_compra,status,tipo,valor_frete,desconto,observacoes,usuario_id) VALUES(?,?,NULL,?,'RECEBIDA','PRODUTO',0,0,NULL,?)");
            $s->execute([$numero!==''?$numero:null,$fornProduto,$data,uid()]);
            $id=(int)$pdo->lastInsertId();
            $s=$pdo->prepare("INSERT INTO compra_itens(compra_id,tipo_item,produto_id,suprimento_id,quantidade,quantidade_recebida,preco_unitario,desconto) VALUES(:compra,'PRODUTO',:produto,NULL,:quantidade,:recebida,:preco,0)");
            $s->execute([':compra'=>$id,':produto'=>$prod,':quantidade'=>$q,':recebida'=>$q,':preco'=>$preco]);
            alterarEstoque($pdo,$prod,'FISICO',$q,'ENTRADA_COMPRA','Compra registrada','COMPRA',$id);
            $pdo->prepare("UPDATE produtos SET preco_custo=? WHERE id=?")->execute([$preco,$prod]);
            $pdo->commit(); redirecionarEstoque('Compra registrada e estoque atualizado.','sucesso','compras');
        }
        $s=$pdo->prepare("SELECT c.id,c.fornecedor_id,c.data_compra,c.numero,ci.id item_id,ci.produto_id,ci.quantidade,ci.preco_unitario FROM compras c JOIN compra_itens ci ON ci.compra_id=c.id AND ci.tipo_item='PRODUTO' WHERE c.id=? FOR UPDATE");$s->execute([$id]);$old=$s->fetch();if(!$old)throw new Exception('Compra não encontrada.');
        alterarEstoque($pdo,(int)$old['produto_id'],'FISICO',-(float)$old['quantidade'],'AJUSTE_SAIDA','Estorno da compra para edição','COMPRA_EDICAO',$id);
        $pdo->prepare("UPDATE compras SET fornecedor_id=?,fornecedor_produto_id=NULL,data_compra=?,numero=? WHERE id=?")->execute([$fornProduto,$data,$numero?:null,$id]);
        $pdo->prepare("UPDATE compra_itens SET produto_id=?,quantidade=?,quantidade_recebida=?,preco_unitario=? WHERE id=?")->execute([$prod,$q,$q,$preco,$old['item_id']]);
        alterarEstoque($pdo,$prod,'FISICO',$q,'ENTRADA_COMPRA','Compra editada','COMPRA',$id);
        $pdo->prepare("UPDATE produtos SET preco_custo=? WHERE id=?")->execute([$preco,$prod]); $pdo->commit(); redirecionarEstoque('Compra alterada e estoque recalculado.','sucesso','compras');
    }

    if ($acao==='excluir_compra') {
        $id=(int)($_POST['compra_id']??0);$pdo->beginTransaction();$s=$pdo->prepare("SELECT ci.produto_id,ci.quantidade FROM compra_itens ci JOIN compras c ON c.id=ci.compra_id WHERE c.id=? AND ci.tipo_item='PRODUTO' FOR UPDATE");$s->execute([$id]);$it=$s->fetch();if(!$it)throw new Exception('Compra não encontrada.');
        alterarEstoque($pdo,(int)$it['produto_id'],'FISICO',-(float)$it['quantidade'],'AJUSTE_SAIDA','Estorno da compra excluída','COMPRA_EXCLUSAO',$id);
        $pdo->prepare("DELETE FROM compra_itens WHERE compra_id=?")->execute([$id]);$pdo->prepare("DELETE FROM compras WHERE id=?")->execute([$id]);$pdo->commit();redirecionarEstoque('Compra excluída e estoque ajustado.','sucesso','compras');
    }

    if ($acao==='salvar_venda' || $acao==='editar_venda') {
        $id=(int)($_POST['venda_id']??0);$prod=(int)($_POST['produto_id']??0);$canal=$_POST['canal']??'LOCAL';$q=num($_POST['quantidade']??0);$valor=num($_POST['valor_unitario']??0);$data=$_POST['data_venda']??date('Y-m-d');
        if(!$prod||$q<=0||$valor<0||!in_array($canal,['LOCAL','MERCADO_LIVRE'],true))redirecionarEstoque('Preencha produto, quantidade, origem e valor corretamente.','erro','vendas');
        $local=$canal==='MERCADO_LIVRE'?'MERCADO_LIVRE':'FISICO';$pdo->beginTransaction();
        if($acao==='salvar_venda'){
            $s=$pdo->prepare("INSERT INTO vendas(numero,canal,data_venda,status,valor_total,observacoes) VALUES(NULL,?,?, 'CONCLUIDA',?,NULL)");$s->execute([$canal,$data.' 12:00:00',$q*$valor]);$id=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO venda_itens(venda_id,produto_id,quantidade,preco_unitario) VALUES(?,?,?,?)")->execute([$id,$prod,$q,$valor]);
            alterarEstoque($pdo,$prod,$local,-$q,'SAIDA_VENDA','Venda registrada','VENDA',$id);$pdo->commit();redirecionarEstoque('Venda registrada e estoque atualizado.','sucesso','vendas');
        }
        $s=$pdo->prepare("SELECT v.id,v.canal,v.data_venda,vi.id item_id,vi.produto_id,vi.quantidade,vi.preco_unitario FROM vendas v JOIN venda_itens vi ON vi.venda_id=v.id WHERE v.id=? FOR UPDATE");$s->execute([$id]);$old=$s->fetch();if(!$old)throw new Exception('Venda não encontrada.');$oldLocal=$old['canal']==='MERCADO_LIVRE'?'MERCADO_LIVRE':'FISICO';
        alterarEstoque($pdo,(int)$old['produto_id'],$oldLocal,(float)$old['quantidade'],'AJUSTE_ENTRADA','Estorno da venda para edição','VENDA_EDICAO',$id);
        $pdo->prepare("UPDATE vendas SET canal=?,data_venda=?,valor_total=? WHERE id=?")->execute([$canal,$data.' 12:00:00',$q*$valor,$id]);$pdo->prepare("UPDATE venda_itens SET produto_id=?,quantidade=?,preco_unitario=? WHERE id=?")->execute([$prod,$q,$valor,$old['item_id']]);alterarEstoque($pdo,$prod,$local,-$q,'SAIDA_VENDA','Venda editada','VENDA',$id);$pdo->commit();redirecionarEstoque('Venda alterada e estoque recalculado.','sucesso','vendas');
    }

    if ($acao==='excluir_venda') {
        $id=(int)($_POST['venda_id']??0);$pdo->beginTransaction();$s=$pdo->prepare("SELECT v.canal,vi.produto_id,vi.quantidade FROM vendas v JOIN venda_itens vi ON vi.venda_id=v.id WHERE v.id=? FOR UPDATE");$s->execute([$id]);$it=$s->fetch();if(!$it)throw new Exception('Venda não encontrada.');$local=$it['canal']==='MERCADO_LIVRE'?'MERCADO_LIVRE':'FISICO';alterarEstoque($pdo,(int)$it['produto_id'],$local,(float)$it['quantidade'],'AJUSTE_ENTRADA','Estorno da venda excluída','VENDA_EXCLUSAO',$id);$pdo->prepare("DELETE FROM venda_itens WHERE venda_id=?")->execute([$id]);$pdo->prepare("DELETE FROM vendas WHERE id=?")->execute([$id]);$pdo->commit();redirecionarEstoque('Venda excluída e estoque ajustado.','sucesso','vendas');
    }

    if ($acao==='salvar_devolucao' || $acao==='editar_devolucao') {
        $id=(int)($_POST['devolucao_id']??0);$prod=(int)($_POST['produto_id']??0);$q=num($_POST['quantidade']??0);$data=$_POST['data_devolucao']??date('Y-m-d');$origem=$_POST['origem']??'LOCAL';$motivo=trim($_POST['motivo']??'');$avaria=(int)($_POST['avaria']??0);$obs=trim($_POST['observacoes']??'');
        if(!$prod||$q<=0||!$motivo||!in_array($origem,['LOCAL','MERCADO_LIVRE'],true))redirecionarEstoque('Preencha produto, quantidade, origem e motivo.','erro','devolucoes');
        $pdo->beginTransaction();
        $custo=(float)($pdo->query("SELECT preco_custo FROM produtos WHERE id=".(int)$prod)->fetchColumn() ?: 0);
        if($acao==='salvar_devolucao'){
            $perda=$avaria?($q*$custo):0;$s=$pdo->prepare("INSERT INTO devolucoes_produtos(produto_id,data_devolucao,quantidade,origem,motivo,avaria,valor_unitario_custo,valor_perda,observacoes,usuario_id) VALUES(?,?,?,?,?,?,?,?,?,?)");$s->execute([$prod,$data,$q,$origem,$motivo,$avaria,$custo,$perda,$obs?:null,uid()]);$id=(int)$pdo->lastInsertId();if(!$avaria)alterarEstoque($pdo,$prod,'FISICO',$q,'DEVOLUCAO','Devolução aproveitável','DEVOLUCAO',$id);else movimento($pdo,$prod,'FISICO','PERDA',$q,estoque($pdo,$prod,'FISICO',true),estoque($pdo,$prod,'FISICO',true),'Devolução com avaria — perda','DEVOLUCAO',$id);$pdo->commit();redirecionarEstoque($avaria?'Devolução registrada como perda.':'Devolução registrada e devolvida ao estoque local.','sucesso','devolucoes');
        }
        $s=$pdo->prepare("SELECT * FROM devolucoes_produtos WHERE id=? FOR UPDATE");$s->execute([$id]);$old=$s->fetch();if(!$old)throw new Exception('Devolução não encontrada.');
        // desfaz efeito anterior
        if(!(int)$old['avaria']) alterarEstoque($pdo,(int)$old['produto_id'],'FISICO',-(float)$old['quantidade'],'AJUSTE_SAIDA','Estorno da devolução para edição','DEVOLUCAO_EDICAO',$id);
        $perda=$avaria?($q*$custo):0;$pdo->prepare("UPDATE devolucoes_produtos SET produto_id=?,data_devolucao=?,quantidade=?,origem=?,motivo=?,avaria=?,valor_unitario_custo=?,valor_perda=?,observacoes=? WHERE id=?")->execute([$prod,$data,$q,$origem,$motivo,$avaria,$custo,$perda,$obs?:null,$id]);if(!$avaria)alterarEstoque($pdo,$prod,'FISICO',$q,'DEVOLUCAO','Devolução editada','DEVOLUCAO',$id);$pdo->commit();redirecionarEstoque('Devolução alterada e estoque recalculado.','sucesso','devolucoes');
    }

    if ($acao==='excluir_devolucao') {
        $id=(int)($_POST['devolucao_id']??0);$pdo->beginTransaction();$s=$pdo->prepare("SELECT produto_id,quantidade,avaria FROM devolucoes_produtos WHERE id=? FOR UPDATE");$s->execute([$id]);$d=$s->fetch();if(!$d)throw new Exception('Devolução não encontrada.');if(!(int)$d['avaria'])alterarEstoque($pdo,(int)$d['produto_id'],'FISICO',-(float)$d['quantidade'],'AJUSTE_SAIDA','Estorno da devolução excluída','DEVOLUCAO_EXCLUSAO',$id);$pdo->prepare("DELETE FROM devolucoes_produtos WHERE id=?")->execute([$id]);$pdo->commit();redirecionarEstoque('Devolução excluída e estoque ajustado.','sucesso','devolucoes');
    }

    if ($acao==='enviar_mercado_livre') {
        $id=(int)($_POST['produto_id']??0); $q=num($_POST['quantidade']??0); $motivo=trim($_POST['motivo']??'Transferência para Mercado Livre');
        if(!$id||$q<=0) redirecionarEstoque('Informe produto e quantidade.','erro','mercado_livre');
        $pdo->beginTransaction();
        $pdo->exec("CREATE TABLE IF NOT EXISTS transferencias_mercado_livre (id INT UNSIGNED NOT NULL AUTO_INCREMENT, produto_id INT NOT NULL, quantidade DECIMAL(15,3) NOT NULL, data_transferencia DATE NOT NULL, motivo VARCHAR(255) NULL, usuario_id INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_tml_produto(produto_id), CONSTRAINT fk_tml_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $local=estoque($pdo,$id,'FISICO',true); $min=(float)$pdo->query("SELECT estoque_minimo_local FROM produtos WHERE id=".(int)$id)->fetchColumn();
        if($q>max(0,$local)+0.000001) throw new Exception('Estoque local não possui a quantidade a ser transferida. Disponível no estoque local: '.number_format(max(0,$local),2,',','.').'.');
        $s=$pdo->prepare("INSERT INTO transferencias_mercado_livre (produto_id,quantidade,data_transferencia,motivo,usuario_id) VALUES (?,?,?,?,?)");
        $s->execute([$id,$q,date('Y-m-d'),$motivo?:null,uid()]); $transferId=(int)$pdo->lastInsertId();
        alterarEstoque($pdo,$id,'FISICO',-$q,'ENVIO_MERCADO_LIVRE',$motivo,'TRANSFERENCIA',$transferId);
        alterarEstoque($pdo,$id,'MERCADO_LIVRE',$q,'TRANSFERENCIA',$motivo,'TRANSFERENCIA',$transferId);
        $pdo->commit(); redirecionarEstoque('Transferência para Mercado Livre registrada.','sucesso','mercado_livre');
    }

    if ($acao==='editar_transferencia_ml') {
        $tid=(int)($_POST['transferencia_id']??0); $novoProd=(int)($_POST['produto_id']??0); $novaQ=num($_POST['quantidade']??0); $data=$_POST['data_transferencia']??date('Y-m-d'); $motivo=trim($_POST['motivo']??'Transferência para Mercado Livre');
        if(!$tid||!$novoProd||$novaQ<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$data)) redirecionarEstoque('Informe os dados da transferência corretamente.','erro','mercado_livre');
        $pdo->beginTransaction();
        $pdo->exec("CREATE TABLE IF NOT EXISTS transferencias_mercado_livre (id INT UNSIGNED NOT NULL AUTO_INCREMENT, produto_id INT NOT NULL, quantidade DECIMAL(15,3) NOT NULL, data_transferencia DATE NOT NULL, motivo VARCHAR(255) NULL, usuario_id INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_tml_produto(produto_id), CONSTRAINT fk_tml_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $s=$pdo->prepare("SELECT * FROM transferencias_mercado_livre WHERE id=? FOR UPDATE"); $s->execute([$tid]); $old=$s->fetch(); if(!$old) throw new Exception('Transferência não encontrada.');
        $oldProd=(int)$old['produto_id']; $oldQ=(float)$old['quantidade'];
        $mlOld=estoque($pdo,$oldProd,'MERCADO_LIVRE',true); if($mlOld+0.000001<$oldQ) throw new Exception('Não é possível editar: o estoque Mercado Livre atual é menor que a quantidade da transferência original.');
        // Estorna a transferência original.
        alterarEstoque($pdo,$oldProd,'MERCADO_LIVRE',-$oldQ,'AJUSTE_SAIDA','Estorno da transferência para edição','TRANSFERENCIA_EDICAO',$tid);
        alterarEstoque($pdo,$oldProd,'FISICO',$oldQ,'AJUSTE_ENTRADA','Estorno da transferência para edição','TRANSFERENCIA_EDICAO',$tid);
        // Aplica a nova transferência.
        $local=estoque($pdo,$novoProd,'FISICO',true); $min=(float)$pdo->query("SELECT estoque_minimo_local FROM produtos WHERE id=".(int)$novoProd)->fetchColumn();
        if($novaQ>max(0,$local)+0.000001) throw new Exception('Estoque local não possui a nova quantidade a ser transferida. Disponível no estoque local: '.number_format(max(0,$local),2,',','.').'.');
        $pdo->prepare("UPDATE transferencias_mercado_livre SET produto_id=?,quantidade=?,data_transferencia=?,motivo=? WHERE id=?")->execute([$novoProd,$novaQ,$data,$motivo?:null,$tid]);
        alterarEstoque($pdo,$novoProd,'FISICO',-$novaQ,'ENVIO_MERCADO_LIVRE','Transferência editada','TRANSFERENCIA_EDICAO',$tid);
        alterarEstoque($pdo,$novoProd,'MERCADO_LIVRE',$novaQ,'TRANSFERENCIA','Transferência editada','TRANSFERENCIA_EDICAO',$tid);
        $pdo->commit(); redirecionarEstoque('Transferência alterada e estoques recalculados.','sucesso','mercado_livre');
    }

    if ($acao==='excluir_transferencia_ml') {
        $tid=(int)($_POST['transferencia_id']??0); if(!$tid) throw new Exception('Transferência inválida.');
        $pdo->beginTransaction();
        $pdo->exec("CREATE TABLE IF NOT EXISTS transferencias_mercado_livre (id INT UNSIGNED NOT NULL AUTO_INCREMENT, produto_id INT NOT NULL, quantidade DECIMAL(15,3) NOT NULL, data_transferencia DATE NOT NULL, motivo VARCHAR(255) NULL, usuario_id INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY idx_tml_produto(produto_id), CONSTRAINT fk_tml_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $s=$pdo->prepare("SELECT * FROM transferencias_mercado_livre WHERE id=? FOR UPDATE"); $s->execute([$tid]); $old=$s->fetch(); if(!$old) throw new Exception('Transferência não encontrada.');
        $prod=(int)$old['produto_id']; $q=(float)$old['quantidade']; $ml=estoque($pdo,$prod,'MERCADO_LIVRE',true); if($ml+0.000001<$q) throw new Exception('Não é possível excluir: o estoque Mercado Livre atual é menor que a quantidade transferida.');
        alterarEstoque($pdo,$prod,'MERCADO_LIVRE',-$q,'AJUSTE_SAIDA','Estorno da transferência excluída','TRANSFERENCIA_EXCLUSAO',$tid);
        alterarEstoque($pdo,$prod,'FISICO',$q,'AJUSTE_ENTRADA','Estorno da transferência excluída','TRANSFERENCIA_EXCLUSAO',$tid);
        $pdo->prepare("DELETE FROM transferencias_mercado_livre WHERE id=?")->execute([$tid]);
        $pdo->commit(); redirecionarEstoque('Transferência excluída e estoque ajustado.','sucesso','mercado_livre');
    }

    if ($acao==='atualizar_minimo_material') {
        $id=(int)($_POST['material_id']??0);$min=num($_POST['estoque_minimo']??0);
        if(!$id||$min<0) redirecionarEstoque('Informe um estoque mínimo válido.','erro','materiais_uso');
        $s=$pdo->prepare("UPDATE suprimentos SET estoque_minimo=? WHERE id=? AND status='ATIVO'");$s->execute([$min,$id]);
        if($s->rowCount()<1){$chk=$pdo->prepare("SELECT id FROM suprimentos WHERE id=?");$chk->execute([$id]);if(!$chk->fetch())throw new Exception('Material de uso não encontrado.');}
        redirecionarEstoque('Estoque mínimo do Material de Uso atualizado.','sucesso','materiais_uso');
    }

    if ($acao==='ajustar_inventario_material') {
        $id=(int)($_POST['material_id']??0);$real=num($_POST['quantidade_real']??-1);$motivo=trim($_POST['motivo']??'Inventário de Material de Uso');
        if(!$id||$real<0) redirecionarEstoque('Informe os dados do inventário do material corretamente.','erro','inventario');
        $pdo->beginTransaction();
        $s=$pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id=? AND status='ATIVO' FOR UPDATE");$s->execute([$id]);$antes=$s->fetchColumn();if($antes===false)throw new Exception('Material de uso não encontrado ou inativo.');$antes=(float)$antes;$delta=$real-$antes;
        if(abs($delta)>0.000001){$pdo->prepare('UPDATE suprimentos SET estoque_atual=? WHERE id=?')->execute([$real,$id]);$pdo->prepare('INSERT INTO movimentacoes_suprimentos(suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)')->execute([$id,$delta>0?'AJUSTE_ENTRADA':'AJUSTE_SAIDA',abs($delta),$antes,$real,$motivo?:'Inventário de Material de Uso',uid()]);}
        $pdo->commit();redirecionarEstoque('Inventário do Material de Uso conferido e registrado.','sucesso','inventario');
    }

    if ($acao==='ajustar_inventario') {
        $id=(int)$_POST['produto_id'];$local=$_POST['local_estoque']??'FISICO';$real=num($_POST['quantidade_real']);$motivo=trim($_POST['motivo']??'Inventário físico');if(!$id||!in_array($local,['FISICO','MERCADO_LIVRE'],true)||$real<0)redirecionarEstoque('Informe os dados do inventário corretamente.','erro','inventario');$pdo->beginTransaction();$antes=estoque($pdo,$id,$local,true);$delta=$real-$antes;if(abs($delta)>0.000001)alterarEstoque($pdo,$id,$local,$delta,$delta>0?'AJUSTE_ENTRADA':'AJUSTE_SAIDA',$motivo,'INVENTARIO',$id);$pdo->commit();redirecionarEstoque('Inventário conferido e registrado.','sucesso','inventario');
    }
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    $abaErro='produtos';
    if(in_array($acao,['salvar_compra','editar_compra','excluir_compra'],true)) $abaErro='compras';
    elseif(in_array($acao,['salvar_venda','editar_venda','excluir_venda'],true)) $abaErro='vendas';
    elseif($acao==='atualizar_minimo_material') $abaErro='materiais_uso';
    elseif(in_array($acao,['ajustar_inventario_material','ajustar_inventario'],true)) $abaErro='inventario';
    elseif(strpos($acao,'devolucao')!==false) $abaErro='devolucoes';
    elseif(strpos($acao,'transferencia')!==false || $acao==='enviar_mercado_livre') $abaErro='mercado_livre';
    redirecionarEstoque($e->getMessage(),'erro',$abaErro);
}
redirecionarEstoque();
