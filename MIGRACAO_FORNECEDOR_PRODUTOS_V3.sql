-- ============================================================
-- V3 - Fornecedor de PRODUTOS separado do fornecedor de SUPRIMENTOS
-- Não apaga tabelas legadas e não altera dados de fornecedores de suprimentos.
-- Execute no banco projetointegrador_dev.
-- ============================================================

CREATE TABLE IF NOT EXISTS fornecedores_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedor_produto_nome (nome),
    KEY idx_fornecedor_produto_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Produtos: novo fornecedor exclusivo de produtos.
SET @db = DATABASE();
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='produtos' AND column_name='fornecedor_produto_id'),
    'SELECT 1',
    'ALTER TABLE produtos ADD COLUMN fornecedor_produto_id INT NULL AFTER estoque_minimo_mercado_livre'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='produtos' AND index_name='idx_produto_fornecedor_produto'),
    'SELECT 1',
    'ALTER TABLE produtos ADD KEY idx_produto_fornecedor_produto (fornecedor_produto_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Compras de produtos passam a usar fornecedor_produto_id.
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='compras' AND column_name='fornecedor_produto_id'),
    'SELECT 1',
    'ALTER TABLE compras ADD COLUMN fornecedor_produto_id INT NULL AFTER fornecedor_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- O fornecedor_id antigo continua existindo para compras de suprimentos.
-- Para compras de produtos ele fica NULL, evitando misturar os cadastros.
SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='compras' AND column_name='fornecedor_id' AND is_nullable='NO'),
    'ALTER TABLE compras MODIFY fornecedor_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='compras' AND index_name='idx_compra_fornecedor_produto'),
    'SELECT 1',
    'ALTER TABLE compras ADD KEY idx_compra_fornecedor_produto (fornecedor_produto_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- As relações são mantidas por IDs e validação no PHP, preservando compatibilidade
-- com instalações MySQL/MariaDB onde já existam constraints antigas.
