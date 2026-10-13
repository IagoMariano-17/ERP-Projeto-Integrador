-- ============================================================
-- ERP Gestão LGPD - V03 FINAL
-- Evolução incremental da V02/V03.
-- NÃO apagar tabelas nem dados existentes.
-- Execute no banco atualmente usado pelo sistema.
-- ============================================================

-- 1) Fornecedor único: tipo de fornecimento
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS tipo_fornecimento ENUM('PRODUTO','MATERIAL','AMBOS') NOT NULL DEFAULT 'PRODUTO' AFTER cnpj;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS cep VARCHAR(9) NULL AFTER email;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS numero VARCHAR(20) NULL AFTER endereco;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS complemento VARCHAR(100) NULL AFTER numero;
ALTER TABLE fornecedores ADD COLUMN IF NOT EXISTS bairro VARCHAR(100) NULL AFTER complemento;

-- 2) Endereço estruturado de clientes
ALTER TABLE baseinformacoes ADD COLUMN IF NOT EXISTS numero VARCHAR(20) NULL AFTER endereco;
ALTER TABLE baseinformacoes ADD COLUMN IF NOT EXISTS complemento VARCHAR(100) NULL AFTER numero;
ALTER TABLE baseinformacoes ADD COLUMN IF NOT EXISTS cep VARCHAR(9) NULL AFTER email;
ALTER TABLE baseinformacoes ADD COLUMN IF NOT EXISTS bairro VARCHAR(100) NULL AFTER cep;
ALTER TABLE baseinformacoes ADD COLUMN IF NOT EXISTS estado CHAR(2) NULL AFTER cidade;

-- 3) Manter compatibilidade com cadastro legado de fornecedores de produtos
CREATE TABLE IF NOT EXISTS fornecedores_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedores_produtos_nome(nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Migrar fornecedores legados que ainda não estejam no cadastro único
INSERT INTO fornecedores (razao_social,nome_fantasia,status,tipo_fornecimento)
SELECT fp.nome,fp.nome,CASE WHEN fp.status='INATIVO' THEN 'INATIVO' ELSE 'ATIVO' END,'PRODUTO'
FROM fornecedores_produtos fp
LEFT JOIN fornecedores f ON f.nome_fantasia=fp.nome OR f.razao_social=fp.nome
WHERE f.id IS NULL AND TRIM(fp.nome)<>'';

-- 5) Vincular produtos e compras existentes ao fornecedor único
UPDATE produtos p
JOIN fornecedores_produtos fp ON fp.id=p.fornecedor_produto_id
JOIN fornecedores f ON f.nome_fantasia=fp.nome OR f.razao_social=fp.nome
SET p.fornecedor_id=f.id
WHERE p.fornecedor_id IS NULL;

UPDATE compras c
JOIN fornecedores_produtos fp ON fp.id=c.fornecedor_produto_id
JOIN fornecedores f ON f.nome_fantasia=fp.nome OR f.razao_social=fp.nome
SET c.fornecedor_id=f.id
WHERE c.fornecedor_id IS NULL;

-- 6) Relacionamentos de produtos existentes
INSERT INTO fornecedor_produtos(fornecedor_id,produto_id,preco_unitario,fornecedor_preferencial,observacoes)
SELECT p.fornecedor_id,p.id,p.preco_custo,1,'Relação migrada na V03'
FROM produtos p
LEFT JOIN fornecedor_produtos fp ON fp.fornecedor_id=p.fornecedor_id AND fp.produto_id=p.id
WHERE p.fornecedor_id IS NOT NULL AND fp.id IS NULL;

-- 7) Inferir o tipo do fornecedor pelos relacionamentos existentes.
UPDATE fornecedores f
SET tipo_fornecimento=CASE
  WHEN EXISTS(SELECT 1 FROM fornecedor_produtos fp WHERE fp.fornecedor_id=f.id)
   AND EXISTS(SELECT 1 FROM fornecedor_suprimentos fs WHERE fs.fornecedor_id=f.id) THEN 'AMBOS'
  WHEN EXISTS(SELECT 1 FROM fornecedor_suprimentos fs WHERE fs.fornecedor_id=f.id) THEN 'MATERIAL'
  ELSE 'PRODUTO'
END;

-- 8) Configuração da versão
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL,
    valor TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_chave(chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO configuracoes_sistema(chave,valor) VALUES('versao_sistema','V03')
ON DUPLICATE KEY UPDATE valor='V03';

-- 9) Conferência
SELECT 'Fornecedores' verificacao,COUNT(*) quantidade FROM fornecedores;
SELECT 'Fornecedores produto' verificacao,COUNT(*) quantidade FROM fornecedores WHERE tipo_fornecimento IN('PRODUTO','AMBOS');
SELECT 'Fornecedores material' verificacao,COUNT(*) quantidade FROM fornecedores WHERE tipo_fornecimento IN('MATERIAL','AMBOS');
SELECT 'Produtos' verificacao,COUNT(*) quantidade FROM produtos WHERE status='ATIVO';
SELECT 'Materiais de uso' verificacao,COUNT(*) quantidade FROM suprimentos WHERE status='ATIVO';

-- IMPORTANTE:
-- As tabelas legadas fornecedores_produtos e produtos.fornecedor_produto_id
-- são preservadas. A Gestão da Loja, Compras e Vendas utilizam o cadastro único.
