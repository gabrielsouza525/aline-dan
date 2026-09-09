-- Aline Dan · Migração v3 — dados reais do salão (fonte: trinks.com/espaco-lounge-aline-dan)
-- Catálogo completo com categorias e preços "a partir de".
-- ATENÇÃO: substitui o catálogo e limpa agendamentos/bloqueios de teste.

USE aline_dan;

ALTER TABLE services
  ADD COLUMN category   VARCHAR(40) NOT NULL DEFAULT 'Outros' AFTER name,
  ADD COLUMN price_from TINYINT(1)  NOT NULL DEFAULT 0 AFTER price;

DELETE FROM bookings;
DELETE FROM schedule_blocks;
DELETE FROM services;

INSERT INTO services (id, name, category, description, duration_min, price, price_from, icon, sort_order) VALUES
-- Cabelo
('aplicacao-coloracao',      'Aplicação de Coloração',              'Cabelo', 'Aplicação de coloração com produto levado pela cliente.',        60,  100.00, 1, 'palette',  1),
('botox-capilar',            'Botox Capilar',                       'Cabelo', 'Botox capilar, selante e acabamento.',                           120, 250.00, 1, 'droplet',  2),
('botox-produto-aline',      'Botox com Produto da Aline',          'Cabelo', 'Botox com produto do salão.',                                    90,  200.00, 1, 'droplet',  3),
('mega-hair',                'Colocação/Manutenção Mega Hair',      'Cabelo', 'Colocação ou manutenção de mega hair.',                          180, 75.00,  1, 'sparkles', 4),
('coloracao',                'Coloração / Tonalização',             'Cabelo', 'Tintura dos cabelos com produtos do salão.',                     60,  140.00, 1, 'palette',  5),
('corte-feminino',           'Corte Feminino',                      'Cabelo', 'Higienização, corte e secagem.',                                 40,  150.00, 1, 'scissors', 6),
('corte-infantil',           'Corte Infantil',                      'Cabelo', 'Corte infantil.',                                                30,  50.00,  1, 'scissors', 7),
('corte-masculino',          'Corte Masculino',                     'Cabelo', 'Higienização, corte e secagem.',                                 30,  80.00,  1, 'scissors', 8),
('escova-prancha',           'Escova e Prancha',                    'Cabelo', 'Lavagem, escova e prancha.',                                     60,  70.00,  1, 'wind',     9),
('progressiva',              'Escova Progressiva sem Formol',       'Cabelo', 'Alisamento progressivo sem formol.',                             225, 220.00, 1, 'wind',     10),
('escova-simples',           'Escova Simples',                      'Cabelo', 'Lavagem e escova.',                                              30,  60.00,  1, 'wind',     11),
('hidratacao',               'Hidratação',                          'Cabelo', 'Hidratação capilar.',                                            30,  100.00, 1, 'droplet',  12),
('lavagem-especial',         'Lavagem Especial',                    'Cabelo', 'Lavagem específica para cada tipo de cabelo.',                   15,  50.00,  1, 'droplet',  13),
('lavagem-simples',          'Lavagem Simples',                     'Cabelo', 'Lavagem simples.',                                               15,  30.00,  1, 'droplet',  14),
('lavagem-terapeutica',      'Lavagem Terapêutica',                 'Cabelo', 'Lavagem terapêutica.',                                           30,  50.00,  0, 'droplet',  15),
('limpeza-de-cor',           'Limpeza de Cor',                      'Cabelo', 'Remoção de coloração.',                                          30,  150.00, 1, 'palette',  16),
('luzes',                    'Luzes',                               'Cabelo', 'Mechas bem fininhas, técnica similar à do reflexo.',             120, 450.00, 1, 'sparkles', 17),
('morena-iluminada',         'Morena Iluminada',                    'Cabelo', 'Iluminado para morenas.',                                        300, 450.00, 1, 'sparkles', 18),
('reconstrucao',             'Reconstrução Capilar',                'Cabelo', 'Reconstrução capilar.',                                          60,  170.00, 1, 'droplet',  19),
('remocao-mega',             'Remoção do Mega',                     'Cabelo', 'Retirada do mega hair.',                                         30,  40.00,  0, 'scissors', 20),
('secagem',                  'Secagem',                             'Cabelo', 'Somente secar — chegue com os cabelos lavados.',                 20,  15.00,  1, 'wind',     21),
('selante',                  'Selante',                             'Cabelo', 'Selagem capilar.',                                               100, 200.00, 1, 'droplet',  22),
('terapia-capilar',          'Terapia Capilar',                     'Cabelo', 'Terapia capilar.',                                               120, 200.00, 0, 'droplet',  23),
('vapor-ozonio',             'Vapor de Ozônio',                     'Cabelo', 'Vapor de ozônio.',                                               30,  30.00,  0, 'droplet',  24),
-- Mãos e Pés
('esmaltacao-maos',          'Esmaltação - Mãos',                   'Mãos e Pés', 'Passar ou trocar o esmalte das unhas das mãos.',             20,  35.00,  0, 'star',     25),
('esmaltacao-pes',           'Esmaltação - Pés',                    'Mãos e Pés', 'Passar ou trocar o esmalte das unhas dos pés.',              20,  35.00,  0, 'star',     26),
('manicure',                 'Manicure',                            'Mãos e Pés', 'Cutilação e esmaltação das unhas das mãos.',                 60,  45.00,  0, 'star',     27),
('manicure-francesinha',     'Manicure - Francesinha',              'Mãos e Pés', 'Cutilação e esmaltação no estilo francesinha.',              60,  35.00,  1, 'star',     28),
('mao-pe',                   'Manicure e Pedicure',                 'Mãos e Pés', 'Cutilação e esmaltação das mãos e dos pés.',                 120, 85.00,  0, 'star',     29),
('mao-pe-francesinha',       'Manicure e Pedicure - Francesinha',   'Mãos e Pés', 'Mãos e pés no estilo francesinha.',                          90,  70.00,  1, 'star',     30),
('mao-pe-masculina',         'Manicure e Pedicure Masculina',       'Mãos e Pés', 'Cortar, lixar, hidratar e cutilar mãos e pés.',              90,  60.00,  1, 'star',     31),
('manicure-masculina',       'Manicure Masculina',                  'Mãos e Pés', 'Cortar, lixar, hidratar e cutilar as mãos.',                 60,  30.00,  1, 'star',     32),
('manut-alongamento-gel',    'Manutenção de Alongamento em Gel',    'Mãos e Pés', 'Manutenção de alongamento em gel.',                          120, 100.00, 1, 'star',     33),
('promo-mao-pe',             'Mão e Pé — Promoção Terça e Quarta',  'Mãos e Pés', 'Promoção válida às terças e quartas.',                       120, 65.00,  0, 'star',     34),
('pedicure',                 'Pedicure',                            'Mãos e Pés', 'Cutilação e esmaltação das unhas dos pés.',                  60,  50.00,  0, 'star',     35),
('pedicure-francesinha',     'Pedicure - Francesinha',              'Mãos e Pés', 'Pés no estilo francesinha.',                                 60,  40.00,  1, 'star',     36),
('pedicure-masculina',       'Pedicure Masculina',                  'Mãos e Pés', 'Cortar, lixar, hidratar e cutilar os pés.',                  60,  35.00,  1, 'star',     37),
('spa-dos-pes',              'Spa dos Pés',                         'Mãos e Pés', 'Cuidado completo para os pés.',                              120, 125.00, 0, 'droplet',  38),
('escalda-pes',              'Escalda Pés',                         'Mãos e Pés', 'Escalda pés relaxante.',                                     30,  50.00,  0, 'droplet',  39),
-- Cílios
('cilios-brasileiro-87',     'Brasileiro - 87',                     'Cílios', 'Manutenção.',                                                    60,  87.00,  0, 'eye',      40),
('cilios-cliente-aline',     'Cliente Aline',                       'Cílios', 'Cílios — condição especial para clientes da Aline.',             130, 150.00, 0, 'eye',      41),
('cilios-manut-4d',          'Manutenção 4D',                       'Cílios', 'Manutenção do volume 4D.',                                       100, 110.00, 0, 'eye',      42),
('cilios-manut-4d-70',       'Manutenção 4D - 70',                  'Cílios', 'Manutenção do volume 4D.',                                       60,  70.00,  0, 'eye',      43),
('cilios-manut-fox-aline',   'Manutenção Efeito Fox Cliente Aline', 'Cílios', 'Retorno em até 23 dias.',                                        120, 130.00, 0, 'eye',      44),
('cilios-manut-express-100', 'Manutenção Express',                  'Cílios', 'Retorno em até 15 dias.',                                        100, 90.00,  0, 'eye',      45),
('cilios-manut-fox',         'Manutenção Fox',                      'Cílios', 'Retorno em até 23 dias.',                                        100, 135.00, 0, 'eye',      46),
('cilios-vol-bras-aline',    'Volume Brasileiro Cliente Aline',     'Cílios', 'Condição especial para clientes da Aline.',                      120, 140.00, 0, 'eye',      47),
('cilios-vol-expr-aline',    'Volume Express Cliente Aline',        'Cílios', 'Condição especial para clientes da Aline.',                      120, 125.00, 0, 'eye',      48),
('cilios-efeito-4d',         'Efeito 4D',                           'Cílios', 'Extensão de cílios efeito 4D.',                                  150, 150.00, 0, 'eye',      49),
('cilios-manut-classico',    'Manutenção (Clássico e Brasileiro)',  'Cílios', 'Manutenção dos volumes clássico e brasileiro.',                  90,  90.00,  0, 'eye',      50),
('cilios-manut-express-90',  'Manutenção (Express)',                'Cílios', 'Retorno em até 15 dias.',                                        90,  90.00,  0, 'eye',      51),
('cilios-manut-brasileiro',  'Manutenção Brasileiro',               'Cílios', 'Manutenção do volume brasileiro.',                               90,  95.00,  0, 'eye',      52),
('cilios-remocao',           'Remoção dos Cílios',                  'Cílios', 'Remoção da extensão de cílios.',                                 30,  30.00,  0, 'eye',      53),
('cilios-volume-brasileiro', 'Volume (Brasileiro)',                 'Cílios', 'Extensão de cílios volume brasileiro.',                          150, 140.00, 0, 'eye',      54),
('cilios-volume-express',    'Volume Express',                      'Cílios', 'Extensão de cílios volume express.',                             150, 125.00, 0, 'eye',      55),
-- Unhas Artificiais
('blindagem-gel',            'Blindagem de Gel',                    'Unhas Artificiais', 'Unhas naturais, flexíveis e fortes — indolor e sem odor.', 120, 90.00, 1, 'star',   56),
('dedo-de-fibra',            'Dedo de Fibra',                       'Unhas Artificiais', 'Apenas um dedo.',                                     30,  20.00,  0, 'star',     57),
('manut-banho-gel',          'Manutenção do Banho de Gel',          'Unhas Artificiais', 'Refazemos o processo de blindagem.',                  90,  90.00,  0, 'star',     58),
('molde-f1',                 'Molde F1',                            'Unhas Artificiais', 'Molde nas unhas.',                                    90,  160.00, 1, 'star',     59),
('remocao-unhas',            'Remoção',                             'Unhas Artificiais', 'Remoção de unhas artificiais.',                       60,  50.00,  0, 'star',     60),
('fibra-colocacao',          'Unha de Fibra de Vidro - Colocação',  'Unhas Artificiais', 'Colocação de unha de fibra de vidro.',                120, 150.00, 1, 'star',     61),
('fibra-manutencao',         'Unha de Fibra de Vidro - Manutenção', 'Unhas Artificiais', 'Manutenção de unhas de fibra de vidro.',              120, 100.00, 0, 'star',     62),
-- Unhas em Gel
('banho-gel',                'Banho em Gel',                        'Unhas em Gel', 'Banho em gel.',                                            60,  100.00, 0, 'star',     63),
('esmaltacao-gel',           'Esmaltação em Gel',                   'Unhas em Gel', 'Esmaltação em gel.',                                       120, 80.00,  0, 'star',     64),
('shoft',                    'Shoft',                               'Unhas em Gel', 'Unhas em gel.',                                            120, 120.00, 0, 'star',     65),
-- Sobrancelha
('design-sobrancelha',       'Design de Sobrancelha',               'Sobrancelha', 'Design de sobrancelha.',                                    30,  30.00,  0, 'pencil',   66),
('design-sobrancelha-tp',    'Design de Sobrancelha T.P',           'Sobrancelha', 'Tirar e pintar.',                                           40,  40.00,  0, 'pencil',   67),
('sobrancelha-henna',        'Pintura Sobrancelhas - Henna',        'Sobrancelha', 'Pintura de sobrancelhas com henna.',                        30,  30.00,  1, 'pencil',   68),
-- Penteados
('babyliss',                 'Baby Liss / Cachos',                  'Penteados', 'Cachos manuais ou com babyliss.',                             60,  80.00,  1, 'crown',    69),
('penteado',                 'Penteado',                            'Penteados', 'Penteado para festas e eventos.',                             60,  120.00, 1, 'crown',    70),
-- Maquiagem
('maquiagem-com-cilios',     'Maquiagem com Cílios',                'Maquiagem', 'Maquiagem com cílios postiços.',                              30,  195.00, 0, 'sparkles', 71),
('maquiagem',                'Maquiagem sem Cílios',                'Maquiagem', 'Maquiagem completa.',                                         60,  175.00, 0, 'sparkles', 72),
-- Depilação
('buco-cera',                'Depilação de Buço na Cera',           'Depilação', 'Depilação do buço com cera.',                                 20,  25.00,  1, 'wind',     73);
