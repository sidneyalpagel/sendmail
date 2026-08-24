# Implantação no HestiaCP

Passo a passo para colocar a aplicação em
`sendmail.santahelena.pr.gov.br`. Os comandos assumem o usuário
`informatica` do Hestia — ajuste se for outro.

---

## 1. Criar o domínio no Hestia

No painel (`https://IP:8083`), em **WEB → Add Web Domain**:

- Domain: `sendmail.santahelena.pr.gov.br`
- Marque **SSL Support** e **Let's Encrypt** (ou aplique o certificado wildcard
  já existente, se preferir manter o mesmo dos demais domínios)

Confirme que a pasta foi criada:

```bash
ls -la /home/informatica/web/sendmail.santahelena.pr.gov.br/
```

Devem existir `public_html` e `private`. Se `private` não existir, crie:

```bash
mkdir -p /home/informatica/web/sendmail.santahelena.pr.gov.br/private
```

## 2. Registro de DNS

No Technitium, aponte `sendmail` para o IP do servidor Hestia e confirme a
propagação antes de emitir o certificado:

```bash
dig +short sendmail.santahelena.pr.gov.br @localhost
dig +short sendmail.santahelena.pr.gov.br @8.8.8.8
```

## 3. Enviar e extrair o pacote

```bash
# Da sua estação:
scp sendmail-1.0.0.tar.gz root@SERVIDOR:/tmp/

# No servidor:
cd /home/informatica/web/sendmail.santahelena.pr.gov.br/
tar xzf /tmp/sendmail-1.0.0.tar.gz --strip-components=1
ls -la
```

O pacote traz `public_html/`, `private/` e `sql/`. A extração preenche as
pastas que o Hestia já criou, sem apagar nada.

## 4. Dependências do PHP

```bash
php -v                                    # precisa ser 8.1 ou superior
php -m | grep -E 'pdo_mysql|mbstring|openssl'
```

Se faltar algo:

```bash
apt install php8.2-mysql php8.2-mbstring -y
systemctl restart php8.2-fpm
```

## 5. Banco de dados

No servidor MariaDB (`192.168.0.91`), como root:

```sql
CREATE DATABASE sendmail CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sendmail'@'IP_DO_HESTIA' IDENTIFIED BY 'SENHA_FORTE_AQUI';
GRANT ALL PRIVILEGES ON sendmail.* TO 'sendmail'@'IP_DO_HESTIA';
FLUSH PRIVILEGES;
```

Teste a conexão a partir do Hestia antes de seguir:

```bash
mariadb -h 192.168.0.91 -u sendmail -p sendmail -e "SELECT 1;"
```

## 6. Conta de envio no Zimbra

Crie uma conta dedicada — não use a caixa pessoal de ninguém:

```bash
su - zimbra
zmprov ca naoresponda@santahelena.pr.gov.br 'SENHA_FORTE' displayName 'Prefeitura de Santa Helena - TIC'
```

**Cota do cbpolicyd.** A política de cota por remetente vai barrar a conta no
meio de uma campanha grande. Confira o limite atual e crie uma exceção para
esta conta na interface do policyd (`https://policyd.santahelena.pr.gov.br`),
ou dimensione a cadência da aplicação abaixo do teto.

Vale também confirmar que a conta pode enviar para fora e que o SPF do domínio
cobre o servidor de saída.

## 7. Configurar a aplicação

```bash
cd /home/informatica/web/sendmail.santahelena.pr.gov.br/private
cp config.exemplo.php config.php

# Gere a chave que assina os links de descadastro:
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

nano config.php
```

Preencha:

| Opção | Valor |
|---|---|
| `banco.host` | `192.168.0.91` |
| `banco.usuario` / `banco.senha` | as credenciais do passo 5 |
| `smtp.host` | servidor Zimbra que aceita submissão autenticada |
| `smtp.porta` / `smtp.seguranca` | `587` / `tls` |
| `smtp.usuario` / `smtp.senha` | a conta do passo 6 |
| `app.chave` | a chave gerada acima — **não mude depois** |
| `app.url_base` | `https://sendmail.santahelena.pr.gov.br` |

> Se `app.chave` mudar, todos os links de descadastro já enviados param de
> funcionar. Gere uma vez e guarde junto das demais senhas do setor.

## 8. Permissões

```bash
cd /home/informatica/web/sendmail.santahelena.pr.gov.br/
chown -R informatica:informatica public_html private sql
chmod 600 private/config.php
chmod 750 private/logs
find public_html private -type f -name "*.php" -exec chmod 640 {} \;
chmod 755 public_html private private/app private/app/views private/bin private/lib
```

O diretório `private` fica fora do `public_html`, então o servidor web não o
serve — não é preciso regra de bloqueio no Apache ou no nginx.

## 9. Criar as tabelas e o primeiro operador

```bash
cd /home/informatica/web/sendmail.santahelena.pr.gov.br/
sudo -u informatica php private/bin/instalar.php
```

O script cria as sete tabelas, confere se todas nasceram e pede os dados do
administrador. A senha tem no mínimo 10 caracteres.

## 10. Agendar o worker

Como usuário `informatica`:

```bash
crontab -u informatica -e
```

```cron
* * * * * /usr/bin/php /home/informatica/web/sendmail.santahelena.pr.gov.br/private/bin/worker.php >> /home/informatica/web/sendmail.santahelena.pr.gov.br/private/logs/cron.log 2>&1
```

Confirme o caminho real do PHP com `which php`. O worker usa trava de arquivo:
mesmo que o cron dispare enquanto o ciclo anterior ainda roda, nenhuma mensagem
é enviada duas vezes.

Verifique o funcionamento:

```bash
tail -f /home/informatica/web/sendmail.santahelena.pr.gov.br/private/logs/cron.log
```

## 11. Conferir a instalação

1. Acesse `https://sendmail.santahelena.pr.gov.br` e entre com o operador criado.
2. Em **Ajustes**, clique em **Testar conexão e autenticação** — deve confirmar
   o SMTP sem enviar mensagem.
3. Cadastre um contato com o seu próprio e-mail.
4. Crie um envio para esse contato, use **Enviar teste para mim** e depois
   **Liberar**.
5. Acompanhe a barra de progresso e confirme a chegada da mensagem.
6. Clique no link de descadastro no rodapé e verifique se o contato passa a
   aparecer como descadastrado na listagem.

## 12. Endurecimento recomendado

**Restringir o painel à rede da Prefeitura.** A página de descadastro precisa
continuar pública, mas o restante pode ser limitado. No template do nginx do
domínio:

```nginx
location = /descadastro.php { }      # permanece aberto

location / {
    allow 192.168.0.0/24;
    allow IP_PUBLICO_DA_PREFEITURA;
    deny all;
}
```

**Backup.** Inclua o banco `sendmail` na rotina de dump e guarde uma cópia de
`private/config.php` fora do servidor — ela contém a chave sem a qual os links
de descadastro antigos deixam de validar.

**Fail2ban.** A tela de login registra as tentativas negadas na tabela
`auditoria`; se quiser bloqueio por IP, um filtro sobre o log de acesso do
domínio resolve.

---

## Atualizações futuras

```bash
cd /home/informatica/web/sendmail.santahelena.pr.gov.br/
cp private/config.php /root/config-sendmail.bak      # segurança
tar xzf /tmp/sendmail-NOVA-VERSAO.tar.gz --strip-components=1
chown -R informatica:informatica public_html private
```

O pacote nunca contém `config.php`, então a configuração local é preservada.
Se a nova versão trouxer mudanças no banco, elas virão descritas nas notas da
versão.

## Solução de problemas

| Sintoma | Onde olhar |
|---|---|
| "Serviço indisponível: falha ao acessar o banco" | credenciais em `config.php`, e se o MariaDB aceita conexão do IP do Hestia |
| Fila parada, nada sai | `crontab -l -u informatica`, e `private/logs/cron.log` |
| Mensagens marcadas como falha | coluna "Detalhe" na tela do envio traz a recusa exata do servidor SMTP |
| Tudo pendente e nada processa | verifique se a **pausa global** está ligada em Ajustes |
| Worker não roda mas o cron dispara | apague `private/logs/worker.lock` se um processo travou |
| E-mails caindo em spam | SPF, DKIM e DMARC do domínio; e se o `From` bate com a conta autenticada |
