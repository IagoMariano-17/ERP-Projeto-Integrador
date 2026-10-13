CREATE TABLE IF NOT EXISTS fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(150) NOT NULL,
    nome_fantasia VARCHAR(150),
    cnpj VARCHAR(18),
    contato VARCHAR(100),
    telefone VARCHAR(20),
    email VARCHAR(150),
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    estado CHAR(2),
    observacoes TEXT,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fornecedor_cnpj (cnpj),
    INDEX idx_fornecedor_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS suprimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50),
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    unidade_medida VARCHAR(20) NOT NULL,
    estoque_minimo DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_atual DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('ATIVO','INATIVO') NOT NULL DEFAULT 'ATIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_suprimento_codigo (codigo),
    INDEX idx_suprimento_nome (nome),
    INDEX idx_suprimento_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fornecedor_suprimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fornecedor_id INT NOT NULL,
    suprimento_id INT NOT NULL,
    codigo_fornecedor VARCHAR(50),
    preco_unitario DECIMAL(12,2),
    prazo_entrega_dias INT,
    quantidade_minima DECIMAL(12,2) NOT NULL DEFAULT 1,
    fornecedor_preferencial TINYINT(1) NOT NULL DEFAULT 0,
    observacoes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fs_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fs_suprimento FOREIGN KEY (suprimento_id) REFERENCES suprimentos(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uk_fornecedor_suprimento (fornecedor_id, suprimento_id),
    INDEX idx_fs_fornecedor (fornecedor_id),
    INDEX idx_fs_suprimento (suprimento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demandas_compra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(30),
    data_solicitacao DATE NOT NULL,
    solicitante_id INT NULL,
    prioridade ENUM('BAIXA','NORMAL','ALTA','URGENTE') NOT NULL DEFAULT 'NORMAL',
    status ENUM('RASCUNHO','PENDENTE','EM_COTACAO','APROVADA','COMPRADA','RECEBIDA','CANCELADA') NOT NULL DEFAULT 'PENDENTE',
    observacoes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_demanda_status (status),
    INDEX idx_demanda_data (data_solicitacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demanda_compra_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demanda_id INT NOT NULL,
    suprimento_id INT NOT NULL,
    quantidade DECIMAL(12,2) NOT NULL,
    fornecedor_id INT NULL,
    prazo_entrega_dias INT NULL,
    preco_estimado DECIMAL(12,2) NULL,
    observacoes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dci_demanda FOREIGN KEY (demanda_id) REFERENCES demandas_compra(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dci_suprimento FOREIGN KEY (suprimento_id) REFERENCES suprimentos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dci_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_dci_demanda (demanda_id),
    INDEX idx_dci_suprimento (suprimento_id),
    INDEX idx_dci_fornecedor (fornecedor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
