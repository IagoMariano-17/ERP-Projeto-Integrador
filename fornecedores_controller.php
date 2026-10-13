<?php
if(session_status()===PHP_SESSION_NONE)session_start();
require_once __DIR__.'/../config/conexao.php'; require_once __DIR__.'/../auth/verifica_login.php';
function backF($m,$t='success'){$_SESSION['fornecedor_msg']=$m;$_SESSION['fornecedor_tipo']=$t;header('Location: ../fornecedores.php');exit;}
function soDigits($v){return preg_replace('/\D+/','',(string)$v);}
$acao=$_POST['acao']??'';
try{
 if(in_array($acao,['salvar','editar'],true)){
  $id=(int)($_POST['fornecedor_id']??0);$cnpj=soDigits($_POST['cnpj']??'');$razao=trim($_POST['razao_social']??'');$fantasia=trim($_POST['nome_fantasia']??'');$tipo=$_POST['tipo_fornecimento']??'PRODUTO';
  if(!in_array($tipo,['PRODUTO','MATERIAL','AMBOS'],true))$tipo='PRODUTO';
  if(strlen($cnpj)!==14||$razao==='')throw new Exception('Informe um CNPJ válido e a razão social.');
  $s=$pdo->prepare('SELECT id FROM fornecedores WHERE cnpj=? AND id<>?');$s->execute([$cnpj,$id]);if($s->fetchColumn())throw new Exception('Já existe um fornecedor cadastrado com este CNPJ.');
  $cep=preg_replace('/\D+/','',(string)($_POST['cep']??''));
  if(strlen($cep)!==8)throw new Exception('Informe um CEP válido.');
  $dados=[$razao,$fantasia?:null,$cnpj,$tipo,trim($_POST['contato']??'')?:null,trim($_POST['telefone']??'')?:null,trim($_POST['email']??'')?:null,trim($_POST['endereco']??'')?:null,$cep,trim($_POST['numero']??'')?:null,trim($_POST['complemento']??'')?:null,trim($_POST['bairro']??'')?:null,trim($_POST['cidade']??'')?:null,strtoupper(trim($_POST['estado']??''))?:null,trim($_POST['observacoes']??'')?:null];
  if($acao==='salvar'){$s=$pdo->prepare('INSERT INTO fornecedores(razao_social,nome_fantasia,cnpj,tipo_fornecimento,contato,telefone,email,endereco,cep,numero,complemento,bairro,cidade,estado,observacoes,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'ATIVO\')');$s->execute($dados);backF('Fornecedor cadastrado com sucesso.');}
  $dados[]=$id;$s=$pdo->prepare('UPDATE fornecedores SET razao_social=?,nome_fantasia=?,cnpj=?,tipo_fornecimento=?,contato=?,telefone=?,email=?,endereco=?,cep=?,numero=?,complemento=?,bairro=?,cidade=?,estado=?,observacoes=? WHERE id=?');$s->execute($dados);backF('Fornecedor atualizado com sucesso.');
 }
 if($acao==='inativar'){ $id=(int)($_POST['fornecedor_id']??0);if($id<=0)throw new Exception('Fornecedor inválido.');$pdo->prepare("UPDATE fornecedores SET status='INATIVO' WHERE id=?")->execute([$id]);backF('Fornecedor inativado.'); }
 if($acao==='reativar'){ $id=(int)($_POST['fornecedor_id']??0);if($id<=0)throw new Exception('Fornecedor inválido.');$pdo->prepare("UPDATE fornecedores SET status='ATIVO' WHERE id=?")->execute([$id]);backF('Fornecedor reativado com sucesso.'); }
 throw new Exception('Ação inválida.');
}catch(Throwable $e){backF($e->getMessage(),'error');}
