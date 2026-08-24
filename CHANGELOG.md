# Histórico de versões

## 1.2.0 — 24/08/2026

Descadastro público.

- Novo componente `descadastro-publico/`: página autônoma de cancelamento, para
  hospedar em domínio alcançável pela internet. Sem sessão, sem login, sem
  acesso a campanhas ou operadores — valida o token assinado e marca o contato.
- Atende o One-Click da RFC 8058, o botão "cancelar inscrição" do Gmail e do
  Outlook.
- Usuário de banco próprio e restrito por coluna: lê quatro campos de
  `contatos`, escreve em dois, e só acrescenta em `auditoria`.
- Nova opção `app.url_descadastro`, separada de `app.url_base`: o painel segue
  na rede interna e apenas o link do rodapé aponta para fora.

## 1.1.0 — 24/08/2026

Teto diário de envio.

- Limite de mensagens nas últimas 24 horas, em janela rolante: cada mensagem
  deixa de contar 24h depois de sair, evitando o pico de meia-noite. Ao atingir
  o teto a fila pausa e retoma sozinha, sem perder nada.
- Ajustável pela interface (padrão 1000); `0` desliga o limite.
- Painel, Ajustes e a tela do envio mostram o consumo da janela, quanto ainda
  cabe e o horário da próxima vaga — a fila nunca parece travada sem explicação.
- Índice `idx_fila_janela` para a contagem, criado automaticamente pelo deploy
  em bases já existentes.
- A sessão do banco passa a usar o fuso da aplicação. Com o servidor em UTC,
  todas as datas exibidas ao operador saíam adiantadas.

## 1.0.1 — 24/08/2026

Deploy a partir do GitHub.

- `deploy/deploy.sh`: busca a referência pedida, confere a sintaxe de todo o
  código antes de publicar, mostra as diferenças, toma a trava do worker,
  guarda backup e permite reverter.
- `--simular` mostra o que mudaria sem escrever nada; `--voltar` restaura o
  backup mais recente; `--listar` mostra as versões guardadas.
- `instalar.php --tabelas` confere a estrutura do banco sem interação, para o
  deploy criar tabelas que uma versão nova traga.
- O script vive fora do diretório publicado, para não ser sobrescrito durante a
  própria execução.

## 1.0.0 — 24/08/2026

Primeira versão.

- Cadastro de contatos com bairro, importação de CSV com normalização de bairro
  e reconhecimento de duplicidade por e-mail.
- Envio para um contato, para um bairro ou para toda a lista.
- Modelos de mensagem com variáveis substituídas por destinatário.
- Fila persistente com worker por cron, conexão SMTP reaproveitada, cadência
  configurável, pausa global e retentativa com desistência.
- Acompanhamento do envio em tempo real, com reenvio seletivo das falhas.
- Descadastro assinado por HMAC, no rodapé e via `List-Unsubscribe` (RFC 8058).
- Supressão de contatos descadastrados no momento do envio, mesmo que a
  campanha já estivesse liberada.
- Trilha de auditoria e controle de acesso por operador e administrador.
