-- ============================================================
-- CORREÇÃO DA TELA LEGADA DE DEVOLUÇÕES
-- ============================================================
-- Use somente se a tabela `devolucoes` do banco não possuir `cliente_cpf`.
-- O conteúdo da tabela incompatível é preservado com outro nome.
-- A tabela legada é recriada com a estrutura usada por devolucoes.php.

SET @db = DATABASE();
SET @tem_cliente_cpf = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='devolucoes' AND COLUMN_NAME='cliente_cpf');
SET @tem_tabela = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='devolucoes');

SET @sql = IF(@tem_tabela=1 AND @tem_cliente_cpf=0,
    'RENAME TABLE devolucoes TO devolucoes_erp_incompativel_backup',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS devolucoes (
  id INT NOT NULL AUTO_INCREMENT,
  cliente_cpf CHAR(11) NOT NULL,
  data_solicitacao DATE NOT NULL,
  produto VARCHAR(120) NOT NULL,
  tamanho VARCHAR(10) DEFAULT NULL,
  motivo ENUM('tamanho_errado','defeito','arrependimento','cor_modelo','outro') NOT NULL,
  descricao TEXT DEFAULT NULL,
  resolucao ENUM('troca','reembolso','credito_loja','recusada','pendente') NOT NULL DEFAULT 'pendente',
  status ENUM('aguardando','em_analise','concluida','recusada','cancelada') NOT NULL DEFAULT 'aguardando',
  data_resolucao DATE DEFAULT NULL,
  observacoes TEXT DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_dev_cpf (cliente_cpf)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- A estrutura incompatível, se existia, permanece em devolucoes_erp_incompativel_backup.
