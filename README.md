# Aline Dan · Espaço Lounge

Site e sistema de agendamento do salão **Aline Dan Espaço Lounge**, em Araçatuba/SP.

A cliente escolhe o serviço, a profissional, o dia e o horário; cria a conta; e vê,
remarca ou cancela os agendamentos dela. A Aline tem um painel com a agenda do dia
e da semana, agendamento de balcão, bloqueio de horários, edição do catálogo de
serviços e um relatório de faturamento.

## Como funciona a agenda

O salão abre de **terça a sábado, das 08h às 18h**, e os horários de início vão de
**5 em 5 minutos** — 120 por dia. Cada agendamento ocupa o intervalo
`[início, início + duração do serviço)`, e os serviços vão de 5 a 300 minutos.

Um horário só aparece livre quando, em **todos** os minutos desse intervalo:

- a profissional escolhida não tem outro atendimento nem bloqueio;
- o salão ainda tem capacidade (10 profissionais menos as bloqueadas); e
- o serviço termina antes das 18h.

Por isso um Mega Hair de 3 horas some da lista depois das 15:00, enquanto um
serviço de 5 minutos ainda pode começar às 17:55. A verificação roda de novo no
servidor, dentro de uma transação com as linhas travadas, para duas clientes não
pegarem o mesmo horário ao mesmo tempo.

A cliente cancela e remarca sozinha **até 4 horas antes**; passado o prazo, só
falando com o salão. A administração não tem esse limite.

Cancelar não apaga o agendamento: ele fica com `status = cancelado`, a data e
quem desmarcou (cliente ou salão). O horário volta a ficar livre na mesma hora,
mas o registro continua — dá para ver quem desmarca sempre em cima da hora. Os
cancelamentos do dia aparecem numa faixa própria no painel, abaixo da grade.

Quem simplesmente não aparece é outra coisa: no painel, o cartão de um horário
que já começou ganha o botão **Faltou** (`status = falta`, com **Desfazer falta**
para corrigir um clique errado). A falta continua visível na grade, apagada e com
etiqueta, mas sai do faturamento e da contagem de atendimentos do dia.

## Rodando na sua máquina

Precisa do **XAMPP** (Apache + MariaDB + PHP 8).

1. Copie a pasta do projeto para `C:\xampp\htdocs\aline-dan`.
2. Crie um banco vazio e importe **`setup/banco-completo.sql`**. Ele traz as
   quatro tabelas com todas as migrations já aplicadas **e os 73 serviços do
   catálogo** — um arquivo só. Os `schema.sql` e `migrate-*.sql` continuam na
   pasta como histórico; não precisa importá-los.
3. Copie `api/config.example.php` para `api/config.php` e ajuste o que precisar.
   No XAMPP padrão (root sem senha) já funciona como está.
4. Rode o instalador — ele confere tudo e popula o catálogo se estiver vazio:

   ```
   php setup/instalar.php
   ```

5. Abra `http://localhost/aline-dan/`.

**Na hospedagem é o mesmo caminho:** crie o banco pelo painel, importe o
`banco-completo.sql` pelo phpMyAdmin e preencha o `config.php`. Só a conta da
administradora fica de fora do arquivo, porque a senha tem que ser escolhida
por você e não pode viajar num arquivo versionado — para isso serve o
instalador do próximo tópico.

Importar duas vezes não faz mal: as tabelas usam `IF NOT EXISTS` e os serviços
usam `INSERT IGNORE`, então nada é apagado nem duplicado.

O `migrate-v3.sql` antigo apagava a tabela de serviços antes de inserir. Uma
importação interrompida no meio apagava e não inseria, e o site subia com o
catálogo vazio: a API respondia `200` com uma lista vazia, a página carregava
inteira e não mostrava nada — com cara de PHP quebrado, sem ser. O
`banco-completo.sql` não tem `DELETE` nenhum.

O instalador também cria a administradora (o cadastro público só faz cliente):

```
php setup/instalar.php --admin=aline@exemplo.com
```

Ele pede a senha na hora, para ela não ficar no histórico do terminal.

### Hospedagem sem terminal

O mesmo instalador roda pelo navegador. Como ele cria conta de administradora,
vem **desligado**: só responde se `SETUP_TOKEN` estiver preenchido em
`api/config.php`.

1. Pelo gerenciador de arquivos da hospedagem, abra `api/config.php` e escreva
   uma frase secreta em `SETUP_TOKEN`.
2. Abra `https://seusite.com.br/setup/instalar.php?chave=SUA_FRASE`. A página
   confere o banco, popula o catálogo se estiver vazio e traz um formulário
   para criar a administradora.
3. **Volte ao `config.php` e esvazie o `SETUP_TOKEN`.** Enquanto ele tiver
   valor, quem souber o endereço e a chave troca a senha da administradora.

O `setup/.htaccess` fecha o resto da pasta: os `.sql` e o cron não são
baixáveis pela web, só o `instalar.php` responde.

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
| `index.html`, `app.js` | Página principal: vitrine, equipe e chamada para o agendamento |
| `servicos.html`, `servicos.js` | Catálogo completo, com busca e filtro por categoria |
| `agendar.html` | Página do agendamento em 4 passos (usa o mesmo `app.js`) |
| `minha-conta.html`, `conta.js` | Área da cliente: agendamentos, remarcar, dados, senha |
| `admin.html`, `admin.js` | Painel da Aline: agenda, balcão, bloqueios, serviços, relatório |
| `login.html`, `login.js` | Entrar e criar conta |
| `common.js` | Código compartilhado: catálogo, chamadas de API, grade de horários |
| `styles.css` | Design system e todas as telas |
| `api/` | Back-end em PHP (PDO, sessões, senhas com bcrypt) |
| `api/data.php` | Regras da agenda: horários, durações e motor de disponibilidade |
| `setup/` | Schema do banco e o cron de lembretes |

## Ainda falta

- **Nove fotos de categoria** para a vitrine da home (recorte 4:5, ~1000x1250):
  cabelo, mãos e pés, cílios, unhas artificiais, unhas em gel, sobrancelha,
  penteados, maquiagem e depilação. Ponha os arquivos em `img/servicos/` e
  aponte o caminho em `CATEGORY_INFO` (`common.js`) — o bloco troca sozinho o
  painel decorativo pela foto.
- **Nove retratos das profissionais** (recorte 4:5). A seção Equipe é uma faixa
  escura com carrossel, no estilo do Meche Salon: foto colorida e o nome embaixo.
  Aline já usa foto; as outras mostram as iniciais na mesma moldura, então nada
  parece quebrado. Para ligar, acrescente `photo: img/equipe/nome.jpg` na lista
  `PROFESSIONALS` (`common.js`).
- Substituir os 5 depoimentos de demonstração, marcados com `data-demo="1"` no
  `index.html`, por avaliações verdadeiras antes de publicar.
