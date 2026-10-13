-- ============================================================
-- ERP DEFINITIVO - ESTOQUE + SUPRIMENTOS + COMPRAS
-- Banco: projetointegrador_dev
--
-- ATENÇÃO:
-- 1) Este script APAGA E RECRIA SOMENTE as tabelas NOVAS do ERP.
-- 2) NÃO APAGA: baseinformacoes, usuarios, encomendas, devolucoes.
-- 3) Antes de executar, faça um backup do banco pelo phpMyAdmin.
-- 4) Como este é um ambiente local/de desenvolvimento, o objetivo é
--    eliminar incompatibilidades entre versões anteriores das tabelas.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS devolucoes_itens;
DROP TABLE IF EXISTS recebimento_itens;
DROP TABLE IF EXISTS recebimentos;
DROP TABLE IF EXISTS compra_itens;
DROP TABLE IF EXISTS compras;
DROP TABLE IF EXISTS demanda_compra_itens;
DROP TABLE IF EXISTS demandas_compra;
DROP TABLE IF EXISTS movimentacoes_suprimentos;
DROP TABLE IF EXISTS fornecedor_produtos;
DROP TABLE IF EXISTS fornecedor_suprimentos;
DROP TABLE IF EXISTS movimentacoes_estoque;
DROP TABLE IF EXISTS estoque;
DROP TABLE IF EXISTS venda_itens;
DROP TABLE IF EXISTS vendas;
DROP TABLE IF EXISTS suprimentos;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS categorias_produtos;
DROP TABLE IF EXISTS fornecedores;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FORNECEDORES
-- ============================================================
CREATE TABLE fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(150) NOT NULL,
    nome_fantasia VARCHAR(150) NULL,
    cnpj VARCHAR(18) NULL,
    contato VARCHAR(100) NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    endereco VARCHAR(255) NULL,
    cidade VARCHAR(100) NULL,
    estado CHAR(2) NULL,
    observacoes TEXT NULL,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedor_cnpj (cnpj),
    KEY idx_fornecedor_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CATEGORIAS E PRODUTOS
-- ============================================================
CREATE TABLE categorias_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NULL,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_categoria_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    sku VARCHAR(80) NULL,
    nome VARCHAR(150) NOT NULL,
    categoria_id INT NULL,
    descricao TEXT NULL,
    unidade_medida VARCHAR(20) NOT NULL DEFAULT 'UN',
    preco_custo DECIMAL(12,2) NOT NULL DEFAULT 0,
    preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_minimo DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_seguranca DECIMAL(12,2) NOT NULL DEFAULT 0,
    ponto_reposicao DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_maximo DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_produto_codigo (codigo),
    UNIQUE KEY uk_produto_sku (sku),
    KEY idx_produto_categoria (categoria_id),
    CONSTRAINT fk_produto_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias_produtos(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SUPRIMENTOS
-- ============================================================
CREATE TABLE suprimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    unidade_medida VARCHAR(20) NOT NULL DEFAULT 'UN',
    estoque_minimo DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_seguranca DECIMAL(12,2) NOT NULL DEFAULT 0,
    ponto_reposicao DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_maximo DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_atual DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_suprimento_codigo (codigo),
    KEY idx_suprimento_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fornecedor_suprimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fornecedor_id INT NOT NULL,
    suprimento_id INT NOT NULL,
    codigo_fornecedor VARCHAR(80) NULL,
    preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    prazo_entrega_dias INT NOT NULL DEFAULT 0,
    quantidade_minima DECIMAL(12,2) NOT NULL DEFAULT 1,
    fornecedor_preferencial TINYINT(1) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedor_suprimento (fornecedor_id, suprimento_id),
    KEY idx_fs_suprimento (suprimento_id),
    CONSTRAINT fk_fs_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE,
    CONSTRAINT fk_fs_suprimento FOREIGN KEY (suprimento_id) REFERENCES suprimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fornecedor_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fornecedor_id INT NOT NULL,
    produto_id INT NOT NULL,
    codigo_fornecedor VARCHAR(80) NULL,
    preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    prazo_entrega_dias INT NOT NULL DEFAULT 0,
    quantidade_minima DECIMAL(12,2) NOT NULL DEFAULT 1,
    fornecedor_preferencial TINYINT(1) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fornecedor_produto (fornecedor_id, produto_id),
    KEY idx_fp_produto (produto_id),
    CONSTRAINT fk_fp_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE,
    CONSTRAINT fk_fp_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ESTOQUE: FÍSICO + MERCADO LIVRE
-- ============================================================
CREATE TABLE estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    local_estoque ENUM('FISICO','MERCADO_LIVRE') NOT NULL DEFAULT 'FISICO',
    quantidade DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantidade_reservada DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_estoque_produto_local (produto_id, local_estoque),
    KEY idx_estoque_local (local_estoque),
    CONSTRAINT fk_estoque_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE movimentacoes_estoque (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    local_estoque ENUM('FISICO','MERCADO_LIVRE') NOT NULL DEFAULT 'FISICO',
    tipo VARCHAR(40) NOT NULL,
    quantidade DECIMAL(12,2) NOT NULL,
    estoque_anterior DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_posterior DECIMAL(12,2) NOT NULL DEFAULT 0,
    motivo VARCHAR(255) NULL,
    usuario_id INT NULL,
    referencia_tipo VARCHAR(50) NULL,
    referencia_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mov_produto (produto_id),
    KEY idx_mov_data (created_at),
    KEY idx_mov_local (local_estoque),
    CONSTRAINT fk_mov_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE movimentacoes_suprimentos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    suprimento_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    quantidade DECIMAL(12,2) NOT NULL,
    estoque_anterior DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_posterior DECIMAL(12,2) NOT NULL DEFAULT 0,
    motivo VARCHAR(255) NULL,
    usuario_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ms_sup_data (suprimento_id, created_at),
    CONSTRAINT fk_ms_suprimento FOREIGN KEY (suprimento_id) REFERENCES suprimentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- NECESSIDADE DE COMPRA
-- ============================================================
CREATE TABLE demandas_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(40) NULL,
    data_solicitacao DATE NOT NULL,
    solicitante_id INT NULL,
    prioridade ENUM('BAIXA','NORMAL','ALTA','URGENTE') NOT NULL DEFAULT 'NORMAL',
    status ENUM('RASCUNHO','PENDENTE','EM_COTACAO','APROVADA','COMPRADA','CANCELADA') NOT NULL DEFAULT 'RASCUNHO',
    origem ENUM('MANUAL','ESTOQUE_BAIXO','SUGESTAO_SISTEMA') NOT NULL DEFAULT 'MANUAL',
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_demanda_status (status),
    KEY idx_demanda_data (data_solicitacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE demanda_compra_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demanda_id INT NOT NULL,
    tipo_item ENUM('PRODUTO','SUPRIMENTO') NOT NULL,
    produto_id INT NULL,
    suprimento_id INT NULL,
    quantidade_sugerida DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantidade_solicitada DECIMAL(12,2) NOT NULL DEFAULT 0,
    fornecedor_id INT NULL,
    prazo_entrega_dias INT NULL,
    preco_estimado DECIMAL(12,2) NULL,
    justificativa VARCHAR(255) NULL,
    observacoes TEXT NULL,
    KEY idx_dci_demanda (demanda_id),
    KEY idx_dci_produto (produto_id),
    KEY idx_dci_suprimento (suprimento_id),
    CONSTRAINT fk_dci_demanda FOREIGN KEY (demanda_id) REFERENCES demandas_compra(id) ON DELETE CASCADE,
    CONSTRAINT fk_dci_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dci_suprimento FOREIGN KEY (suprimento_id) REFERENCES suprimentos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_dci_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- COMPRAS
-- ============================================================
CREATE TABLE compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(40) NULL,
    fornecedor_id INT NOT NULL,
    data_compra DATE NOT NULL,
    data_prevista_entrega DATE NULL,
    data_recebimento DATE NULL,
    status ENUM('RASCUNHO','PEDIDA','PARCIAL','RECEBIDA','CANCELADA') NOT NULL DEFAULT 'RASCUNHO',
    tipo ENUM('PRODUTO','SUPRIMENTO','MISTA') NOT NULL DEFAULT 'PRODUTO',
    valor_frete DECIMAL(12,2) NOT NULL DEFAULT 0,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    usuario_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_compra_fornecedor (fornecedor_id),
    KEY idx_compra_data (data_compra),
    KEY idx_compra_status (status),
    CONSTRAINT fk_compra_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE compra_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    tipo_item ENUM('PRODUTO','SUPRIMENTO') NOT NULL,
    produto_id INT NULL,
    suprimento_id INT NULL,
    quantidade DECIMAL(12,2) NOT NULL,
    quantidade_recebida DECIMAL(12,2) NOT NULL DEFAULT 0,
    preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    KEY idx_ci_compra (compra_id),
    KEY idx_ci_produto (produto_id),
    KEY idx_ci_suprimento (suprimento_id),
    CONSTRAINT fk_ci_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
    CONSTRAINT fk_ci_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ci_suprimento FOREIGN KEY (suprimento_id) REFERENCES suprimentos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- RECEBIMENTOS
-- ============================================================
CREATE TABLE recebimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    data_recebimento DATE NOT NULL,
    numero_nf VARCHAR(60) NULL,
    observacoes TEXT NULL,
    usuario_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_receb_compra (compra_id),
    KEY idx_receb_data (data_recebimento),
    CONSTRAINT fk_receb_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recebimento_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recebimento_id INT NOT NULL,
    compra_item_id INT NOT NULL,
    quantidade_recebida DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantidade_aceita DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantidade_avariada DECIMAL(12,2) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    KEY idx_ri_recebimento (recebimento_id),
    KEY idx_ri_compra_item (compra_item_id),
    CONSTRAINT fk_ri_recebimento FOREIGN KEY (recebimento_id) REFERENCES recebimentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_ri_compra_item FOREIGN KEY (compra_item_id) REFERENCES compra_itens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- VENDAS (estrutura de apoio para futura integração/relatórios)
-- ============================================================
CREATE TABLE vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(60) NULL,
    canal ENUM('MERCADO_LIVRE','OUTRO') NOT NULL DEFAULT 'OUTRO',
    data_venda DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('PENDENTE','PAGA','ENVIADA','CONCLUIDA','CANCELADA') NOT NULL DEFAULT 'PENDENTE',
    valor_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_venda_data (data_venda),
    KEY idx_venda_canal (canal),
    KEY idx_venda_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE venda_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade DECIMAL(12,2) NOT NULL,
    preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    KEY idx_vi_venda (venda_id),
    KEY idx_vi_produto (produto_id),
    CONSTRAINT fk_vi_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
    CONSTRAINT fk_vi_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- INICIALIZAÇÃO DO ESTOQUE POR PRODUTO
-- ============================================================
INSERT INTO estoque (produto_id, local_estoque, quantidade, quantidade_reservada)
SELECT id, 'FISICO', 0, 0 FROM produtos;

INSERT INTO estoque (produto_id, local_estoque, quantidade, quantidade_reservada)
SELECT id, 'MERCADO_LIVRE', 0, 0 FROM produtos;

-- ============================================================
-- IMPORTANTE: tabelas legadas preservadas
-- baseinformacoes, usuarios, encomendas e devolucoes NÃO são alteradas.
-- ============================================================
