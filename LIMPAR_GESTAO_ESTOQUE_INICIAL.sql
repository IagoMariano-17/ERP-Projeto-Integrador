-- ============================================================
-- LIMPEZA INICIAL DA GESTAO DE ESTOQUE
--
-- Use APENAS se voce realmente quer comecar o cadastro de
-- produtos do zero no banco local projetointegrador_dev.
--
-- PRESERVA:
--   - clientes e tabelas legadas
--   - tabela devolucoes (legada)
--   - encomendas
--   - suprimentos e seus fornecedores
--   - movimentacoes de suprimentos
--   - compras de suprimentos
--
-- APAGA:
--   - produtos
--   - estoque Local/ML dos produtos
--   - vendas de produtos
--   - devolucoes de produtos
--   - movimentacoes de estoque de produtos
--   - fornecedores exclusivos de produtos
--   - historico de compras exclusivamente de produtos
--   - vinculos de produtos com fornecedores
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Tabelas diretamente ligadas a produtos
TRUNCATE TABLE movimentacoes_estoque;
TRUNCATE TABLE estoque;
TRUNCATE TABLE venda_itens;
TRUNCATE TABLE vendas;

-- Nova tabela de devolucoes de produtos
TRUNCATE TABLE devolucoes_produtos;

-- Demandas: remove apenas itens de PRODUTO; itens de SUPRIMENTO permanecem.
DELETE FROM demanda_compra_itens WHERE tipo_item = 'PRODUTO';
DELETE d
FROM demandas_compra d
LEFT JOIN demanda_compra_itens i ON i.demanda_id = d.id
WHERE i.id IS NULL;

-- Compras: remove somente compras cujo tipo e PRODUTO.
-- Compras de SUPRIMENTO nao sao removidas.
DROP TEMPORARY TABLE IF EXISTS tmp_compras_produtos;
CREATE TEMPORARY TABLE tmp_compras_produtos AS
SELECT id FROM compras WHERE tipo = 'PRODUTO';

DELETE ri
FROM recebimento_itens ri
JOIN compra_itens ci ON ci.id = ri.compra_item_id
JOIN tmp_compras_produtos t ON t.id = ci.compra_id;

DELETE r
FROM recebimentos r
JOIN tmp_compras_produtos t ON t.id = r.compra_id;

DELETE ci
FROM compra_itens ci
JOIN tmp_compras_produtos t ON t.id = ci.compra_id;

DELETE c
FROM compras c
JOIN tmp_compras_produtos t ON t.id = c.id;

DROP TEMPORARY TABLE tmp_compras_produtos;

-- Vinculos antigos/novos de fornecedores de produtos
TRUNCATE TABLE fornecedor_produtos;
TRUNCATE TABLE fornecedores_produtos;

-- Agora os produtos podem ser removidos com seguranca.
DELETE FROM produtos;

-- Reinicia os IDs para o novo cadastro comecar limpo.
ALTER TABLE produtos AUTO_INCREMENT = 1;
ALTER TABLE fornecedores_produtos AUTO_INCREMENT = 1;
ALTER TABLE fornecedor_produtos AUTO_INCREMENT = 1;
ALTER TABLE estoque AUTO_INCREMENT = 1;
ALTER TABLE vendas AUTO_INCREMENT = 1;
ALTER TABLE venda_itens AUTO_INCREMENT = 1;
ALTER TABLE movimentacoes_estoque AUTO_INCREMENT = 1;
ALTER TABLE devolucoes_produtos AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- A tabela legacy `devolucoes` NAO foi alterada.
-- A tabela `encomendas` NAO foi alterada.
