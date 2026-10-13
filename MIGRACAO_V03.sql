-- ============================================================
-- ERP Gestão LGPD - MIGRAÇÃO V03
-- Evolução segura da V02 - NÃO apaga dados nem recria tabelas.
-- Execute no banco já utilizado pela V02.
-- ============================================================

SET @db := DATABASE();

-- ------------------------------------------------------------
-- 1. Endereço estruturado de fornecedores
-- ------------------------------------------------------------
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS cep VARCHAR(9) NULL AFTER email;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS numero VARCHAR(20) NULL AFTER endereco;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS complemento VARCHAR(100) NULL AFTER numero;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS bairro VARCHAR(100) NULL AFTER complemento;

-- ------------------------------------------------------------
-- 2. Endereço estruturado de clientes
-- ------------------------------------------------------------
ALTER TABLE baseinformacoes ADD COLUMN IF NOT EXISTS numero VARCHAR(20) NULL AFTER endereco;
ALTER TABLE baseinformacoes ADD COLUMN IF NOT EXISTS complemento VARCHAR(100) NULL AFTER numero;

-- ------------------------------------------------------------
-- 3. Garantir que as relações V02 existam
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fornecedores_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedores_produtos_nome(nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. Migrar fornecedores antigos de produtos para o cadastro
--    único de fornecedores.
-- ------------------------------------------------------------
INSERT INTO fornecedores (razao_social, nome_fantasia, status)
SELECT fp.nome, fp.nome, CASE WHEN fp.status='INATIVO' THEN 'INATIVO' ELSE 'ATIVO' END
FROM fornecedores_produtos fp
LEFT JOIN fornecedores f ON f.nome_fantasia = fp.nome OR f.razao_social = fp.nome
WHERE f.id IS NULL AND TRIM(fp.nome) <> '';

-- ------------------------------------------------------------
-- 5. Vincular produtos existentes ao fornecedor geral.
-- ------------------------------------------------------------
UPDATE produtos p
JOIN fornecedores_produtos fp ON fp.id = p.fornecedor_produto_id
JOIN fornecedores f ON (f.nome_fantasia = fp.nome OR f.razao_social = fp.nome)
SET p.fornecedor_id = f.id
WHERE p.fornecedor_id IS NULL;

-- ------------------------------------------------------------
-- 6. Vincular compras de produtos existentes ao fornecedor geral.
-- ------------------------------------------------------------
UPDATE compras c
JOIN fornecedores_produtos fp ON fp.id = c.fornecedor_produto_id
JOIN fornecedores f ON (f.nome_fantasia = fp.nome OR f.razao_social = fp.nome)
SET c.fornecedor_id = f.id
WHERE c.fornecedor_id IS NULL;

-- ------------------------------------------------------------
-- 7. Criar/atualizar a relação fornecedor x produto.
-- ------------------------------------------------------------
INSERT INTO fornecedor_produtos
    (fornecedor_id, produto_id, preco_unitario, fornecedor_preferencial, observacoes)
SELECT p.fornecedor_id, p.id, p.preco_custo, 1, 'Relação migrada automaticamente na V03'
FROM produtos p
LEFT JOIN fornecedor_produtos fp
    ON fp.fornecedor_id = p.fornecedor_id AND fp.produto_id = p.id
WHERE p.fornecedor_id IS NOT NULL
  AND fp.id IS NULL;

-- ------------------------------------------------------------
-- 8. Índices auxiliares. O bloco abaixo é tolerante a execução
--    repetida: os índices só são criados se ainda não existirem.
-- ------------------------------------------------------------
SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='fornecedores' AND index_name='idx_fornecedor_cep'),
    'SELECT 1',
    'ALTER TABLE fornecedores ADD INDEX idx_fornecedor_cep (cep)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='produtos' AND index_name='idx_produto_fornecedor_v03'),
    'SELECT 1',
    'ALTER TABLE produtos ADD INDEX idx_produto_fornecedor_v03 (fornecedor_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=@db AND table_name='compras' AND index_name='idx_compra_fornecedor_v03'),
    'SELECT 1',
    'ALTER TABLE compras ADD INDEX idx_compra_fornecedor_v03 (fornecedor_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 9. Tabela de configuração da V03 (opcional, preparada para
--    futuras integrações sem hard-code).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL,
    valor TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO configuracoes_sistema (chave, valor)
VALUES ('versao_sistema', 'V03')
ON DUPLICATE KEY UPDATE valor='V03';

-- ------------------------------------------------------------
-- 10. Conferência pós-migração
-- ------------------------------------------------------------
SELECT 'V03 - Fornecedores' AS verificacao, COUNT(*) AS quantidade FROM fornecedores;
SELECT 'V03 - Produtos com fornecedor' AS verificacao, COUNT(*) AS quantidade FROM produtos WHERE fornecedor_id IS NOT NULL;
SELECT 'V03 - Compras com fornecedor' AS verificacao, COUNT(*) AS quantidade FROM compras WHERE fornecedor_id IS NOT NULL;
SELECT 'V03 - Clientes com endereço estruturado' AS verificacao, COUNT(*) AS quantidade FROM baseinformacoes WHERE cep IS NOT NULL;

-- IMPORTANTE:
-- As tabelas/colunas legadas fornecedores_produtos e produtos.fornecedor_produto_id
-- NÃO são removidas nesta etapa para preservar compatibilidade e histórico.
