-- Aline Dan · Migração v2
-- Serviços no banco, bloqueios de agenda, tokens (senha/e-mail),
-- agendamento de balcão (convidadas) e lembretes.

USE aline_dan;

-- Serviços editáveis pelo painel
CREATE TABLE IF NOT EXISTS services (
  id           VARCHAR(30)  PRIMARY KEY,
  name         VARCHAR(80)  NOT NULL,
  description  VARCHAR(255) NOT NULL DEFAULT '',
  duration_min INT UNSIGNED NOT NULL,
  price        DECIMAL(8,2) NOT NULL,
  icon         VARCHAR(30)  NOT NULL DEFAULT 'sparkles',
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0
) ENGINE = InnoDB;

INSERT INTO services (id, name, description, duration_min, price, icon, sort_order) VALUES
  ('corte',      'Corte Feminino',        'Corte personalizado com análise do formato do rosto, lavagem e finalização.', 60,  90.00,  'scissors', 1),
  ('escova',     'Escova & Finalização',  'Escova modelada lisa ou com ondas, com proteção térmica.',                    45,  70.00,  'wind',     2),
  ('coloracao',  'Coloração',             'Cobertura completa ou retoque de raiz com colorimetria profissional.',        120, 180.00, 'palette',  3),
  ('mechas',     'Mechas & Luzes',        'Balayage, morena iluminada ou loiro dos sonhos, com matização inclusa.',      180, 260.00, 'sparkles', 4),
  ('hidratacao', 'Hidratação Profunda',   'Cronograma capilar com máscara profissional e vapor de ozônio.',              60,  110.00, 'droplet',  5),
  ('penteado',   'Penteado para Eventos', 'Penteados para festas, formaturas e noivas, com teste opcional.',             90,  150.00, 'crown',    6)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Bloqueios de agenda (férias, almoço, imprevistos)
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

-- Tokens de redefinição de senha e verificação de e-mail
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

-- Verificação de e-mail
ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL;

-- Agendamentos: balcão (sem cadastro), preço/duração congelados e lembrete
ALTER TABLE bookings DROP FOREIGN KEY fk_bookings_user;
ALTER TABLE bookings
  MODIFY user_id INT UNSIGNED NULL,
  ADD COLUMN guest_name       VARCHAR(120) NULL,
  ADD COLUMN guest_phone      VARCHAR(20)  NULL,
  ADD COLUMN price            DECIMAL(8,2) NOT NULL DEFAULT 0,
  ADD COLUMN duration_min     INT UNSIGNED NOT NULL DEFAULT 60,
  ADD COLUMN reminder_sent_at DATETIME NULL;
ALTER TABLE bookings
  ADD CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;

-- Congela preço/duração dos agendamentos existentes
UPDATE bookings b JOIN services s ON s.id = b.service_id
   SET b.price = s.price, b.duration_min = s.duration_min;
