-- Migração incremental da Gestão de Estoque V2
-- NÃO apaga tabelas legadas e NÃO recria o banco.
-- Pode ser executada no banco local projetointegrador_dev.

SET @db = DATABASE();

-- 1) Novos parâmetros do produto
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='produtos' AND column_name='estoque_minimo_local'),
  'SELECT 1',
  'ALTER TABLE produtos ADD COLUMN estoque_minimo_local DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER preco_venda'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='produtos' AND column_name='estoque_minimo_mercado_livre'),
  'SELECT 1',
  'ALTER TABLE produtos ADD COLUMN estoque_minimo_mercado_livre DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER estoque_minimo_local'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='produtos' AND column_name='fornecedor_id'),
  'SELECT 1',
  'ALTER TABLE produtos ADD COLUMN fornecedor_id INT NULL AFTER estoque_minimo_mercado_livre'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migra o antigo estoque_minimo para o novo mínimo local apenas quando o novo campo ainda estiver zerado.
UPDATE produtos
SET estoque_minimo_local = estoque_minimo
WHERE COALESCE(estoque_minimo_local,0) = 0 AND COALESCE(estoque_minimo,0) > 0;

-- Índice/relacionamento lógico do fornecedor (sem depender de FK para preservar compatibilidade).
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='produtos' AND index_name='idx_produto_fornecedor'),
  'SELECT 1',
  'ALTER TABLE produtos ADD KEY idx_produto_fornecedor (fornecedor_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Permite identificar venda local na tabela existente.
ALTER TABLE vendas MODIFY canal ENUM('MERCADO_LIVRE','LOCAL','OUTRO') NOT NULL DEFAULT 'LOCAL';

-- 3) Devoluções de produtos ligadas ao estoque. A tabela legada `devolucoes` NÃO é alterada.
CREATE TABLE IF NOT EXISTS devolucoes_produtos (
  id INT NOT NULL AUTO_INCREMENT,
  produto_id INT NOT NULL,
  data_devolucao DATE NOT NULL,
  quantidade DECIMAL(12,2) NOT NULL,
  origem ENUM('MERCADO_LIVRE','LOCAL') NOT NULL DEFAULT 'LOCAL',
  motivo VARCHAR(255) NOT NULL,
  avaria TINYINT(1) NOT NULL DEFAULT 0,
  valor_unitario_custo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  valor_perda DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  observacoes TEXT NULL,
  usuario_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dev_produto (produto_id),
  KEY idx_dev_data (data_devolucao),
  KEY idx_dev_avaria (avaria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4) Garante estoque separado Local/ML para produtos já existentes.
INSERT INTO estoque (produto_id, local_estoque, quantidade, quantidade_reservada)
SELECT p.id, 'FISICO', 0, 0
FROM produtos p
LEFT JOIN estoque e ON e.produto_id=p.id AND e.local_estoque='FISICO'
WHERE e.id IS NULL;

INSERT INTO estoque (produto_id, local_estoque, quantidade, quantidade_reservada)
SELECT p.id, 'MERCADO_LIVRE', 0, 0
FROM produtos p
LEFT JOIN estoque e ON e.produto_id=p.id AND e.local_estoque='MERCADO_LIVRE'
WHERE e.id IS NULL;

-- Observação: categorias_produtos e os campos antigos de estoque permanecem no banco
-- por compatibilidade com dados/módulos anteriores, mas deixam de ser usados pela nova tela.
