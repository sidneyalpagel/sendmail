# Disparador de e-mails

Aplicação web para envio de comunicados por e-mail da **Prefeitura Municipal de
Santa Helena / PR**. O operador escolhe o público — um contato, um bairro ou
toda a lista —, escreve a mensagem e libera o disparo. O envio acontece em
segundo plano, com fila, cadência controlada e registro de quem recebeu.

Domínio de produção: `https://sendmail.santahelena.pr.gov.br`

---

## O que ela faz

- **Cadastro de contatos** com bairro, telefone e documento, alimentado
  manualmente ou por importação de CSV.
- **Escolha do público** em três níveis: um destinatário específico, todos de um
  bairro, ou a lista inteira.
- **Modelos de mensagem** reutilizáveis, com variáveis (`{{primeiro_nome}}`,
  `{{bairro}}`, `{{data}}` e outras) substituídas no momento do envio.
- **Fila com worker próprio**: o disparo não depende do navegador aberto nem do
  tempo limite do PHP. A tela de acompanhamento mostra o andamento em tempo real.
- **Teto diário** em janela rolante de 24 horas, protegendo a reputação do
  domínio, mais **cadência por minuto** e pausa global, para não estourar a
  cota do servidor de e-mail.
- **Retentativa automática** de endereços que falharam por motivo temporário,
  com desistência após um número definido de tentativas.
- **Descadastro em um clique**, pelo rodapé da mensagem ou pelo próprio cliente
  de e-mail (cabeçalho `List-Unsubscribe`). Quem se descadastra fica fora de
  qualquer envio seguinte, inclusive dos que já estavam na fila.
- **Trilha de auditoria** de tudo que os operadores fazem.

## Como o envio funciona

1. O operador cria um **rascunho**: público, assunto e corpo. Nada sai ainda.
2. Pode ver a **prévia** renderizada e mandar um **teste para si mesmo**.
3. Ao **liberar**, o público é resolvido e **congelado** na tabela `fila`. Quem
   entrar na base depois não recebe aquela mensagem, e o relatório reflete
   exatamente quem estava na lista naquele instante.
4. O **worker**, chamado pelo cron a cada minuto, trabalha por 50 segundos
   mantendo uma única conexão SMTP autenticada aberta, respeitando o limite de
   mensagens por minuto.
5. Cada linha da fila termina em `enviado`, `falha` ou `suprimido`. Quando não
   sobra nada pendente, a campanha é marcada como concluída.

## Estrutura

```
public_html/          Único diretório exposto pelo servidor web
  index.php           Controlador: rotas e ações
  descadastro.php     Página pública de cancelamento
  assets/app.css

private/              Fora do alcance do navegador
  config.php          Credenciais (NÃO versionado — veja config.exemplo.php)
  app/
    bootstrap.php     Configuração, sessão, funções auxiliares
    Db.php            Camada PDO
    Auth.php          Autenticação dos operadores
    Auditoria.php     Trilha de atividades
    Contatos.php      Cadastro, bairros e importação de CSV
    Modelos.php       Modelos de mensagem
    Campanhas.php     Público, fila e controle de execução
    Mensagem.php      Variáveis, moldura HTML e versão em texto
    Correio.php       Transporte SMTP
    views/            Telas
  bin/
    instalar.php      Cria as tabelas e o primeiro operador
    worker.php        Processa a fila (cron)
  lib/                PHPMailer embutido
  logs/

sql/schema.sql        Estrutura do banco
```

## Requisitos

- PHP 8.1 ou superior, com `pdo_mysql`, `mbstring` e `openssl`
- MariaDB 10.6+ ou MySQL 8+
- Conta autenticada em servidor SMTP
- Acesso ao cron para o worker

Sem Composer: o PHPMailer 6.9.3 vai embutido em `private/lib`
(licença LGPL-2.1, preservada em `LICENSE-PHPMailer.txt`).

## Instalação

Veja **[INSTALL.md](INSTALL.md)** para o passo a passo no HestiaCP.

Resumo:

```bash
cp private/config.exemplo.php private/config.php
chmod 600 private/config.php
$EDITOR private/config.php          # banco, SMTP e chave da aplicação
php private/bin/instalar.php        # tabelas + primeiro operador
```

E o agendamento do worker:

```cron
* * * * * /usr/bin/php /caminho/private/bin/worker.php >> /caminho/private/logs/cron.log 2>&1
```

## Cuidados de operação

**Dois limites, propósitos diferentes.** O teto diário protege a reputação do
domínio junto aos provedores, que avaliam volume e taxa de reclamação. O limite
por minuto evita estourar a cota por remetente do servidor de e-mail num pico.
Os dois ficam em *Ajustes*.

**Aquecimento.** Se o domínio ainda não envia volume, começar com mil mensagens
no primeiro dia é um salto que os provedores notam. O usual é partir de 50 a 100
e subir ao longo de duas ou três semanas, acompanhando a taxa de rejeição.

**A conta remetente não é a conta de resposta.** O campo `Reply-To` aponta para
o endereço de atendimento, para que respostas cheguem a quem pode respondê-las.

**Descadastro é definitivo por padrão.** Uma importação de CSV não reativa quem
pediu para sair — a reativação é manual, na listagem de contatos.

**Congelamento do público.** Editar contatos depois de liberar um envio não
altera aquele envio, apenas os seguintes.

## Licença

Uso interno da Prefeitura Municipal de Santa Helena / PR.
O PHPMailer, incluído em `private/lib`, segue a licença LGPL-2.1 do projeto original.
