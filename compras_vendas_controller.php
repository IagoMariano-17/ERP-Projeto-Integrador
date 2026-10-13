<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/conexao.php'; require_once __DIR__.'/../auth/verifica_login.php';
function backCV($where,$m,$t='success'){$_SESSION[$where.'_msg']=$m;$_SESSION[$where.'_tipo']=$t;header('Location: ../'.$where.'.php');exit;}
function ncv($v){return (float)str_replace(',','.',(string)($v??0));}
function uidcv(){return $_SESSION['usuario_id']??null;}
function getStockCV(PDO $pdo,$produto,$local,$lock=false){$q=$pdo->prepare("SELECT quantidade FROM estoque WHERE produto_id=? AND local_estoque=?".($lock?' FOR UPDATE':''));$q->execute([$produto,$local]);$v=$q->fetchColumn();if($v===false){$ins=$pdo->prepare("INSERT INTO estoque(produto_id,local_estoque,quantidade,quantidade_reservada) VALUES(?,?,0,0) ON DUPLICATE KEY UPDATE quantidade=quantidade");$ins->execute([$produto,$local]);$q=$pdo->prepare("SELECT quantidade FROM estoque WHERE produto_id=? AND local_estoque=?".($lock?' FOR UPDATE':''));$q->execute([$produto,$local]);$v=$q->fetchColumn();}if($v===false)throw new Exception('Registro de estoque não encontrado.');return (float)$v;}
function movCV(PDO $pdo,$produto,$local,$tipo,$qtd,$antes,$depois,$motivo,$refTipo,$refId){$q=$pdo->prepare('INSERT INTO movimentacoes_estoque(produto_id,local_estoque,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id,referencia_tipo,referencia_id) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([$produto,$local,$tipo,abs($qtd),$antes,$depois,$motivo,uidcv(),$refTipo,$refId]);}
function deltaCV(PDO $pdo,$produto,$local,$delta,$tipo,$motivo,$refTipo,$refId){$antes=getStockCV($pdo,$produto,$local,true);$depois=$antes+$delta;if($depois<-0.000001)throw new Exception('Estoque insuficiente. Disponível: '.number_format($antes,2,',','.'));$pdo->prepare('UPDATE estoque SET quantidade=? WHERE produto_id=? AND local_estoque=?')->execute([max(0,$depois),$produto,$local]);movCV($pdo,$produto,$local,$tipo,$delta,$antes,max(0,$depois),$motivo,$refTipo,$refId);}
function atualizarCustoProdutoCV(PDO $pdo,$produtoId){
    if(!$produtoId)return;
    $q=$pdo->prepare("SELECT ci.preco_unitario FROM compra_itens ci JOIN compras c ON c.id=ci.compra_id WHERE ci.tipo_item='PRODUTO' AND ci.produto_id=? AND c.status<>'CANCELADA' ORDER BY c.data_compra DESC,c.id DESC,ci.id DESC LIMIT 1");
    $q->execute([$produtoId]);$v=$q->fetchColumn();
    if($v!==false)$pdo->prepare('UPDATE produtos SET preco_custo=? WHERE id=?')->execute([(float)$v,$produtoId]);
}
function atualizarFornecedorProdutoCV(PDO $pdo,$fornecedorId,$produtoId){
    if(!$fornecedorId||!$produtoId)return;
    $q=$pdo->prepare("SELECT ci.preco_unitario FROM compra_itens ci JOIN compras c ON c.id=ci.compra_id WHERE c.fornecedor_id=? AND ci.tipo_item='PRODUTO' AND ci.produto_id=? AND c.status<>'CANCELADA' ORDER BY c.data_compra DESC,c.id DESC,ci.id DESC LIMIT 1");
    $q->execute([$fornecedorId,$produtoId]);$v=$q->fetchColumn();
    if($v===false){$pdo->prepare('DELETE FROM fornecedor_produtos WHERE fornecedor_id=? AND produto_id=?')->execute([$fornecedorId,$produtoId]);return;}
    $pdo->prepare("INSERT INTO fornecedor_produtos(fornecedor_id,produto_id,preco_unitario,fornecedor_preferencial,observacoes) VALUES(?,?,?,1,'Compra V03') ON DUPLICATE KEY UPDATE preco_unitario=VALUES(preco_unitario),fornecedor_preferencial=1")->execute([$fornecedorId,$produtoId,(float)$v]);
}
function atualizarFornecedorMaterialCV(PDO $pdo,$fornecedorId,$materialId){
    if(!$fornecedorId||!$materialId)return;
    $q=$pdo->prepare("SELECT ci.preco_unitario FROM compra_itens ci JOIN compras c ON c.id=ci.compra_id WHERE c.fornecedor_id=? AND ci.tipo_item='SUPRIMENTO' AND ci.suprimento_id=? AND c.status<>'CANCELADA' ORDER BY c.data_compra DESC,c.id DESC,ci.id DESC LIMIT 1");
    $q->execute([$fornecedorId,$materialId]);$v=$q->fetchColumn();
    if($v===false){$pdo->prepare('DELETE FROM fornecedor_suprimentos WHERE fornecedor_id=? AND suprimento_id=?')->execute([$fornecedorId,$materialId]);return;}
    $pdo->prepare("INSERT INTO fornecedor_suprimentos(fornecedor_id,suprimento_id,preco_unitario,fornecedor_preferencial,observacoes) VALUES(?,?,?,1,'Compra V03') ON DUPLICATE KEY UPDATE preco_unitario=VALUES(preco_unitario),fornecedor_preferencial=1")->execute([$fornecedorId,$materialId,(float)$v]);
}
function validarFornecedorCompraCV(PDO $pdo,$forn,$tipo){
    $s=$pdo->prepare('SELECT tipo_fornecimento,status FROM fornecedores WHERE id=?');$s->execute([$forn]);$f=$s->fetch();
    if(!$f||$f['status']!=='ATIVO')throw new Exception('Fornecedor inválido ou inativo.');
    if($f['tipo_fornecimento']!=='AMBOS' && (($tipo==='PRODUTO'&&$f['tipo_fornecimento']!=='PRODUTO')||($tipo==='MATERIAL'&&$f['tipo_fornecimento']!=='MATERIAL')))throw new Exception('O tipo de compra não é compatível com o tipo de fornecimento cadastrado para este fornecedor.');
}
function aplicarEntradaCompraCV(PDO $pdo,$tipo,$item,$qtd,$motivo,$refTipo,$refId){
    if($tipo==='PRODUTO'){
        $s=$pdo->prepare("SELECT id FROM produtos WHERE id=? AND COALESCE(status,'ATIVO')<>'INATIVO'");$s->execute([$item]);if(!$s->fetchColumn())throw new Exception('Produto não encontrado.');
        deltaCV($pdo,$item,'FISICO',$qtd,'ENTRADA_COMPRA',$motivo,$refTipo,$refId);
    }else{
        $s=$pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id=? AND status='ATIVO' FOR UPDATE");$s->execute([$item]);$antes=$s->fetchColumn();if($antes===false)throw new Exception('Material de uso não encontrado.');$antes=(float)$antes;$depois=$antes+$qtd;
        $pdo->prepare('UPDATE suprimentos SET estoque_atual=? WHERE id=?')->execute([$depois,$item]);
        $pdo->prepare('INSERT INTO movimentacoes_suprimentos(suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)')->execute([$item,'ENTRADA_COMPRA',$qtd,$antes,$depois,$motivo,uidcv()]);
    }
}
function estornarEntradaCompraCV(PDO $pdo,$tipo,$item,$qtd,$motivo,$refTipo,$refId){
    if($tipo==='PRODUTO'){
        deltaCV($pdo,$item,'FISICO',-$qtd,'ESTORNO_COMPRA',$motivo,$refTipo,$refId);
    }else{
        $s=$pdo->prepare('SELECT estoque_atual FROM suprimentos WHERE id=? FOR UPDATE');$s->execute([$item]);$antes=$s->fetchColumn();if($antes===false)throw new Exception('Material de uso não encontrado.');$antes=(float)$antes;$depois=$antes-$qtd;if($depois<-0.000001)throw new Exception('Não é possível estornar a compra: o estoque do material já foi consumido.');
        $pdo->prepare('UPDATE suprimentos SET estoque_atual=? WHERE id=?')->execute([max(0,$depois),$item]);
        $pdo->prepare('INSERT INTO movimentacoes_suprimentos(suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)')->execute([$item,'ESTORNO_COMPRA',$qtd,$antes,max(0,$depois),$motivo,uidcv()]);
    }
}
try{
 $acao=$_POST['acao']??'';
 if($acao==='salvar_compra'){
  $forn=(int)($_POST['fornecedor_id']??0);$tipo=$_POST['tipo_item']??'PRODUTO';$item=(int)($_POST['item_id']??0);$qtd=ncv($_POST['quantidade']??0);$preco=ncv($_POST['preco_unitario']??0);$data=$_POST['data_compra']??date('Y-m-d');$numero=trim($_POST['numero']??'');
  if(!$forn||!in_array($tipo,['PRODUTO','MATERIAL'],true)||$item<=0||$qtd<=0||$preco<0)throw new Exception('Preencha fornecedor, tipo, item, quantidade e valor corretamente.');
  validarFornecedorCompraCV($pdo,$forn,$tipo);$pdo->beginTransaction();
  $enum=$tipo==='PRODUTO'?'PRODUTO':'SUPRIMENTO';
  $s=$pdo->prepare("INSERT INTO compras(numero,fornecedor_id,data_compra,status,tipo,valor_frete,desconto,observacoes,usuario_id) VALUES(?,?,?,'RECEBIDA',?,0,0,NULL,?)");$s->execute([$numero?:null,$forn,$data,$enum,uidcv()]);$cid=(int)$pdo->lastInsertId();
  $s=$pdo->prepare("INSERT INTO compra_itens(compra_id,tipo_item,produto_id,suprimento_id,quantidade,quantidade_recebida,preco_unitario,desconto) VALUES(?,?,?,?,?,?,?,0)");$s->execute([$cid,$enum==='PRODUTO'?'PRODUTO':'SUPRIMENTO',$enum==='PRODUTO'?$item:null,$enum==='SUPRIMENTO'?$item:null,$qtd,$qtd,$preco]);
  aplicarEntradaCompraCV($pdo,$tipo,$item,$qtd,'Compra registrada','COMPRA',$cid);
  if($tipo==='PRODUTO'){atualizarCustoProdutoCV($pdo,$item);atualizarFornecedorProdutoCV($pdo,$forn,$item);}else{atualizarFornecedorMaterialCV($pdo,$forn,$item);}
  $pdo->commit();backCV('compras','Compra registrada e estoque atualizado.');
 }
 if($acao==='editar_compra'){
  $id=(int)($_POST['compra_id']??0);$forn=(int)($_POST['fornecedor_id']??0);$tipo=$_POST['tipo_item']??'PRODUTO';$item=(int)($_POST['item_id']??0);$qtd=ncv($_POST['quantidade']??0);$preco=ncv($_POST['preco_unitario']??0);$data=$_POST['data_compra']??date('Y-m-d');$numero=trim($_POST['numero']??'');
  if($id<=0||!$forn||!in_array($tipo,['PRODUTO','MATERIAL'],true)||$item<=0||$qtd<=0||$preco<0)throw new Exception('Preencha todos os dados da compra corretamente.');
  validarFornecedorCompraCV($pdo,$forn,$tipo);$pdo->beginTransaction();
  $s=$pdo->prepare('SELECT * FROM compras WHERE id=? FOR UPDATE');$s->execute([$id]);$c=$s->fetch();if(!$c)throw new Exception('Compra não encontrada.');
  $s=$pdo->prepare('SELECT * FROM compra_itens WHERE compra_id=? LIMIT 1 FOR UPDATE');$s->execute([$id]);$old=$s->fetch();if(!$old)throw new Exception('Item da compra não encontrado.');
  $oldTipo=$old['tipo_item']==='PRODUTO'?'PRODUTO':'MATERIAL';$oldItem=(int)($oldTipo==='PRODUTO'?$old['produto_id']:$old['suprimento_id']);$oldQtd=(float)$old['quantidade'];$oldFornecedor=(int)$c['fornecedor_id'];
  estornarEntradaCompraCV($pdo,$oldTipo,$oldItem,$oldQtd,'Edição da compra: estorno dos dados anteriores','COMPRA_EDICAO_ESTORNO',$id);
  $enum=$tipo==='PRODUTO'?'PRODUTO':'SUPRIMENTO';
  $pdo->prepare('UPDATE compras SET numero=?,fornecedor_id=?,data_compra=?,tipo=?,status=\'RECEBIDA\' WHERE id=?')->execute([$numero?:null,$forn,$data,$enum,$id]);
  $pdo->prepare('UPDATE compra_itens SET tipo_item=?,produto_id=?,suprimento_id=?,quantidade=?,quantidade_recebida=?,preco_unitario=?,desconto=0,observacoes=NULL WHERE id=?')->execute([$enum,$enum==='PRODUTO'?$item:null,$enum==='SUPRIMENTO'?$item:null,$qtd,$qtd,$preco,(int)$old['id']]);
  aplicarEntradaCompraCV($pdo,$tipo,$item,$qtd,'Compra editada e estoque recalculado','COMPRA_EDICAO',$id);
  if($oldTipo==='PRODUTO')atualizarCustoProdutoCV($pdo,$oldItem);
  if($tipo==='PRODUTO')atualizarCustoProdutoCV($pdo,$item);
  if($oldTipo==='PRODUTO')atualizarFornecedorProdutoCV($pdo,$oldFornecedor,$oldItem);
  if($tipo==='PRODUTO')atualizarFornecedorProdutoCV($pdo,$forn,$item);
  if($oldTipo==='MATERIAL')atualizarFornecedorMaterialCV($pdo,$oldFornecedor,$oldItem);
  if($tipo==='MATERIAL')atualizarFornecedorMaterialCV($pdo,$forn,$item);
  $pdo->commit();backCV('compras','Compra atualizada e todos os dados relacionados ao estoque foram recalculados.');
 }
 if($acao==='excluir_compra'){
  $id=(int)($_POST['compra_id']??0);$pdo->beginTransaction();$s=$pdo->prepare('SELECT * FROM compras WHERE id=? FOR UPDATE');$s->execute([$id]);$c=$s->fetch();if(!$c)throw new Exception('Compra não encontrada.');$s=$pdo->prepare('SELECT * FROM compra_itens WHERE compra_id=? LIMIT 1 FOR UPDATE');$s->execute([$id]);$i=$s->fetch();if(!$i)throw new Exception('Item da compra não encontrado.');
  $tipoOld=$i['tipo_item']==='PRODUTO'?'PRODUTO':'MATERIAL';$itemOld=(int)($tipoOld==='PRODUTO'?$i['produto_id']:$i['suprimento_id']);$fornOld=(int)$c['fornecedor_id'];
  estornarEntradaCompraCV($pdo,$tipoOld,$itemOld,(float)$i['quantidade'],'Compra excluída','COMPRA_EXCLUSAO',$id);
  $pdo->prepare('DELETE FROM compra_itens WHERE compra_id=?')->execute([$id]);$pdo->prepare('DELETE FROM compras WHERE id=?')->execute([$id]);
  if($tipoOld==='PRODUTO'){atualizarCustoProdutoCV($pdo,$itemOld);atualizarFornecedorProdutoCV($pdo,$fornOld,$itemOld);}else{atualizarFornecedorMaterialCV($pdo,$fornOld,$itemOld);}
  $pdo->commit();backCV('compras','Compra excluída e estoque ajustado.');
 }
 if($acao==='salvar_venda'){
  $prod=(int)($_POST['produto_id']??0);$qtd=ncv($_POST['quantidade']??0);$preco=ncv($_POST['preco_unitario']??0);$canal=$_POST['canal']??'LOCAL';$data=$_POST['data_venda']??date('Y-m-d');if($prod<=0||$qtd<=0||$preco<0||!in_array($canal,['LOCAL','MERCADO_LIVRE','OUTRO'],true))throw new Exception('Preencha produto, quantidade, valor e canal corretamente.');$local=$canal==='MERCADO_LIVRE'?'MERCADO_LIVRE':'FISICO';$pdo->beginTransaction();$s=$pdo->prepare('SELECT id FROM produtos WHERE id=? AND status=\'ATIVO\'');$s->execute([$prod]);if(!$s->fetchColumn())throw new Exception('Produto não encontrado.');$s=$pdo->prepare("INSERT INTO vendas(canal,data_venda,status,valor_total,observacoes) VALUES(?,?,'CONCLUIDA',?,NULL)");$s->execute([$canal,$data.' 12:00:00',$qtd*$preco]);$vid=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO venda_itens(venda_id,produto_id,quantidade,preco_unitario) VALUES(?,?,?,?)')->execute([$vid,$prod,$qtd,$preco]);deltaCV($pdo,$prod,$local,-$qtd,'SAIDA_VENDA','Venda registrada','VENDA',$vid);$pdo->commit();backCV('vendas','Venda registrada e estoque atualizado.');
 }
 if($acao==='editar_venda'){
  $id=(int)($_POST['venda_id']??0);$prod=(int)($_POST['produto_id']??0);$qtd=ncv($_POST['quantidade']??0);$preco=ncv($_POST['preco_unitario']??0);$canal=$_POST['canal']??'LOCAL';$data=$_POST['data_venda']??date('Y-m-d');
  if($id<=0||$prod<=0||$qtd<=0||$preco<0||!in_array($canal,['LOCAL','MERCADO_LIVRE','OUTRO'],true))throw new Exception('Preencha produto, quantidade, valor e canal corretamente.');
  $pdo->beginTransaction();
  $s=$pdo->prepare("SELECT v.id,v.canal,v.status,vi.id item_id,vi.produto_id,vi.quantidade FROM vendas v JOIN venda_itens vi ON vi.venda_id=v.id WHERE v.id=? FOR UPDATE");$s->execute([$id]);$old=$s->fetch();if(!$old||$old['status']==='CANCELADA')throw new Exception('Venda não encontrada ou cancelada.');
  $s=$pdo->prepare("SELECT id FROM produtos WHERE id=? AND COALESCE(status,'ATIVO')<>'INATIVO'");$s->execute([$prod]);if(!$s->fetchColumn())throw new Exception('Produto não encontrado ou inativo.');
  $oldLocal=$old['canal']==='MERCADO_LIVRE'?'MERCADO_LIVRE':'FISICO';
  deltaCV($pdo,(int)$old['produto_id'],$oldLocal,(float)$old['quantidade'],'ESTORNO_VENDA','Edição da venda: estorno dos dados anteriores','VENDA_EDICAO_ESTORNO',$id);
  $pdo->prepare("UPDATE vendas SET canal=?,data_venda=?,valor_total=?,status='CONCLUIDA' WHERE id=?")->execute([$canal,$data.' 12:00:00',$qtd*$preco,$id]);
  $pdo->prepare('UPDATE venda_itens SET produto_id=?,quantidade=?,preco_unitario=? WHERE id=?')->execute([$prod,$qtd,$preco,(int)$old['item_id']]);
  $newLocal=$canal==='MERCADO_LIVRE'?'MERCADO_LIVRE':'FISICO';
  deltaCV($pdo,$prod,$newLocal,-$qtd,'SAIDA_VENDA','Venda editada e estoque recalculado','VENDA_EDICAO',$id);
  $pdo->commit();backCV('vendas','Venda atualizada e estoque recalculado.');
 }
 if($acao==='excluir_venda'){$id=(int)($_POST['venda_id']??0);$pdo->beginTransaction();$s=$pdo->prepare('SELECT v.canal,vi.produto_id,vi.quantidade FROM vendas v JOIN venda_itens vi ON vi.venda_id=v.id WHERE v.id=? FOR UPDATE');$s->execute([$id]);$v=$s->fetch();if(!$v)throw new Exception('Venda não encontrada.');$local=$v['canal']==='MERCADO_LIVRE'?'MERCADO_LIVRE':'FISICO';deltaCV($pdo,(int)$v['produto_id'],$local,(float)$v['quantidade'],'ESTORNO_VENDA','Venda excluída','VENDA_EXCLUSAO',$id);$pdo->prepare('DELETE FROM venda_itens WHERE venda_id=?')->execute([$id]);$pdo->prepare('DELETE FROM vendas WHERE id=?')->execute([$id]);$pdo->commit();backCV('vendas','Venda excluída e estoque devolvido.');}
 throw new Exception('Ação inválida.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();backCV(in_array($acao,['salvar_venda','editar_venda','excluir_venda'],true)?'vendas':'compras',$e->getMessage(),'error');}
