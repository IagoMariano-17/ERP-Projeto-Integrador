-- Permite códigos de produto repetidos. A chave primária é produtos.id.
SET FOREIGN_KEY_CHECKS=0;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='produtos' AND index_name='uk_produto_codigo'), 'ALTER TABLE produtos DROP INDEX uk_produto_codigo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='produtos' AND index_name='idx_produto_codigo'), 'ALTER TABLE produtos ADD INDEX idx_produto_codigo (codigo)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
ALTER TABLE produtos MODIFY id INT(11) NOT NULL AUTO_INCREMENT;
SET FOREIGN_KEY_CHECKS=1;
