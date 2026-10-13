-- ============================================================
-- ERP - Estoque + Suprimentos + Compras + apoio à decisão
-- INSTALAÇÃO SEGURA / COMPATÍVEL
--
-- Este arquivo NÃO apaga e NÃO recria tabelas legadas.
-- Em especial: baseinformacoes, devolucoes e encomendas são preservadas.
-- Execute este arquivo com o banco do InfinityFree selecionado no phpMyAdmin antes de usar os módulos.
-- ============================================================

CREATE TABLE IF NOT EXISTS fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(150) NOT NULL,
    nome_fantasia VARCHAR(150), cnpj VARCHAR(18), contato VARCHAR(100), telefone VARCHAR(30), email VARCHAR(150),
    endereco VARCHAR(255), cidade VARCHAR(100), estado CHAR(2), observacoes TEXT,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedor_cnpj (cnpj), INDEX idx_fornecedor_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorias_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL, descricao VARCHAR(255),
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_categoria_nome(nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL, sku VARCHAR(80), nome VARCHAR(150) NOT NULL, categoria_id INT NULL, descricao TEXT,
    unidade_medida VARCHAR(20) NOT NULL DEFAULT 'UN', preco_custo DECIMAL(12,2) NOT NULL DEFAULT 0,
    preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0, estoque_minimo DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_seguranca DECIMAL(12,2) NOT NULL DEFAULT 0, ponto_reposicao DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_maximo DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_produto_codigo(codigo), UNIQUE KEY uk_produto_sku(sku), INDEX idx_produto_categoria(categoria_id),
    CONSTRAINT fk_produto_categoria FOREIGN KEY(categoria_id) REFERENCES categorias_produtos(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suprimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50), nome VARCHAR(150) NOT NULL, descricao TEXT, unidade_medida VARCHAR(20) NOT NULL DEFAULT 'UN',
    estoque_minimo DECIMAL(12,2) NOT NULL DEFAULT 0, estoque_seguranca DECIMAL(12,2) NOT NULL DEFAULT 0,
    ponto_reposicao DECIMAL(12,2) NOT NULL DEFAULT 0, estoque_maximo DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_atual DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_suprimento_codigo(codigo), INDEX idx_suprimento_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fornecedor_suprimentos (
    id INT AUTO_INCREMENT PRIMARY KEY, fornecedor_id INT NOT NULL, suprimento_id INT NOT NULL,
    codigo_fornecedor VARCHAR(80), preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    prazo_entrega_dias INT NOT NULL DEFAULT 0, quantidade_minima DECIMAL(12,2) NOT NULL DEFAULT 1,
    fornecedor_preferencial TINYINT(1) NOT NULL DEFAULT 0, observacoes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedor_suprimento(fornecedor_id,suprimento_id),
    CONSTRAINT fk_fs_fornecedor FOREIGN KEY(fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE,
    CONSTRAINT fk_fs_suprimento FOREIGN KEY(suprimento_id) REFERENCES suprimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fornecedor_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY, fornecedor_id INT NOT NULL, produto_id INT NOT NULL,
    codigo_fornecedor VARCHAR(80), preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    prazo_entrega_dias INT NOT NULL DEFAULT 0, quantidade_minima DECIMAL(12,2) NOT NULL DEFAULT 1,
    fornecedor_preferencial TINYINT(1) NOT NULL DEFAULT 0, observacoes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedor_produto(fornecedor_id,produto_id),
    CONSTRAINT fk_fp_fornecedor FOREIGN KEY(fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE,
    CONSTRAINT fk_fp_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS estoque (
    id INT AUTO_INCREMENT PRIMARY KEY, produto_id INT NOT NULL,
    local_estoque ENUM('FISICO','MERCADO_LIVRE') NOT NULL DEFAULT 'FISICO',
    quantidade DECIMAL(12,2) NOT NULL DEFAULT 0, quantidade_reservada DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_estoque_produto_local(produto_id,local_estoque), INDEX idx_estoque_local(local_estoque),
    CONSTRAINT fk_estoque_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id BIGINT AUTO_INCREMENT PRIMARY KEY, produto_id INT NOT NULL,
    local_estoque ENUM('FISICO','MERCADO_LIVRE') NOT NULL DEFAULT 'FISICO', tipo VARCHAR(40) NOT NULL,
    quantidade DECIMAL(12,2) NOT NULL, estoque_anterior DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_posterior DECIMAL(12,2) NOT NULL DEFAULT 0, motivo VARCHAR(255), usuario_id INT NULL,
    referencia_tipo VARCHAR(50), referencia_id INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mov_produto(produto_id), INDEX idx_mov_data(created_at),
    CONSTRAINT fk_mov_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS movimentacoes_suprimentos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY, suprimento_id INT NOT NULL, tipo VARCHAR(40) NOT NULL,
    quantidade DECIMAL(12,2) NOT NULL, estoque_anterior DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_posterior DECIMAL(12,2) NOT NULL DEFAULT 0, motivo VARCHAR(255), usuario_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_ms_sup_data(suprimento_id,created_at),
    CONSTRAINT fk_ms_suprimento FOREIGN KEY(suprimento_id) REFERENCES suprimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compras (
    id INT AUTO_INCREMENT PRIMARY KEY, numero VARCHAR(40), fornecedor_id INT NOT NULL, data_compra DATE NOT NULL,
    data_prevista_entrega DATE NULL, data_recebimento DATE NULL,
    status ENUM('RASCUNHO','PEDIDA','PARCIAL','RECEBIDA','CANCELADA') NOT NULL DEFAULT 'RASCUNHO',
    tipo ENUM('PRODUTO','SUPRIMENTO','MISTA') NOT NULL DEFAULT 'PRODUTO', valor_frete DECIMAL(12,2) NOT NULL DEFAULT 0,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0, observacoes TEXT, usuario_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_compra_fornecedor(fornecedor_id), INDEX idx_compra_data(data_compra),
    CONSTRAINT fk_compra_fornecedor FOREIGN KEY(fornecedor_id) REFERENCES fornecedores(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compra_itens (
    id INT AUTO_INCREMENT PRIMARY KEY, compra_id INT NOT NULL, tipo_item ENUM('PRODUTO','SUPRIMENTO') NOT NULL,
    produto_id INT NULL, suprimento_id INT NULL, quantidade DECIMAL(12,2) NOT NULL,
    quantidade_recebida DECIMAL(12,2) NOT NULL DEFAULT 0, preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0, observacoes TEXT,
    CONSTRAINT fk_ci_compra FOREIGN KEY(compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ci_suprimento FOREIGN KEY(suprimento_id) REFERENCES suprimentos(id) ON DELETE RESTRICT,
    INDEX idx_ci_produto(produto_id), INDEX idx_ci_suprimento(suprimento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demandas_compra (
    id INT AUTO_INCREMENT PRIMARY KEY, numero VARCHAR(40), data_solicitacao DATE NOT NULL, solicitante_id INT NULL,
    prioridade ENUM('BAIXA','NORMAL','ALTA','URGENTE') NOT NULL DEFAULT 'NORMAL',
    status ENUM('RASCUNHO','PENDENTE','EM_COTACAO','APROVADA','COMPRADA','CANCELADA') NOT NULL DEFAULT 'RASCUNHO',
    origem ENUM('MANUAL','ESTOQUE_BAIXO','SUGESTAO_SISTEMA') NOT NULL DEFAULT 'MANUAL', observacoes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demanda_compra_itens (
    id INT AUTO_INCREMENT PRIMARY KEY, demanda_id INT NOT NULL, tipo_item ENUM('PRODUTO','SUPRIMENTO') NOT NULL,
    produto_id INT NULL, suprimento_id INT NULL, quantidade_sugerida DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantidade_solicitada DECIMAL(12,2) NOT NULL DEFAULT 0, fornecedor_id INT NULL, prazo_entrega_dias INT NULL,
    preco_estimado DECIMAL(12,2) NULL, justificativa VARCHAR(255), observacoes TEXT,
    CONSTRAINT fk_dci_demanda FOREIGN KEY(demanda_id) REFERENCES demandas_compra(id) ON DELETE CASCADE,
    CONSTRAINT fk_dci_produto FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dci_suprimento FOREIGN KEY(suprimento_id) REFERENCES suprimentos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dci_fornecedor FOREIGN KEY(fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
    INDEX idx_dci_produto(produto_id), INDEX idx_dci_suprimento(suprimento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MIGRAÇÃO DE INSTALAÇÕES ANTERIORES DO MÓDULO
-- ============================================================
SET @db = DATABASE();

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='produtos' AND COLUMN_NAME='estoque_seguranca')=0,
 'ALTER TABLE produtos ADD COLUMN estoque_seguranca DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER estoque_minimo','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='produtos' AND COLUMN_NAME='ponto_reposicao')=0,
 'ALTER TABLE produtos ADD COLUMN ponto_reposicao DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER estoque_seguranca','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='suprimentos' AND COLUMN_NAME='estoque_seguranca')=0,
 'ALTER TABLE suprimentos ADD COLUMN estoque_seguranca DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER estoque_minimo','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='suprimentos' AND COLUMN_NAME='ponto_reposicao')=0,
 'ALTER TABLE suprimentos ADD COLUMN ponto_reposicao DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER estoque_seguranca','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='estoque' AND COLUMN_NAME='local_estoque')=0,
 'ALTER TABLE estoque ADD COLUMN local_estoque ENUM(\'FISICO\',\'MERCADO_LIVRE\') NOT NULL DEFAULT \'FISICO\' AFTER produto_id','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='movimentacoes_estoque' AND COLUMN_NAME='local_estoque')=0,
 'ALTER TABLE movimentacoes_estoque ADD COLUMN local_estoque ENUM(\'FISICO\',\'MERCADO_LIVRE\') NOT NULL DEFAULT \'FISICO\' AFTER produto_id','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Registros de estoque para produtos existentes.
INSERT IGNORE INTO estoque(produto_id,local_estoque,quantidade,quantidade_reservada)
SELECT id,'FISICO',0,0 FROM produtos;
INSERT IGNORE INTO estoque(produto_id,local_estoque,quantidade,quantidade_reservada)
SELECT id,'MERCADO_LIVRE',0,0 FROM produtos;

-- IMPORTANTE: a tabela legada `devolucoes` NÃO é criada, removida ou modificada aqui.
-- O módulo novo deve usar a tela legada de Devoluções até que uma integração seja feita explicitamente.
