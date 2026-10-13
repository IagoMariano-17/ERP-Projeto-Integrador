<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexao.php';

function sr($aba = 'suprimentos') {
    header('Location: ../gestao_suprimentos.php?aba=' . urlencode($aba));
    exit;
}

function sm($m, $t = 'success') {
    $_SESSION['mensagem_suprimentos'] = $m;
    $_SESSION['tipo_suprimentos'] = $t;
}

function n($v) {
    $v = is_string($v) ? str_replace(',', '.', $v) : $v;
    return (float) ($v ?? 0);
}

$acao = $_POST['acao'] ?? '';
$aba = 'suprimentos';

try {
    if ($acao === 'editar_fornecedor') {
        $aba = 'fornecedores';
        $id = (int) ($_POST['fornecedor_id'] ?? 0);
        if (!$id || trim($_POST['razao_social'] ?? '') === '') throw new Exception('Informe a razão social do fornecedor.');
        $status = ($_POST['status'] ?? 'ATIVO') === 'INATIVO' ? 'INATIVO' : 'ATIVO';
        $st = $pdo->prepare("UPDATE fornecedores SET razao_social=?, nome_fantasia=?, cnpj=?, contato=?, telefone=?, email=?, cep=?, endereco=?, numero=?, complemento=?, bairro=?, cidade=?, estado=?, observacoes=?, status=? WHERE id=?");
        $st->execute([
            trim($_POST['razao_social'] ?? ''), trim($_POST['nome_fantasia'] ?? ''), trim($_POST['cnpj'] ?? '') ?: null,
            trim($_POST['contato'] ?? ''), trim($_POST['telefone'] ?? ''), trim($_POST['email'] ?? ''),
            preg_replace('/\D+/', '', $_POST['cep'] ?? '') ?: null, trim($_POST['endereco'] ?? ''), trim($_POST['numero'] ?? ''),
            trim($_POST['complemento'] ?? '') ?: null, trim($_POST['bairro'] ?? ''), trim($_POST['cidade'] ?? ''),
            strtoupper(trim($_POST['estado'] ?? '')) ?: null, trim($_POST['observacoes'] ?? '') ?: null, $status, $id
        ]);
        sm('Fornecedor atualizado.');
    }

    if ($acao === 'editar_suprimento') {
        $aba = 'suprimentos';
        $id = (int) ($_POST['suprimento_id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '') ?: null;
        $nome = trim($_POST['nome'] ?? '');
        $unidade = $_POST['unidade_medida'] ?? 'UN';
        $novoEstoque = n($_POST['estoque_atual'] ?? 0);
        $novoMin = n($_POST['estoque_minimo'] ?? 0);
        if (!$id || $nome === '') throw new Exception('Informe o nome do suprimento.');
        if ($novoEstoque < 0 || $novoMin < 0) throw new Exception('Os valores de estoque não podem ser negativos.');
        $pdo->beginTransaction();
        $st=$pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id=? FOR UPDATE"); $st->execute([$id]); $antes=$st->fetchColumn();
        if ($antes === false) throw new Exception('Suprimento não encontrado.');
        $antes=(float)$antes;
        $pdo->prepare("UPDATE suprimentos SET codigo=?, nome=?, unidade_medida=?, estoque_atual=?, estoque_minimo=? WHERE id=?")
            ->execute([$codigo,$nome,$unidade,$novoEstoque,$novoMin,$id]);
        if (abs($novoEstoque-$antes)>0.000001) {
            $tipoMov=$novoEstoque>$antes?'AJUSTE_ENTRADA':'AJUSTE_SAIDA'; $qtd=abs($novoEstoque-$antes);
            $pdo->prepare("INSERT INTO movimentacoes_suprimentos (suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES (?,?,?,?,?,?,?)")
                ->execute([$id,$tipoMov,$qtd,$antes,$novoEstoque,'Ajuste manual do estoque no cadastro',$_SESSION['usuario_id']??null]);
        }
        $pdo->commit(); sm('Suprimento atualizado e estoque ajustado.');
    }

    if ($acao === 'excluir_compra') {
        $aba = 'compras';
        $compraId=(int)($_POST['compra_id']??0);
        if(!$compraId) throw new Exception('Compra inválida.');
        $pdo->beginTransaction();
        $st=$pdo->prepare("SELECT c.numero, ci.suprimento_id, ci.quantidade FROM compras c JOIN compra_itens ci ON ci.compra_id=c.id AND ci.tipo_item='SUPRIMENTO' WHERE c.id=? FOR UPDATE");
        $st->execute([$compraId]); $compra=$st->fetch();
        if(!$compra) throw new Exception('Compra não encontrada.');
        $sid=(int)$compra['suprimento_id']; $qtd=(float)$compra['quantidade'];
        $st=$pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id=? FOR UPDATE"); $st->execute([$sid]); $estoque=$st->fetchColumn();
        if($estoque===false) throw new Exception('Suprimento da compra não encontrado.');
        $estoque=(float)$estoque; $novo=$estoque-$qtd;
        if($novo<0) throw new Exception('Não é possível excluir esta compra porque o estoque atual já está abaixo da quantidade comprada.');
        $pdo->prepare("UPDATE suprimentos SET estoque_atual=? WHERE id=?")->execute([$novo,$sid]);
        $pdo->prepare("INSERT INTO movimentacoes_suprimentos (suprimento_id,tipo,quantidade,estoque_anterior,estoque_posterior,motivo,usuario_id) VALUES (?,?,?,?,?,?,?)")
            ->execute([$sid,'AJUSTE_SAIDA',$qtd,$estoque,$novo,'Estorno da exclusão da compra '.($compra['numero']?:$compraId),$_SESSION['usuario_id']??null]);
        $pdo->prepare("DELETE FROM compra_itens WHERE compra_id=?")->execute([$compraId]);
        $pdo->prepare("DELETE FROM compras WHERE id=?")->execute([$compraId]);
        $pdo->commit(); sm('Compra excluída e estoque ajustado com sucesso.');
    }

    if ($acao === 'salvar_fornecedor') {
        $aba = 'fornecedores';

        $s = $pdo->prepare(
            "INSERT INTO fornecedores
                (razao_social, nome_fantasia, cnpj, contato, telefone, email, cep, endereco, numero, complemento, bairro, cidade, estado, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $s->execute([
            trim($_POST['razao_social'] ?? ''),
            trim($_POST['nome_fantasia'] ?? ''),
            trim($_POST['cnpj'] ?? '') ?: null,
            trim($_POST['contato'] ?? ''),
            trim($_POST['telefone'] ?? ''),
            trim($_POST['email'] ?? ''),
            preg_replace('/\D+/', '', $_POST['cep'] ?? '') ?: null,
            trim($_POST['endereco'] ?? ''), trim($_POST['numero'] ?? ''), trim($_POST['complemento'] ?? '') ?: null,
            trim($_POST['bairro'] ?? ''), trim($_POST['cidade'] ?? ''),
            strtoupper(trim($_POST['estado'] ?? '')) ?: null,
            trim($_POST['observacoes'] ?? '') ?: null
        ]);

        sm('Fornecedor cadastrado.');
    }

    if ($acao === 'salvar_suprimento') {
        $aba = 'suprimentos';

        $codigo = trim($_POST['codigo'] ?? '') ?: null;
        $nome = trim($_POST['nome'] ?? '');
        $unidade = $_POST['unidade_medida'] ?? 'UN';
        $estoqueInicial = n($_POST['estoque_atual'] ?? 0);
        $estoqueMinimo = n($_POST['estoque_minimo'] ?? 0);

        if ($nome === '') {
            throw new Exception('Informe o nome do suprimento.');
        }

        if ($estoqueInicial < 0 || $estoqueMinimo < 0) {
            throw new Exception('Os valores de estoque não podem ser negativos.');
        }

        $s = $pdo->prepare(
            "INSERT INTO suprimentos
                (codigo, nome, unidade_medida, estoque_minimo, estoque_atual)
             VALUES (?, ?, ?, ?, ?)"
        );
        $s->execute([$codigo, $nome, $unidade, $estoqueMinimo, $estoqueInicial]);

        $id = (int) $pdo->lastInsertId();

        if ($estoqueInicial > 0) {
            $pdo->prepare(
                "INSERT INTO movimentacoes_suprimentos
                    (suprimento_id, tipo, quantidade, estoque_anterior, estoque_posterior, motivo, usuario_id)
                 VALUES (?, 'AJUSTE_ENTRADA', ?, 0, ?, 'Estoque inicial', ?)"
            )->execute([
                $id,
                $estoqueInicial,
                $estoqueInicial,
                $_SESSION['usuario_id'] ?? null
            ]);
        }

        sm('Suprimento cadastrado.');
    }

    if ($acao === 'salvar_compra') {
        $aba = 'compras';

        $fornecedorId = (int) ($_POST['compra_fornecedor_id'] ?? 0);
        $suprimentoId = (int) ($_POST['compra_suprimento_id'] ?? 0);
        $quantidade = n($_POST['compra_quantidade'] ?? 0);
        $preco = n($_POST['compra_preco'] ?? 0);
        $dataCompra = $_POST['compra_data'] ?? date('Y-m-d');
        $numero = trim($_POST['compra_numero'] ?? '') ?: 'COMP-' . date('Ymd-His');

        if (!$fornecedorId || !$suprimentoId || $quantidade <= 0) {
            throw new Exception('Informe fornecedor, suprimento e uma quantidade válida.');
        }

        $sf = $pdo->prepare("SELECT status FROM fornecedores WHERE id = ?");
        $sf->execute([$fornecedorId]);
        if ($sf->fetchColumn() !== 'ATIVO') {
            throw new Exception('O fornecedor selecionado está inativo e não pode ser usado em uma nova compra.');
        }

        $pdo->beginTransaction();

        /* Confirma que o suprimento existe e trava o registro durante a atualização. */
        $s = $pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id = ? FOR UPDATE");
        $s->execute([$suprimentoId]);
        $estoqueAntes = $s->fetchColumn();

        if ($estoqueAntes === false) {
            throw new Exception('Suprimento não encontrado.');
        }

        $estoqueAntes = (float) $estoqueAntes;
        $estoqueDepois = $estoqueAntes + $quantidade;

        /* A compra é registrada como recebida porque, nesta operação simplificada,
           a quantidade comprada já entra imediatamente no estoque. */
        $s = $pdo->prepare(
            "INSERT INTO compras
                (numero, fornecedor_id, data_compra, data_recebimento, status, tipo, observacoes, usuario_id)
             VALUES (?, ?, ?, ?, 'RECEBIDA', 'SUPRIMENTO', 'Compra registrada pelo módulo de suprimentos', ?)"
        );
        $s->execute([
            $numero,
            $fornecedorId,
            $dataCompra,
            $dataCompra,
            $_SESSION['usuario_id'] ?? null
        ]);

        $compraId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO compra_itens
                (compra_id, tipo_item, suprimento_id, quantidade, quantidade_recebida, preco_unitario)
             VALUES (?, 'SUPRIMENTO', ?, ?, ?, ?)"
        )->execute([
            $compraId,
            $suprimentoId,
            $quantidade,
            $quantidade,
            $preco
        ]);

        $pdo->prepare(
            "UPDATE suprimentos SET estoque_atual = ? WHERE id = ?"
        )->execute([$estoqueDepois, $suprimentoId]);

        $pdo->prepare(
            "INSERT INTO movimentacoes_suprimentos
                (suprimento_id, tipo, quantidade, estoque_anterior, estoque_posterior, motivo, usuario_id)
             VALUES (?, 'ENTRADA_COMPRA', ?, ?, ?, ?, ?)"
        )->execute([
            $suprimentoId,
            $quantidade,
            $estoqueAntes,
            $estoqueDepois,
            'Entrada por compra ' . $numero,
            $_SESSION['usuario_id'] ?? null
        ]);

        $pdo->commit();

        sm('Compra registrada e estoque atualizado com sucesso.');
    }

    if ($acao === 'editar_compra') {
        $aba = 'compras';

        $compraId = (int) ($_POST['compra_id'] ?? 0);
        $fornecedorId = (int) ($_POST['compra_fornecedor_id'] ?? 0);
        $novoSuprimentoId = (int) ($_POST['compra_suprimento_id'] ?? 0);
        $novaQuantidade = n($_POST['compra_quantidade'] ?? 0);
        $novoPreco = n($_POST['compra_preco'] ?? 0);
        $novaData = $_POST['compra_data'] ?? date('Y-m-d');
        $novoNumero = trim($_POST['compra_numero'] ?? '') ?: 'COMP-' . date('Ymd-His');

        if (!$compraId || !$fornecedorId || !$novoSuprimentoId || $novaQuantidade <= 0) {
            throw new Exception('Informe fornecedor, suprimento e uma quantidade válida.');
        }

        $dataObj = DateTime::createFromFormat('Y-m-d', $novaData);
        if (!$dataObj || $dataObj->format('Y-m-d') !== $novaData) {
            throw new Exception('Informe uma data de compra válida.');
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare(
            "SELECT c.id, c.numero, c.fornecedor_id, c.data_compra, c.status,
                    ci.id AS item_id, ci.suprimento_id, ci.quantidade, ci.quantidade_recebida, ci.preco_unitario
             FROM compras c
             JOIN compra_itens ci ON ci.compra_id = c.id AND ci.tipo_item = 'SUPRIMENTO'
             WHERE c.id = ?
             FOR UPDATE"
        );
        $st->execute([$compraId]);
        $antiga = $st->fetch();

        if (!$antiga) {
            throw new Exception('Compra não encontrada.');
        }

        $suprimentoAntigoId = (int) $antiga['suprimento_id'];
        $quantidadeAntiga = (float) $antiga['quantidade'];

        $sf = $pdo->prepare("SELECT status FROM fornecedores WHERE id = ?");
        $sf->execute([$fornecedorId]);
        $statusFornecedor = $sf->fetchColumn();
        if ($statusFornecedor !== 'ATIVO' && $fornecedorId !== (int)$antiga['fornecedor_id']) {
            throw new Exception('O fornecedor selecionado está inativo e não pode ser usado na compra.');
        }

        /* Trava os suprimentos envolvidos para impedir divergências de estoque. */
        $ids = array_values(array_unique([$suprimentoAntigoId, $novoSuprimentoId]));
        sort($ids);
        $estoques = [];
        foreach ($ids as $sid) {
            $q = $pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id = ? FOR UPDATE");
            $q->execute([$sid]);
            $valor = $q->fetchColumn();
            if ($valor === false) {
                throw new Exception('Suprimento selecionado não encontrado.');
            }
            $estoques[$sid] = (float) $valor;
        }

        if ($suprimentoAntigoId === $novoSuprimentoId) {
            $estoqueAtual = $estoques[$suprimentoAntigoId];
            $novoEstoque = $estoqueAtual - $quantidadeAntiga + $novaQuantidade;
            if ($novoEstoque < 0) {
                throw new Exception('A alteração deixaria o estoque negativo. A quantidade atual do estoque já considera movimentações posteriores.');
            }

            $pdo->prepare("UPDATE suprimentos SET estoque_atual = ? WHERE id = ?")
                ->execute([$novoEstoque, $suprimentoAntigoId]);

            $delta = $novaQuantidade - $quantidadeAntiga;
            if (abs($delta) > 0.000001) {
                $tipoMov = $delta > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SAIDA';
                $qtdMov = abs($delta);
                $pdo->prepare(
                    "INSERT INTO movimentacoes_suprimentos
                        (suprimento_id, tipo, quantidade, estoque_anterior, estoque_posterior, motivo, usuario_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $suprimentoAntigoId,
                    $tipoMov,
                    $qtdMov,
                    $estoqueAtual,
                    $novoEstoque,
                    'Ajuste da compra ' . ($antiga['numero'] ?: $compraId),
                    $_SESSION['usuario_id'] ?? null
                ]);
            }
        } else {
            $estoqueAntigo = $estoques[$suprimentoAntigoId];
            $novoEstoqueAntigo = $estoqueAntigo - $quantidadeAntiga;
            if ($novoEstoqueAntigo < 0) {
                throw new Exception('Não é possível trocar o suprimento: o estoque atual não possui quantidade suficiente para desfazer a compra anterior.');
            }

            $estoqueNovo = $estoques[$novoSuprimentoId];
            $novoEstoqueNovo = $estoqueNovo + $novaQuantidade;

            $pdo->prepare("UPDATE suprimentos SET estoque_atual = ? WHERE id = ?")
                ->execute([$novoEstoqueAntigo, $suprimentoAntigoId]);
            $pdo->prepare("UPDATE suprimentos SET estoque_atual = ? WHERE id = ?")
                ->execute([$novoEstoqueNovo, $novoSuprimentoId]);

            $mov = $pdo->prepare(
                "INSERT INTO movimentacoes_suprimentos
                    (suprimento_id, tipo, quantidade, estoque_anterior, estoque_posterior, motivo, usuario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $mov->execute([
                $suprimentoAntigoId,
                'AJUSTE_SAIDA',
                $quantidadeAntiga,
                $estoqueAntigo,
                $novoEstoqueAntigo,
                'Troca de suprimento na compra ' . ($antiga['numero'] ?: $compraId),
                $_SESSION['usuario_id'] ?? null
            ]);
            $mov->execute([
                $novoSuprimentoId,
                'AJUSTE_ENTRADA',
                $novaQuantidade,
                $estoqueNovo,
                $novoEstoqueNovo,
                'Entrada por edição da compra ' . ($antiga['numero'] ?: $compraId),
                $_SESSION['usuario_id'] ?? null
            ]);
        }

        $pdo->prepare(
            "UPDATE compras
             SET numero = ?, fornecedor_id = ?, data_compra = ?, data_recebimento = ?,
                 status = 'RECEBIDA', tipo = 'SUPRIMENTO', updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$novoNumero, $fornecedorId, $novaData, $novaData, $compraId]);

        $pdo->prepare(
            "UPDATE compra_itens
             SET suprimento_id = ?, quantidade = ?, quantidade_recebida = ?, preco_unitario = ?
             WHERE id = ?"
        )->execute([$novoSuprimentoId, $novaQuantidade, $novaQuantidade, $novoPreco, (int)$antiga['item_id']]);

        $pdo->commit();

        sm('Compra atualizada e estoque ajustado com sucesso.');
    }


    if ($acao === 'editar_consumo') {
        $aba = 'consumo';

        $consumoId = (int) ($_POST['consumo_id'] ?? 0);
        $novoSuprimentoId = (int) ($_POST['suprimento_id'] ?? 0);
        $novaQuantidade = n($_POST['quantidade'] ?? 0);
        $novaData = $_POST['data_consumo'] ?? date('Y-m-d');

        if (!$consumoId || !$novoSuprimentoId || $novaQuantidade <= 0) {
            throw new Exception('Informe suprimento e uma quantidade válida.');
        }

        $dataObj = DateTime::createFromFormat('Y-m-d', $novaData);
        if (!$dataObj || $dataObj->format('Y-m-d') !== $novaData) {
            throw new Exception('Informe uma data de consumo válida.');
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare(
            "SELECT m.id, m.suprimento_id, m.quantidade, m.created_at
             FROM movimentacoes_suprimentos m
             WHERE m.id = ? AND m.tipo = 'CONSUMO'
             FOR UPDATE"
        );
        $st->execute([$consumoId]);
        $antigo = $st->fetch();

        if (!$antigo) {
            throw new Exception('Consumo não encontrado.');
        }

        $suprimentoAntigoId = (int) $antigo['suprimento_id'];
        $quantidadeAntiga = (float) $antigo['quantidade'];

        $ids = array_values(array_unique([$suprimentoAntigoId, $novoSuprimentoId]));
        sort($ids);
        $estoques = [];
        foreach ($ids as $sid) {
            $q = $pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id = ? FOR UPDATE");
            $q->execute([$sid]);
            $valor = $q->fetchColumn();
            if ($valor === false) {
                throw new Exception('Suprimento selecionado não encontrado.');
            }
            $estoques[$sid] = (float) $valor;
        }

        if ($suprimentoAntigoId === $novoSuprimentoId) {
            // Primeiro desfaz o consumo antigo e depois aplica o novo consumo.
            $estoqueAtual = $estoques[$suprimentoAntigoId];
            $estoqueRestaurado = $estoqueAtual + $quantidadeAntiga;
            $novoEstoque = $estoqueRestaurado - $novaQuantidade;

            if ($novoEstoque < 0) {
                throw new Exception('A nova quantidade de consumo deixaria o estoque negativo.');
            }

            $pdo->prepare("UPDATE suprimentos SET estoque_atual = ? WHERE id = ?")
                ->execute([$novoEstoque, $suprimentoAntigoId]);

            $delta = $novoEstoque - $estoqueAtual;
            if (abs($delta) > 0.000001) {
                $tipoMov = $delta > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SAIDA';
                $qtdMov = abs($delta);
                $pdo->prepare(
                    "INSERT INTO movimentacoes_suprimentos
                        (suprimento_id, tipo, quantidade, estoque_anterior, estoque_posterior, motivo, usuario_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $suprimentoAntigoId,
                    $tipoMov,
                    $qtdMov,
                    $estoqueAtual,
                    $novoEstoque,
                    'Ajuste da edição do consumo ' . $consumoId,
                    $_SESSION['usuario_id'] ?? null
                ]);
            }
        } else {
            // Devolve a quantidade do suprimento antigo e aplica o novo consumo no suprimento novo.
            $estoqueAntigo = $estoques[$suprimentoAntigoId];
            $novoEstoqueAntigo = $estoqueAntigo + $quantidadeAntiga;
            $estoqueNovo = $estoques[$novoSuprimentoId];
            $novoEstoqueNovo = $estoqueNovo - $novaQuantidade;

            if ($novoEstoqueNovo < 0) {
                throw new Exception('O estoque do novo suprimento não possui quantidade suficiente para esse consumo.');
            }

            $pdo->prepare("UPDATE suprimentos SET estoque_atual = ? WHERE id = ?")
                ->execute([$novoEstoqueAntigo, $suprimentoAntigoId]);
            $pdo->prepare("UPDATE suprimentos SET estoque_atual = ? WHERE id = ?")
                ->execute([$novoEstoqueNovo, $novoSuprimentoId]);

            $mov = $pdo->prepare(
                "INSERT INTO movimentacoes_suprimentos
                    (suprimento_id, tipo, quantidade, estoque_anterior, estoque_posterior, motivo, usuario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $mov->execute([
                $suprimentoAntigoId,
                'AJUSTE_ENTRADA',
                $quantidadeAntiga,
                $estoqueAntigo,
                $novoEstoqueAntigo,
                'Estorno do consumo ' . $consumoId . ' por edição',
                $_SESSION['usuario_id'] ?? null
            ]);
            $mov->execute([
                $novoSuprimentoId,
                'AJUSTE_SAIDA',
                $novaQuantidade,
                $estoqueNovo,
                $novoEstoqueNovo,
                'Novo consumo da edição ' . $consumoId,
                $_SESSION['usuario_id'] ?? null
            ]);
        }

        // Mantém o lançamento original como consumo e atualiza seus dados históricos.
        $createdAt = $novaData . ' ' . date('H:i:s', strtotime((string)$antigo['created_at']));
        $pdo->prepare(
            "UPDATE movimentacoes_suprimentos
             SET suprimento_id = ?, quantidade = ?, created_at = ?, motivo = 'Consumo para expedição'
             WHERE id = ? AND tipo = 'CONSUMO'"
        )->execute([$novoSuprimentoId, $novaQuantidade, $createdAt, $consumoId]);

        $pdo->commit();

        sm('Consumo atualizado e estoque ajustado com sucesso.');
    }

    if ($acao === 'excluir_consumo') {
        $aba = 'consumo';
        $consumoId = (int) ($_POST['consumo_id'] ?? 0);
        if (!$consumoId) {
            throw new Exception('Consumo inválido.');
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare(
            "SELECT id, suprimento_id, quantidade, created_at
             FROM movimentacoes_suprimentos
             WHERE id = ? AND tipo = 'CONSUMO'
             FOR UPDATE"
        );
        $st->execute([$consumoId]);
        $consumo = $st->fetch();

        if (!$consumo) {
            throw new Exception('Consumo não encontrado.');
        }

        $suprimentoId = (int) $consumo['suprimento_id'];
        $quantidade = (float) $consumo['quantidade'];

        $st = $pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id = ? FOR UPDATE");
        $st->execute([$suprimentoId]);
        $estoqueAntes = $st->fetchColumn();

        if ($estoqueAntes === false) {
            throw new Exception('Suprimento do consumo não encontrado.');
        }

        $estoqueAntes = (float) $estoqueAntes;
        $estoqueDepois = $estoqueAntes + $quantidade;

        // A exclusão desfaz o consumo e devolve a quantidade ao estoque atual.
        $pdo->prepare("UPDATE suprimentos SET estoque_atual = ? WHERE id = ?")
            ->execute([$estoqueDepois, $suprimentoId]);

        // Mantém rastreabilidade: registra o estorno antes de excluir o lançamento original.
        $pdo->prepare(
            "INSERT INTO movimentacoes_suprimentos
                (suprimento_id, tipo, quantidade, estoque_anterior, estoque_posterior, motivo, usuario_id)
             VALUES (?, 'AJUSTE_ENTRADA', ?, ?, ?, ?, ?)"
        )->execute([
            $suprimentoId,
            $quantidade,
            $estoqueAntes,
            $estoqueDepois,
            'Estorno da exclusão do consumo ' . $consumoId,
            $_SESSION['usuario_id'] ?? null
        ]);

        $pdo->prepare("DELETE FROM movimentacoes_suprimentos WHERE id = ? AND tipo = 'CONSUMO'")
            ->execute([$consumoId]);

        $pdo->commit();
        sm('Consumo excluído e estoque ajustado com sucesso.');
    }

    if ($acao === 'registrar_consumo') {
        $aba = 'consumo';

        $id = (int) ($_POST['suprimento_id'] ?? 0);
        $q = n($_POST['quantidade'] ?? 0);
        $dataConsumo = $_POST['data_consumo'] ?? date('Y-m-d');
        // O consumo de suprimentos é destinado à expedição dos produtos vendidos.
        $motivo = 'Consumo para expedição';

        if (!$id || $q <= 0) {
            throw new Exception('Informe suprimento e quantidade.');
        }

        $dataObj = DateTime::createFromFormat('Y-m-d', $dataConsumo);
        if (!$dataObj || $dataObj->format('Y-m-d') !== $dataConsumo) {
            throw new Exception('Informe uma data de consumo válida.');
        }

        $pdo->beginTransaction();

        $s = $pdo->prepare("SELECT estoque_atual FROM suprimentos WHERE id = ? FOR UPDATE");
        $s->execute([$id]);
        $antes = $s->fetchColumn();

        if ($antes === false) {
            throw new Exception('Suprimento não encontrado.');
        }

        $antes = (float) $antes;

        if ($q > $antes) {
            throw new Exception('O estoque do suprimento não pode ficar negativo.');
        }

        $depois = $antes - $q;

        $pdo->prepare(
            'UPDATE suprimentos SET estoque_atual = ? WHERE id = ?'
        )->execute([$depois, $id]);

        /* Guarda a data informada pelo usuário para permitir controles históricos. */
        $createdAt = $dataConsumo . ' ' . date('H:i:s');

        $pdo->prepare(
            "INSERT INTO movimentacoes_suprimentos
                (suprimento_id, tipo, quantidade, estoque_anterior, estoque_posterior, motivo, usuario_id, created_at)
             VALUES (?, 'CONSUMO', ?, ?, ?, ?, ?, ?)"
        )->execute([
            $id,
            $q,
            $antes,
            $depois,
            $motivo,
            $_SESSION['usuario_id'] ?? null,
            $createdAt
        ]);

        $pdo->commit();

        sm('Consumo registrado e estoque atualizado.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sm($e->getMessage(), 'error');
}

sr($aba);
