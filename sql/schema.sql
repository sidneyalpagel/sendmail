-- =====================================================================
--  Disparador de e-mails - Prefeitura Municipal de Santa Helena
--  Esquema do banco (MariaDB 10.6+ / MySQL 8+)
-- =====================================================================

CREATE TABLE IF NOT EXISTS operadores (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome         VARCHAR(120)  NOT NULL,
    login        VARCHAR(60)   NOT NULL UNIQUE,
    email        VARCHAR(190)  NOT NULL,
    senha_hash   VARCHAR(255)  NOT NULL,
    papel        ENUM('admin','operador') NOT NULL DEFAULT 'operador',
    ativo        TINYINT(1)    NOT NULL DEFAULT 1,
    ultimo_acesso DATETIME     NULL,
    criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contatos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome         VARCHAR(150)  NOT NULL,
    email        VARCHAR(190)  NOT NULL,
    bairro       VARCHAR(120)  NULL,
    telefone     VARCHAR(30)   NULL,
    documento    VARCHAR(20)   NULL,
    observacao   VARCHAR(255)  NULL,
    origem       VARCHAR(40)   NOT NULL DEFAULT 'manual',
    ativo        TINYINT(1)    NOT NULL DEFAULT 1,
    opt_out      TINYINT(1)    NOT NULL DEFAULT 0,
    opt_out_em   DATETIME      NULL,
    criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contato_email (email),
    KEY idx_contato_bairro (bairro),
    KEY idx_contato_envio (ativo, opt_out)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modelos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome         VARCHAR(120)  NOT NULL,
    assunto      VARCHAR(255)  NOT NULL,
    corpo        MEDIUMTEXT    NOT NULL,
    ativo        TINYINT(1)    NOT NULL DEFAULT 1,
    criado_por   INT UNSIGNED  NULL,
    criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campanhas (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome         VARCHAR(150)  NOT NULL,
    assunto      VARCHAR(255)  NOT NULL,
    corpo        MEDIUMTEXT    NOT NULL,
    modelo_id    INT UNSIGNED  NULL,
    escopo       ENUM('contato','bairro','todos') NOT NULL,
    escopo_valor VARCHAR(190)  NULL,
    status       ENUM('rascunho','na_fila','enviando','pausada','concluida','cancelada')
                 NOT NULL DEFAULT 'rascunho',
    total        INT UNSIGNED  NOT NULL DEFAULT 0,
    enviados     INT UNSIGNED  NOT NULL DEFAULT 0,
    falhas       INT UNSIGNED  NOT NULL DEFAULT 0,
    suprimidos   INT UNSIGNED  NOT NULL DEFAULT 0,
    criado_por   INT UNSIGNED  NULL,
    criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    iniciado_em  DATETIME      NULL,
    concluido_em DATETIME      NULL,
    KEY idx_campanha_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fila (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campanha_id  INT UNSIGNED  NOT NULL,
    contato_id   INT UNSIGNED  NULL,
    nome         VARCHAR(150)  NOT NULL,
    email        VARCHAR(190)  NOT NULL,
    bairro       VARCHAR(120)  NULL,
    status       ENUM('pendente','enviando','enviado','falha','suprimido')
                 NOT NULL DEFAULT 'pendente',
    tentativas   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_erro  VARCHAR(500)  NULL,
    message_id   VARCHAR(190)  NULL,
    liberar_em   DATETIME      NULL,
    enviado_em   DATETIME      NULL,
    KEY idx_fila_trabalho (status, liberar_em),
    KEY idx_fila_campanha (campanha_id, status),
    KEY idx_fila_janela (status, enviado_em),
    CONSTRAINT fk_fila_campanha FOREIGN KEY (campanha_id)
        REFERENCES campanhas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operador_id  INT UNSIGNED  NULL,
    operador_nome VARCHAR(120) NULL,
    acao         VARCHAR(60)   NOT NULL,
    entidade     VARCHAR(40)   NULL,
    entidade_id  VARCHAR(40)   NULL,
    detalhe      VARCHAR(500)  NULL,
    ip           VARCHAR(45)   NULL,
    criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auditoria_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parametros (
    chave        VARCHAR(60)   PRIMARY KEY,
    valor        VARCHAR(255)  NOT NULL,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO parametros (chave, valor) VALUES
    ('envios_por_dia',    '1000'),
    ('envios_por_minuto', '20'),
    ('max_tentativas',    '3'),
    ('pausa_global',      '0');
