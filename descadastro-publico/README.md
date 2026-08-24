# Endpoint público de descadastro

Página de cancelamento de recebimento, para hospedar em um domínio **alcançável
pela internet**. O painel da aplicação fica na rede interna; esta página não
pode, porque quem recebe o comunicado abre o e-mail em casa, no celular.

Sem ela, o cidadão que quer parar de receber só tem uma saída: o botão de
denunciar spam. É esse clique que degrada a reputação do domínio e passa a
derrubar a entrega de tudo que sai da Prefeitura — inclusive o correio comum
dos servidores.

## O que esta página faz

Uma coisa só: marcar um contato como descadastrado, mediante um link assinado.

Não tem sessão, não tem login, não tem formulário de busca. Não lê campanhas,
modelos nem operadores. Quem chegar sem um token válido vê uma página de erro e
nada mais acontece.

Atende também o **One-Click** da RFC 8058 — o botão "cancelar inscrição" que o
Gmail e o Outlook mostram ao lado do remetente. Nesse caso a requisição chega
como POST, a página confirma direto e responde só o código de status, sem
apresentar tela.

## Instalação

### 1. Escolher o domínio

Qualquer um já público. Se o subdomínio de envio for criado, ele é a escolha
mais coerente — o cidadão vê o mesmo nome no remetente e no link.

O endereço precisa ficar **estável**: links já enviados param de funcionar se
ele mudar.

### 2. Publicar os arquivos

```bash
DOM=comunica.santahelena.pr.gov.br
cd /home/USUARIO/web/$DOM/public_html

# descadastro.php, config.exemplo.php e assets/
tar xzf /tmp/descadastro-publico.tar.gz --strip-components=1

cp config.exemplo.php config.php
chmod 600 config.php
```

### 3. Usuário de banco restrito

**Não reaproveite o usuário da aplicação.** Esta página roda em um servidor
exposto; se um dia for comprometida, o estrago precisa parar em "marcou contatos
como descadastrados".

No MariaDB (`192.168.0.91`), como root:

```sql
CREATE USER 'sendmail_optout'@'IP_DO_SERVIDOR_PUBLICO' IDENTIFIED BY 'SENHA_FORTE';

GRANT SELECT (id, nome, email, opt_out) ON sendmail.contatos TO 'sendmail_optout'@'IP_DO_SERVIDOR_PUBLICO';
GRANT UPDATE (opt_out, opt_out_em)      ON sendmail.contatos TO 'sendmail_optout'@'IP_DO_SERVIDOR_PUBLICO';
GRANT INSERT                            ON sendmail.auditoria TO 'sendmail_optout'@'IP_DO_SERVIDOR_PUBLICO';

FLUSH PRIVILEGES;
```

Repare no escopo por coluna: esse usuário lê quatro campos de uma tabela e
escreve em dois. Não alcança telefone, documento, campanhas nem a tabela de
operadores.

Se o servidor público for o mesmo Hestia da aplicação, use o IP dele. Se for
outro, libere o acesso ao MariaDB só para esse IP.

### 4. Configurar

```bash
nano config.php
```

O ponto crítico é a `chave`: precisa ser **idêntica** ao `app.chave` do
`private/config.php` da aplicação. É ela que valida a assinatura dos links. Se
divergirem, todo cancelamento passa a ser recusado como link inválido — e o
sintoma aparece só quando um cidadão tenta usar.

```bash
# na aplicação:
grep "'chave'" /home/informatica/web/sendmail.santahelena.pr.gov.br/private/config.php
```

### 5. Apontar a aplicação para cá

No `private/config.php` da aplicação:

```php
'url_descadastro' => 'https://comunica.santahelena.pr.gov.br',
```

É esse endereço que passa a sair no rodapé e no `List-Unsubscribe`.

### 6. Conferir antes do primeiro disparo

Cadastre um contato de teste no painel e crie um envio para ele. Na mensagem
recebida:

- Clique no link do rodapé, **de fora da rede da Prefeitura** — pelo celular
  com dados móveis, não pelo Wi-Fi. É o cenário real do cidadão.
- Confirme o cancelamento e veja se o contato aparece como descadastrado na
  listagem do painel.
- No Gmail, confira se o botão "cancelar inscrição" aparece ao lado do
  remetente. Ele é o `List-Unsubscribe` funcionando.

Depois, reative o contato de teste na listagem.

## Confirmado em teste

Com os GRANTs acima, o usuário deste endpoint consegue exatamente duas coisas:
ler nome, e-mail e situação de um contato, e marcá-lo como descadastrado.

Tudo o mais é recusado pelo próprio banco — campanhas, operadores, fila,
telefone e documento dos contatos, alteração de e-mail, exclusão de registros.
Nem a tabela de auditoria ele consegue ler; só acrescentar linhas.

## Manutenção

Esta página praticamente não muda. Quando mudar, é um arquivo: substitua o
`descadastro.php` e pronto — não há banco próprio nem estado a migrar.

O `config.php` guarda uma senha de banco e a chave de assinatura. Inclua-o no
backup, junto com o da aplicação.

## Se algo falhar

| Sintoma | Causa provável |
|---|---|
| Todo link dá "inválido" | a `chave` daqui difere do `app.chave` da aplicação |
| "Não foi possível concluir agora" | banco inacessível; veja o log de erro do PHP |
| Link não abre de fora | o domínio não resolve na internet, ou falta o registro A |
| Gmail não mostra "cancelar inscrição" | o `List-Unsubscribe` aponta para endereço que não resolve |
