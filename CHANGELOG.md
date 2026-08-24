# Histórico de versões

## 1.5.0 — 24/08/2026

Reaproveitamento de envios.

- Botão "Salvar como modelo" na tela do envio, em qualquer situação — de um
  rascunho a uma campanha concluída. O modelo nasce com assunto, corpo e uma
  cópia dos anexos do envio.
- Modelos aceitam anexos próprios, com as mesmas regras dos envios. Todo
  envio novo criado a partir de um modelo herda os anexos dele — cópia
  independente: mexer no modelo depois não altera envios já criados.
- Migração automática da tabela `anexos` no deploy (coluna `modelo_id`).

## 1.4.0 — 24/08/2026

Anexos.

- Os envios aceitam anexos: documentos (PDF, Office, ODF, CSV, TXT) e imagens,
  até 10 MB somados por campanha, adicionados no rascunho e enviados em todas
  as mensagens — inclusive no teste para si mesmo.
- Os arquivos ficam em `private/anexos/`, fora do alcance do navegador,
  preservados pelo deploy como config.php e logs. Excluir a campanha apaga os
  arquivos junto; adição e remoção ficam na auditoria.
- Nova tabela `anexos`, criada automaticamente pelo deploy em bases
  existentes.

## 1.3.4 — 24/08/2026

Servidor SMTP ajustável.

- O host do servidor de saída passa a ser editável em *Ajustes → Servidor de
  saída*, pré-definido como `zldapmta.santahelena.pr.gov.br` (o MTA do
  Zimbra). Vazio, vale o host do `config.php`. Porta, criptografia e
  credenciais continuam apenas no arquivo.
- A troca fica na auditoria (`smtp_alterado`) e o botão de teste de conexão
  usa o host em vigor.

## 1.3.3 — 24/08/2026

Correção.

- A importação converte arquivos ANSI (Windows-1252) para UTF-8 antes de ler.
  Exportações de sistemas Windows vinham nessa codificação: o cabeçalho
  "Pessoas - Nome Razão" não era reconhecido por causa do acento e todos os
  contatos entravam com o e-mail no lugar do nome; acentos nos dados também
  chegavam corrompidos ao banco. Reimportar o mesmo arquivo com "Atualizar"
  marcado corrige os nomes de quem já entrou.
- `config.exemplo.php`: o servidor SMTP correto é `zldapmta.santahelena.pr.gov.br`
  (MTA), não `zimbramailbox1` (mailbox).

## 1.3.2 — 24/08/2026

Correção.

- A importação de CSV grava em lotes dentro de uma transação, em vez de
  consultar o banco quatro vezes por linha. Com o banco em outra máquina,
  arquivos com milhares de linhas estouravam os 60 segundos do PHP e a
  importação parava pela metade (Gateway Timeout). Um arquivo de 20 mil
  linhas agora resolve em segundos.
- E-mail repetido dentro do próprio arquivo é fundido (vale a última linha),
  e a auditoria registra o resumo da importação, não mais um evento por
  contato criado.

## 1.3.1 — 24/08/2026

Importação.

- O importador de CSV aceita exportações de outros sistemas: cabeçalhos como
  "Pessoas - Nome Razão", "Pessoas - CPF/CNPJ" e "Bairro - Nome" são
  reconhecidos, e quando nenhuma coluna se chama "email" o endereço é
  localizado pelo próprio conteúdo do arquivo. Se mais de uma coluna se
  parecer com e-mail, a importação para com uma mensagem que explica o que
  fazer, em vez de adivinhar.

## 1.3.0 — 24/08/2026

Robustez.

- O worker recupera itens presos em "enviando". Se um ciclo morria no meio de
  um envio (queda de energia, processo morto), a linha ficava nesse status
  para sempre e a campanha nunca concluía. Agora, no início de cada ciclo,
  essas linhas voltam para a fila contando uma tentativa.
- Bloqueio temporário do login: dez falhas em quinze minutos, do mesmo IP ou
  contra o mesmo usuário, suspendem novas tentativas até a janela expirar. O
  operador bloqueado recebe aviso claro, e o evento fica na auditoria como
  "login_bloqueado".

## 1.2.3 — 24/08/2026

Correções no deploy.

- O `rsync --delete` da publicação não apaga mais os diretórios que o
  HestiaCP mantém na raiz do domínio (`logs/`, `stats/`, `document_errors/`,
  `cgi-bin/`, `public_shtml/`). Antes, um deploy os removia e o painel de
  hospedagem perdia logs e estatísticas do domínio.
- `--voltar` restaura somente `public_html` e `private` (o que o backup
  guarda), em vez de aplicar `--delete` na raiz do destino — o que apagava
  `sql/`, a documentação e os mesmos diretórios do painel.
- `--voltar` agora toma a trava do worker antes de trocar os arquivos, como a
  publicação já fazia.
- `.gitattributes` fora da publicação, como os demais arquivos de controle.

## 1.2.2 — 24/08/2026

Publicação no GitHub.

- O repositório agora vive em <https://github.com/sidneyalpagel/sendmail>,
  público. O deploy passa a buscar direto de lá por HTTPS, sem credencial —
  o bundle em `/tmp` deixa de ser necessário.
- `.gitattributes` garante fim de linha LF nos scripts shell: um checkout no
  Windows entregava o `deploy.sh` com CRLF, que quebra o bash no servidor.
- `deploy.conf.exemplo` atualizado com o endereço real do repositório.

## 1.2.1 — 24/08/2026

Correção.

- O instalador não roda mais `shell_exec` sem verificar se a função existe e se
  está habilitada. O HestiaCP a mantém em `disable_functions` por padrão, e o
  script morria com erro fatal ao pedir a senha do primeiro operador. Quando não
  é possível ocultar a digitação, avisa e segue com a senha visível.

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
