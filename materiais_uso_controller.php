<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/conexao.php';
require_once __DIR__.'/../auth/verifica_login.php';
function backMU($m,$t='success'){$_SESSION['estoque_mensagem']=$m;$_SESSION['estoque_mensagem_tipo']=($t==='error'?'erro':'sucesso');header('Location: ../gestao_loja.php?aba=materiais_uso');exit;}
function nmu($v){return (float)str_replace(',','.',(string)($v??0));}
$usuarioId = isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] !== '' ? (int)$_SESSION['usuario_id'] : null;
try{
 $acao=$_POST['acao']??'';
 if(!in_array($acao,['salvar_uso','editar_uso','excluir_uso'],true))throw new Exception('Ação inválida.');
 $pdo->beginTransaction();
 if($acao==='salvar_uso'){
  $material=(int)($_POST['material_id']??0);$qtd=nmu($_POST['quantidade']??0);$data=$_POST['data_uso']??date('Y-m-d');$motivo=trim($_POST['motivo']??'');
  if($material<=0||$qtd<=0||!$data)throw new Exception('Informe o material, a quantidade e a data de uso.');
  $s=$pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id=? AND status='ATIVO' FOR UPDATE");$s->execute([$material]);$antes=$s->fetchColumn();if($antes===false)throw new Exception('Material de uso não encontrado ou inativo.');$antes=(float)$antes;if($qtd>$antes)throw new Exception('Quantidade superior ao estoque disponível. Estoque atual: '.number_format($antes,2,',','.'));$depois=$antes-$qtd;
  $s=$pdo->prepare('INSERT INTO consumos_materiais(material_id,data_uso,quantidade,motivo,usuario_id) VALUES(?,?,?,?,?)');$s->execute([$material,$data,$qtd,$motivo?:null,$usuarioId]);$id=(int)$pdo->lastInsertId();
  $pdo->prepare('UPDATE suprimentos SET estoque_atual=? WHERE id=?')->execute([$depois,$material]);
  $pdo->prepare('INSERT INTO movimentacoes_suprimentos(suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)')->execute([$material,'CONSUMO',$qtd,$antes,$depois,$motivo?:'Uso de material',$usuarioId]);
  $pdo->commit();backMU('Uso registrado e estoque atualizado.');
 }
 if($acao==='editar_uso'){
  $id=(int)($_POST['consumo_id']??0);$material=(int)($_POST['material_id']??0);$qtd=nmu($_POST['quantidade']??0);$data=$_POST['data_uso']??date('Y-m-d');$motivo=trim($_POST['motivo']??'');
  if($id<=0||$material<=0||$qtd<=0||!$data)throw new Exception('Informe os dados do uso corretamente.');
  $s=$pdo->prepare('SELECT * FROM consumos_materiais WHERE id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetch();if(!$old)throw new Exception('Registro de uso não encontrado.');
  $oldMat=(int)$old['material_id'];$oldQtd=(float)$old['quantidade'];
  $s=$pdo->prepare('SELECT estoque_atual FROM suprimentos WHERE id=? FOR UPDATE');$s->execute([$oldMat]);$estoqueOld=$s->fetchColumn();if($estoqueOld===false)throw new Exception('Material anterior não encontrado.');$estoqueOld=(float)$estoqueOld;$restaurado=$estoqueOld+$oldQtd;
  $pdo->prepare('UPDATE suprimentos SET estoque_atual=? WHERE id=?')->execute([$restaurado,$oldMat]);
  $pdo->prepare('INSERT INTO movimentacoes_suprimentos(suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)')->execute([$oldMat,'ESTORNO_CONSUMO',$oldQtd,$estoqueOld,$restaurado,'Edição do uso #'.$id,$usuarioId]);
  $s=$pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id=? AND status='ATIVO' FOR UPDATE");$s->execute([$material]);$antes=$s->fetchColumn();if($antes===false)throw new Exception('Novo material não encontrado ou inativo.');$antes=(float)$antes;if($qtd>$antes)throw new Exception('Quantidade superior ao estoque disponível do novo material. Estoque atual: '.number_format($antes,2,',','.'));$depois=$antes-$qtd;
  $pdo->prepare('UPDATE suprimentos SET estoque_atual=? WHERE id=?')->execute([$depois,$material]);
  $pdo->prepare('UPDATE consumos_materiais SET material_id=?,data_uso=?,quantidade=?,motivo=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$material,$data,$qtd,$motivo?:null,$id]);
  $pdo->prepare('INSERT INTO movimentacoes_suprimentos(suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)')->execute([$material,'CONSUMO_EDICAO',$qtd,$antes,$depois,'Uso editado #'.$id.($motivo?' - '.$motivo:''),$usuarioId]);
  $pdo->commit();backMU('Uso atualizado e estoque recalculado.');
 }
 if($acao==='excluir_uso'){
  $id=(int)($_POST['consumo_id']??0);if($id<=0)throw new Exception('Registro inválido.');$s=$pdo->prepare('SELECT * FROM consumos_materiais WHERE id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetch();if(!$old)throw new Exception('Registro de uso não encontrado.');$mat=(int)$old['material_id'];$qtd=(float)$old['quantidade'];$s=$pdo->prepare('SELECT estoque_atual FROM suprimentos WHERE id=? FOR UPDATE');$s->execute([$mat]);$antes=$s->fetchColumn();if($antes===false)throw new Exception('Material não encontrado.');$antes=(float)$antes;$depois=$antes+$qtd;$pdo->prepare('UPDATE suprimentos SET estoque_atual=? WHERE id=?')->execute([$depois,$mat]);$pdo->prepare('INSERT INTO movimentacoes_suprimentos(suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)')->execute([$mat,'ESTORNO_CONSUMO',$qtd,$antes,$depois,'Uso excluído #'.$id,$usuarioId]);$pdo->prepare('DELETE FROM consumos_materiais WHERE id=?')->execute([$id]);$pdo->commit();backMU('Uso excluído e estoque devolvido.');
 }
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();backMU($e->getMessage(),'error');}
