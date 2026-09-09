# Aline Dan · Espaço Lounge

Site e sistema de agendamento do salão **Aline Dan Espaço Lounge**, em Araçatuba/SP.

A cliente escolhe o serviço, a profissional, o dia e o horário; cria a conta; e vê,
remarca ou cancela os agendamentos dela. A Aline tem um painel com a agenda do dia
e da semana, agendamento de balcão, bloqueio de horários, edição do catálogo de
serviços e um relatório de faturamento.

## Como funciona a agenda

O salão abre de **terça a sábado, das 08h às 18h**, e os horários de início vão de
**10 em 10 minutos** — 60 por dia. Cada agendamento ocupa o intervalo
`[início, início + duração do serviço)`, e os serviços vão de 10 a 300 minutos.

Um horário só aparece livre quando, em **todos** os minutos desse intervalo:

- a profissional escolhida não tem outro atendimento nem bloqueio;
- o salão ainda tem capacidade (10 profissionais menos as bloqueadas); e
- o serviço termina antes das 18h.

Por isso um Mega Hair de 3 horas some da lista depois das 15:00, enquanto um
serviço de 10 minutos ainda pode começar às 17:50. A verificação roda de novo no
servidor, dentro de uma transação com as linhas travadas, para duas clientes não
pegarem o mesmo horário ao mesmo tempo.

## Rodando na sua máquina

Precisa do **XAMPP** (Apache + MariaDB + PHP 8).

1. Copie a pasta do projeto para `C:\xampp\htdocs\aline-dan`.
2. Crie o banco e as tabelas importando, nesta ordem, os arquivos de `setup/`:
   `schema.sql`, `migrate-v2.sql`, `migrate-v3.sql`.
3. Copie `api/config.example.php` para `api/config.php` e ajuste o que precisar.
   No XAMPP padrão (root sem senha) já funciona como está.
4. Abra `http://localhost/aline-dan/`.

Os e-mails (confirmação de cadastro, aviso de agendamento, lembrete, redefinição
de senha) saem em modo `file` por padrão: em vez de enviar, ficam salvos em
`storage/outbox` para você conferir. Para enviar de verdade, preencha os dados de
SMTP no `config.php` e troque `MAIL_MODE` para `'smtp'`.

Antes de depender disso, teste as credenciais pela linha de comando:

```
php setup/testar_email.php seu@email.com
```

Ele mostra a configuração em uso e, quando falha, a resposta exata do servidor —
que é o que diz se o problema foi a senha, a porta ou o remetente. Todo envio fica
registrado em `storage/mail.log`.

O fuso do salão está fixado em `America/Sao_Paulo` no `config.php`, e a conexão
com o MySQL é alinhada a ele. Sem isso o servidor usa o próprio fuso (quase sempre
UTC numa hospedagem) e a agenda inteira sai errada.

## Organização

| Pasta / arquivo | O que é |
|---|---|
| `index.html`, `app.js` | Página principal e o fluxo de agendamento em 4 passos |
| `minha-conta.html`, `conta.js` | Área da cliente: agendamentos, remarcar, dados, senha |
| `admin.html`, `admin.js` | Painel da Aline: agenda, balcão, bloqueios, serviços, relatório |
| `login.html`, `login.js` | Entrar e criar conta |
| `common.js` | Código compartilhado: catálogo, chamadas de API, grade de horários |
| `styles.css` | Design system e todas as telas |
| `api/` | Back-end em PHP (PDO, sessões, senhas com bcrypt) |
| `api/data.php` | Regras da agenda: horários, durações e motor de disponibilidade |
| `setup/` | Schema do banco e o cron de lembretes |

## Ainda falta

- Trocar os SVGs de espaço reservado pelas fotos reais (hero, salão e as 10 profissionais).
- Substituir os 5 depoimentos de demonstração, marcados com `data-demo="1"` no
  `index.html`, por avaliações verdadeiras antes de publicar.
