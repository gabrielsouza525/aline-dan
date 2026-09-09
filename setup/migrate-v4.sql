-- Aline Dan · v4 — cancelamento vira status, em vez de apagar a linha
--
-- Antes, cancelar rodava DELETE: o agendamento sumia e não sobrava registro
-- de quem desmarcou nem quando. Agora a linha fica, com o motivo do sumiço.
--
-- 'falta' já entra no ENUM porque marcar quem não apareceu é o próximo passo
-- natural, e assim não precisa de outra migração para isso.

ALTER TABLE bookings
  ADD COLUMN status ENUM('confirmado','cancelado','falta')
      NOT NULL DEFAULT 'confirmado' AFTER duration_min,
  ADD COLUMN cancelled_at DATETIME NULL AFTER status,
  ADD COLUMN cancelled_by ENUM('cliente','salao') NULL AFTER cancelled_at;

-- A agenda quase sempre pergunta "o que está confirmado neste dia?"
ALTER TABLE bookings
  ADD INDEX idx_status_data (status, booking_date);
