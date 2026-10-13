-- ============================================================
-- ERP Gestão LGPD - V03 CONSOLIDADA
-- Migração incremental da V02/V03.
-- NÃO apagar tabelas ou dados existentes.
-- Execute no banco atualmente utilizado pelo sistema.
-- ============================================================

-- 1) Cadastro único de fornecedores
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

-- 3) Compatibilidade com fornecedores legados
CREATE TABLE IF NOT EXISTS fornecedores_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedores_produtos_nome(nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fornecedores (razao_social,nome_fantasia,status,tipo_fornecimento)
SELECT fp.nome,fp.nome,CASE WHEN fp.status='INATIVO' THEN 'INATIVO' ELSE 'ATIVO' END,'PRODUTO'
FROM fornecedores_produtos fp
LEFT JOIN fornecedores f ON f.nome_fantasia=fp.nome OR f.razao_social=fp.nome
WHERE f.id IS NULL AND TRIM(fp.nome)<>'';

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

INSERT INTO fornecedor_produtos(fornecedor_id,produto_id,preco_unitario,fornecedor_preferencial,observacoes)
SELECT p.fornecedor_id,p.id,p.preco_custo,1,'Relação migrada na V03'
FROM produtos p
LEFT JOIN fornecedor_produtos fp ON fp.fornecedor_id=p.fornecedor_id AND fp.produto_id=p.id
WHERE p.fornecedor_id IS NOT NULL AND fp.id IS NULL;

UPDATE fornecedores f
SET tipo_fornecimento=CASE
  WHEN EXISTS(SELECT 1 FROM fornecedor_produtos fp WHERE fp.fornecedor_id=f.id)
   AND EXISTS(SELECT 1 FROM fornecedor_suprimentos fs WHERE fs.fornecedor_id=f.id) THEN 'AMBOS'
  WHEN EXISTS(SELECT 1 FROM fornecedor_suprimentos fs WHERE fs.fornecedor_id=f.id) THEN 'MATERIAL'
  ELSE 'PRODUTO'
END;

-- 4) Garantir canais de venda usados pela V03
ALTER TABLE vendas MODIFY canal ENUM('MERCADO_LIVRE','LOCAL','OUTRO') NOT NULL DEFAULT 'LOCAL';

-- 5) Transferências para Mercado Livre
CREATE TABLE IF NOT EXISTS transferencias_mercado_livre (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id INT NOT NULL,
    quantidade DECIMAL(15,3) NOT NULL,
    data_transferencia DATE NOT NULL,
    motivo VARCHAR(255) NULL,
    usuario_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    KEY idx_tml_produto(produto_id),
    CONSTRAINT fk_tml_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) Registro de baixa por uso de Material de Uso
CREATE TABLE IF NOT EXISTS consumos_materiais (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    material_id INT NOT NULL,
    data_uso DATE NOT NULL,
    quantidade DECIMAL(15,3) NOT NULL,
    motivo VARCHAR(255) NULL,
    usuario_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consumos_material (material_id),
    KEY idx_consumos_data (data_uso),
    CONSTRAINT fk_consumos_material FOREIGN KEY (material_id) REFERENCES suprimentos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) Configuração da versão
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL,
    valor TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_chave(chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO configuracoes_sistema(chave,valor) VALUES('versao_sistema','V03 CONSOLIDADA')
ON DUPLICATE KEY UPDATE valor='V03 CONSOLIDADA';

-- 8) Conferência rápida
SELECT 'Fornecedores' verificacao, COUNT(*) quantidade FROM fornecedores;
SELECT 'Produtos ativos' verificacao, COUNT(*) quantidade FROM produtos WHERE status<>'INATIVO';
SELECT 'Materiais de Uso ativos' verificacao, COUNT(*) quantidade FROM suprimentos WHERE status='ATIVO';
SELECT 'Consumos de materiais' verificacao, COUNT(*) quantidade FROM consumos_materiais;
SELECT 'Vendas' verificacao, COUNT(*) quantidade FROM vendas;

-- V03 Gestão da Loja: consolidação de abas, materiais de uso e inventário.
-- Não remove dados existentes. Estruturas abaixo são idempotentes.
CREATE TABLE IF NOT EXISTS consumos_materiais (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    material_id INT NOT NULL,
    data_uso DATE NOT NULL,
    quantidade DECIMAL(15,3) NOT NULL,
    motivo VARCHAR(255) NULL,
    usuario_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id), KEY idx_consumos_material(material_id), KEY idx_consumos_data(data_uso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
UPDATE configuracoes_sistema SET valor='V03 - Gestão da Loja final' WHERE chave='versao_sistema';
