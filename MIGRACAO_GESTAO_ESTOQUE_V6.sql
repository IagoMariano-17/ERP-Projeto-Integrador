-- V6 - Permitir reutilização de código/SKU após inativação do produto.
-- Mantém unicidade lógica entre produtos ATIVOS através da validação do PHP.
-- Não apaga produtos nem históricos.

SET @db = DATABASE();

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='produtos' AND index_name='uk_produto_codigo'),
    'ALTER TABLE produtos DROP INDEX uk_produto_codigo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='produtos' AND index_name='uk_produto_sku'),
    'ALTER TABLE produtos DROP INDEX uk_produto_sku', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='produtos' AND index_name='idx_produto_codigo'),
    'ALTER TABLE produtos ADD INDEX idx_produto_codigo (codigo)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='produtos' AND index_name='idx_produto_sku'),
    'ALTER TABLE produtos ADD INDEX idx_produto_sku (sku)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
