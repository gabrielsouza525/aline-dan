-- Aline Dan · Estrutura completa do banco de dados (instalação do zero)
-- Execute com: mysql -u root < setup/schema.sql
-- (bancos já existentes: use setup/migrate-v2.sql)

CREATE DATABASE IF NOT EXISTS aline_dan
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE aline_dan;

CREATE TABLE IF NOT EXISTS users (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(120)  NOT NULL,
  email             VARCHAR(190)  NOT NULL UNIQUE,
  phone             VARCHAR(20)   NOT NULL,
  password_hash     VARCHAR(255)  NOT NULL,
  role              ENUM('client','admin') NOT NULL DEFAULT 'client',
  email_verified_at DATETIME      NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id           VARCHAR(30)  PRIMARY KEY,
  name         VARCHAR(80)  NOT NULL,
  category     VARCHAR(40)  NOT NULL DEFAULT 'Outros',
  description  VARCHAR(255) NOT NULL DEFAULT '',
  duration_min INT UNSIGNED NOT NULL,
  price        DECIMAL(8,2) NOT NULL,
  price_from   TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = exibe "a partir de"
  icon         VARCHAR(30)  NOT NULL DEFAULT 'sparkles',
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0
) ENGINE = InnoDB;

-- Catálogo real do salão: rode setup/migrate-v3.sql (parte dos INSERTs)
-- após criar as tabelas.

CREATE TABLE IF NOT EXISTS bookings (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NULL,           -- NULL = agendamento de balcão
  service_id       VARCHAR(30)  NOT NULL,
  pro_id           VARCHAR(30)  NOT NULL,
  booking_date     DATE         NOT NULL,
  booking_time     TIME         NOT NULL,
  guest_name       VARCHAR(120) NULL,
  guest_phone      VARCHAR(20)  NULL,
  price            DECIMAL(8,2) NOT NULL DEFAULT 0,  -- congelado no momento do agendamento
  duration_min     INT UNSIGNED NOT NULL DEFAULT 60, -- idem
  status           ENUM('confirmado','cancelado','falta')
                   NOT NULL DEFAULT 'confirmado',    -- cancelar marca, não apaga
  cancelled_at     DATETIME     NULL,
  cancelled_by     ENUM('cliente','salao') NULL,
  reminder_sent_at DATETIME     NULL,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_user
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  KEY idx_slot (booking_date, booking_time),
  KEY idx_user (user_id, booking_date),
  KEY idx_status_data (status, booking_date)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS schedule_blocks (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pro_id     VARCHAR(30)  NOT NULL,  -- aline | daniela | beatriz | all
  block_date DATE         NOT NULL,
  start_time TIME         NOT NULL,
  end_time   TIME         NOT NULL,
  reason     VARCHAR(120) NOT NULL DEFAULT '',
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_block_date (block_date)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS user_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  kind       ENUM('verify','reset') NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hash (token_hash),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Conta da administradora: crie manualmente (o cadastro do site só cria clientes).
-- Gere o hash com:  php -r "echo password_hash('SUA_SENHA', PASSWORD_DEFAULT);"
-- INSERT INTO users (name, email, phone, password_hash, role)
-- VALUES ('Aline', 'aline@alinedan.com', '(11) 99999-9999', '<hash>', 'admin');
