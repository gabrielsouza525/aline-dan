-- =====================================================================
-- Aline Dan · Espaço Lounge — banco completo, pronto para importar
-- =====================================================================
--
-- Um arquivo só, com TUDO o que o site precisa para funcionar:
--   · as 4 tabelas, já com todas as migrations aplicadas
--   · os 73 serviços do catálogo, com preço, duração e categoria
--
-- Não é preciso importar schema.sql nem as migrations: este arquivo já
-- é o resultado final delas. Os outros continuam na pasta como histórico.
--
-- COMO USAR NA HOSPEDAGEM
--   1. Crie um banco vazio pelo painel (nome, usuário e senha).
--   2. Abra o phpMyAdmin, escolha esse banco, aba "Importar",
--      selecione este arquivo e confirme.
--   3. Preencha DB_NAME, DB_USER e DB_PASS em api/config.php.
--
-- Pode importar de novo sem medo: as tabelas usam IF NOT EXISTS e os
-- serviços usam INSERT IGNORE, então nada é apagado nem duplicado.
--
-- NÃO VEM AQUI, de propósito:
--   · clientes, agendamentos e bloqueios — dados de teste da máquina local
--   · a conta da administradora — a senha tem que ser escolhida por você,
--     em setup/instalar.php, e não pode vir num arquivo que fica no Git
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Estrutura
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('client','admin') NOT NULL DEFAULT 'client',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `services` (
  `id` varchar(30) NOT NULL,
  `name` varchar(80) NOT NULL,
  `category` varchar(40) NOT NULL DEFAULT 'Outros',
  `description` varchar(255) NOT NULL DEFAULT '',
  `duration_min` int(10) unsigned NOT NULL,
  `price` decimal(8,2) NOT NULL,
  `price_from` tinyint(1) NOT NULL DEFAULT 0,
  `icon` varchar(30) NOT NULL DEFAULT 'sparkles',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `service_id` varchar(30) NOT NULL,
  `pro_id` varchar(30) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `guest_name` varchar(120) DEFAULT NULL,
  `guest_phone` varchar(20) DEFAULT NULL,
  `price` decimal(8,2) NOT NULL DEFAULT 0.00,
  `duration_min` int(10) unsigned NOT NULL DEFAULT 60,
  `status` enum('confirmado','cancelado','falta') NOT NULL DEFAULT 'confirmado',
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` enum('cliente','salao') DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_slot` (`booking_date`,`booking_time`),
  KEY `idx_user` (`user_id`,`booking_date`),
  KEY `idx_status_data` (`status`,`booking_date`),
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `schedule_blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pro_id` varchar(30) NOT NULL,
  `block_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `reason` varchar(120) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_block_date` (`block_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catálogo de serviços (fonte: trinks.com/espaco-lounge-aline-dan)
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('aplicacao-coloracao','Aplicação de Coloração','Cabelo','Aplicação de coloração com produto levado pela cliente.',60,100.00,1,'palette',1,1);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('babyliss','Baby Liss / Cachos','Penteados','Cachos manuais ou com babyliss.',60,80.00,1,'crown',1,69);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('banho-gel','Banho em Gel','Unhas em Gel','Banho em gel.',60,100.00,0,'star',1,63);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('blindagem-gel','Blindagem de Gel','Unhas Artificiais','Unhas naturais, flexíveis e fortes — indolor e sem odor.',120,90.00,1,'star',1,56);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('botox-capilar','Botox Capilar','Cabelo','Botox capilar, selante e acabamento.',120,250.00,1,'droplet',1,2);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('botox-produto-aline','Botox com Produto da Aline','Cabelo','Botox com produto do salão.',90,200.00,1,'droplet',1,3);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('buco-cera','Depilação de Buço na Cera','Depilação','Depilação do buço com cera.',20,25.00,1,'wind',1,73);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-brasileiro-87','Brasileiro - 87','Cílios','Manutenção.',60,87.00,0,'eye',1,40);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-cliente-aline','Cliente Aline','Cílios','Cílios — condição especial para clientes da Aline.',130,150.00,0,'eye',1,41);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-efeito-4d','Efeito 4D','Cílios','Extensão de cílios efeito 4D.',150,150.00,0,'eye',1,49);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-manut-4d','Manutenção 4D','Cílios','Manutenção do volume 4D.',100,110.00,0,'eye',1,42);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-manut-4d-70','Manutenção 4D - 70','Cílios','Manutenção do volume 4D.',60,70.00,0,'eye',1,43);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-manut-brasileiro','Manutenção Brasileiro','Cílios','Manutenção do volume brasileiro.',90,95.00,0,'eye',1,52);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-manut-classico','Manutenção (Clássico e Brasileiro)','Cílios','Manutenção dos volumes clássico e brasileiro.',90,90.00,0,'eye',1,50);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-manut-express-100','Manutenção Express','Cílios','Retorno em até 15 dias.',100,90.00,0,'eye',1,45);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-manut-express-90','Manutenção (Express)','Cílios','Retorno em até 15 dias.',90,90.00,0,'eye',1,51);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-manut-fox','Manutenção Fox','Cílios','Retorno em até 23 dias.',100,135.00,0,'eye',1,46);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-manut-fox-aline','Manutenção Efeito Fox Cliente Aline','Cílios','Retorno em até 23 dias.',120,130.00,0,'eye',1,44);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-remocao','Remoção dos Cílios','Cílios','Remoção da extensão de cílios.',30,30.00,0,'eye',1,53);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-vol-bras-aline','Volume Brasileiro Cliente Aline','Cílios','Condição especial para clientes da Aline.',120,140.00,0,'eye',1,47);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-vol-expr-aline','Volume Express Cliente Aline','Cílios','Condição especial para clientes da Aline.',120,125.00,0,'eye',1,48);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-volume-brasileiro','Volume (Brasileiro)','Cílios','Extensão de cílios volume brasileiro.',150,140.00,0,'eye',1,54);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('cilios-volume-express','Volume Express','Cílios','Extensão de cílios volume express.',150,125.00,0,'eye',1,55);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('coloracao','Coloração / Tonalização','Cabelo','Tintura dos cabelos com produtos do salão.',60,140.00,1,'palette',1,5);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('corte-feminino','Corte Feminino','Cabelo','Higienização, corte e secagem.',40,150.00,1,'scissors',1,6);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('corte-infantil','Corte Infantil','Cabelo','Corte infantil.',30,50.00,1,'scissors',1,7);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('corte-masculino','Corte Masculino','Cabelo','Higienização, corte e secagem.',30,80.00,1,'scissors',1,8);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('dedo-de-fibra','Dedo de Fibra','Unhas Artificiais','Apenas um dedo.',30,20.00,0,'star',1,57);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('design-sobrancelha','Design de Sobrancelha','Sobrancelha','Design de sobrancelha.',30,30.00,0,'pencil',1,66);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('design-sobrancelha-tp','Design de Sobrancelha T.P','Sobrancelha','Tirar e pintar.',40,40.00,0,'pencil',1,67);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('escalda-pes','Escalda Pés','Mãos e Pés','Escalda pés relaxante.',30,50.00,0,'droplet',1,39);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('escova-prancha','Escova e Prancha','Cabelo','Lavagem, escova e prancha.',60,70.00,1,'wind',1,9);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('escova-simples','Escova Simples','Cabelo','Lavagem e escova.',30,60.00,1,'wind',1,11);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('esmaltacao-gel','Esmaltação em Gel','Unhas em Gel','Esmaltação em gel.',120,80.00,0,'star',1,64);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('esmaltacao-maos','Esmaltação - Mãos','Mãos e Pés','Passar ou trocar o esmalte das unhas das mãos.',20,35.00,0,'star',1,25);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('esmaltacao-pes','Esmaltação - Pés','Mãos e Pés','Passar ou trocar o esmalte das unhas dos pés.',20,35.00,0,'star',1,26);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('fibra-colocacao','Unha de Fibra de Vidro - Colocação','Unhas Artificiais','Colocação de unha de fibra de vidro.',120,150.00,1,'star',1,61);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('fibra-manutencao','Unha de Fibra de Vidro - Manutenção','Unhas Artificiais','Manutenção de unhas de fibra de vidro.',120,100.00,0,'star',1,62);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('hidratacao','Hidratação','Cabelo','Hidratação capilar.',30,100.00,1,'droplet',1,12);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('lavagem-especial','Lavagem Especial','Cabelo','Lavagem específica para cada tipo de cabelo.',15,50.00,1,'droplet',1,13);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('lavagem-simples','Lavagem Simples','Cabelo','Lavagem simples.',15,30.00,1,'droplet',1,14);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('lavagem-terapeutica','Lavagem Terapêutica','Cabelo','Lavagem terapêutica.',30,50.00,0,'droplet',1,15);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('limpeza-de-cor','Limpeza de Cor','Cabelo','Remoção de coloração.',30,150.00,1,'palette',1,16);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('luzes','Luzes','Cabelo','Mechas bem fininhas, técnica similar à do reflexo.',120,450.00,1,'sparkles',1,17);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('manicure','Manicure','Mãos e Pés','Cutilação e esmaltação das unhas das mãos.',60,45.00,0,'star',1,27);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('manicure-francesinha','Manicure - Francesinha','Mãos e Pés','Cutilação e esmaltação no estilo francesinha.',60,35.00,1,'star',1,28);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('manicure-masculina','Manicure Masculina','Mãos e Pés','Cortar, lixar, hidratar e cutilar as mãos.',60,30.00,1,'star',1,32);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('manut-alongamento-gel','Manutenção de Alongamento em Gel','Mãos e Pés','Manutenção de alongamento em gel.',120,100.00,1,'star',1,33);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('manut-banho-gel','Manutenção do Banho de Gel','Unhas Artificiais','Refazemos o processo de blindagem.',90,90.00,0,'star',1,58);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('mao-pe','Manicure e Pedicure','Mãos e Pés','Cutilação e esmaltação das mãos e dos pés.',120,85.00,0,'star',1,29);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('mao-pe-francesinha','Manicure e Pedicure - Francesinha','Mãos e Pés','Mãos e pés no estilo francesinha.',90,70.00,1,'star',1,30);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('mao-pe-masculina','Manicure e Pedicure Masculina','Mãos e Pés','Cortar, lixar, hidratar e cutilar mãos e pés.',90,60.00,1,'star',1,31);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('maquiagem','Maquiagem sem Cílios','Maquiagem','Maquiagem completa.',60,175.00,0,'sparkles',1,72);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('maquiagem-com-cilios','Maquiagem com Cílios','Maquiagem','Maquiagem com cílios postiços.',30,195.00,0,'sparkles',1,71);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('mega-hair','Colocação/Manutenção Mega Hair','Cabelo','Colocação ou manutenção de mega hair.',180,75.00,1,'sparkles',1,4);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('molde-f1','Molde F1','Unhas Artificiais','Molde nas unhas.',90,160.00,1,'star',1,59);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('morena-iluminada','Morena Iluminada','Cabelo','Iluminado para morenas.',300,450.00,1,'sparkles',1,18);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('pedicure','Pedicure','Mãos e Pés','Cutilação e esmaltação das unhas dos pés.',60,50.00,0,'star',1,35);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('pedicure-francesinha','Pedicure - Francesinha','Mãos e Pés','Pés no estilo francesinha.',60,40.00,1,'star',1,36);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('pedicure-masculina','Pedicure Masculina','Mãos e Pés','Cortar, lixar, hidratar e cutilar os pés.',60,35.00,1,'star',1,37);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('penteado','Penteado','Penteados','Penteado para festas e eventos.',60,120.00,1,'crown',1,70);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('progressiva','Escova Progressiva sem Formol','Cabelo','Alisamento progressivo sem formol.',225,220.00,1,'wind',1,10);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('promo-mao-pe','Mão e Pé — Promoção Terça e Quarta','Mãos e Pés','Promoção válida às terças e quartas.',120,65.00,0,'star',1,34);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('reconstrucao','Reconstrução Capilar','Cabelo','Reconstrução capilar.',60,170.00,1,'droplet',1,19);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('remocao-mega','Remoção do Mega','Cabelo','Retirada do mega hair.',30,40.00,0,'scissors',1,20);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('remocao-unhas','Remoção','Unhas Artificiais','Remoção de unhas artificiais.',60,50.00,0,'star',1,60);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('secagem','Secagem','Cabelo','Somente secar — chegue com os cabelos lavados.',20,15.00,1,'wind',1,21);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('selante','Selante','Cabelo','Selagem capilar.',100,200.00,1,'droplet',1,22);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('shoft','Shoft','Unhas em Gel','Unhas em gel.',120,120.00,0,'star',1,65);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('sobrancelha-henna','Pintura Sobrancelhas - Henna','Sobrancelha','Pintura de sobrancelhas com henna.',30,30.00,1,'pencil',1,68);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('spa-dos-pes','Spa dos Pés','Mãos e Pés','Cuidado completo para os pés.',120,125.00,0,'droplet',1,38);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('terapia-capilar','Terapia Capilar','Cabelo','Terapia capilar.',120,200.00,0,'droplet',1,23);
INSERT IGNORE INTO `services` (`id`, `name`, `category`, `description`, `duration_min`, `price`, `price_from`, `icon`, `active`, `sort_order`) VALUES ('vapor-ozonio','Vapor de Ozônio','Cabelo','Vapor de ozônio.',30,30.00,0,'droplet',1,24);

SET FOREIGN_KEY_CHECKS = 1;

-- Depois de importar, crie a administradora abrindo
--   https://seusite.com.br/setup/instalar.php?chave=SUA_CHAVE
-- (preencha SETUP_TOKEN em api/config.php antes, e esvazie depois).
