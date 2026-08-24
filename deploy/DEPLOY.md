# Deploy a partir do GitHub

O servidor busca o código do GitHub por conta própria, usando uma **chave de
deploy** somente-leitura. A chave privada é gerada no servidor e nunca sai dele
— não passa por e-mail, por chat, nem fica gravada em nenhum arquivo do
repositório.

---

## Configuração, uma única vez

### 1. Gerar a chave no servidor Hestia

```bash
ssh-keygen -t ed25519 -C "deploy sendmail hestia" -f ~/.ssh/sendmail_deploy -N ""
```

Isso cria dois arquivos:

- `~/.ssh/sendmail_deploy` — a chave privada, que **fica aqui e só aqui**
- `~/.ssh/sendmail_deploy.pub` — a pública, que vai para o GitHub

```bash
chmod 600 ~/.ssh/sendmail_deploy
cat ~/.ssh/sendmail_deploy.pub
```

### 2. Cadastrar a chave pública no GitHub

No repositório: **Settings → Deploy keys → Add deploy key**

- Title: `Hestia — sendmail.santahelena.pr.gov.br`
- Key: o conteúdo do `.pub` que você acabou de exibir
- **Não** marque "Allow write access" — o servidor só precisa ler

Chave de deploy vale para um repositório só. Se ela vazar, o estrago fica
contido nele, e revogar é apagar a entrada nessa mesma tela — bem diferente de
um token pessoal, que dá acesso a tudo que a sua conta alcança.

### 3. Ensinar o SSH a usá-la

```bash
cat >> ~/.ssh/config <<'FIM'

Host github-sendmail
    HostName github.com
    User git
    IdentityFile ~/.ssh/sendmail_deploy
    IdentitiesOnly yes
FIM
chmod 600 ~/.ssh/config
```

Teste:

```bash
ssh -T github-sendmail
```

A resposta esperada é algo como `Hi SEU_USUARIO/sendmail! You've successfully
authenticated, but GitHub does not provide shell access.` — a recusa de shell é
normal e indica sucesso.

### 4. Preparar o script

```bash
mkdir -p /opt/sendmail-deploy
cd /home/informatica/web/sendmail.santahelena.pr.gov.br/deploy
cp deploy.conf.exemplo deploy.conf
chmod 600 deploy.conf
nano deploy.conf
```

Ajuste `REPO`, `REFERENCIA`, `DESTINO` e `USUARIO`. O `deploy.conf` não guarda
segredo nenhum — só caminhos e o endereço do repositório.

```bash
chmod +x deploy.sh
```

> O primeiro deploy pressupõe a aplicação já instalada pelo `INSTALL.md`: o
> script se recusa a rodar sem um `private/config.php` no destino, justamente
> para não sobrescrever uma instalação pela metade.

---

## Publicando

```bash
cd /home/informatica/web/sendmail.santahelena.pr.gov.br/deploy

./deploy.sh --simular          # mostra o que mudaria, sem tocar em nada
./deploy.sh                    # publica a REFERENCIA do deploy.conf
./deploy.sh v1.1.0             # publica uma tag específica
./deploy.sh --listar           # backups disponíveis
./deploy.sh --voltar           # restaura o backup mais recente
```

O script roda como root (precisa ajustar dono e ler o cron do usuário), mas a
aplicação continua rodando como `informatica`.

### O que ele faz, em ordem

1. Confere `git`, `rsync`, `php`, o diretório do domínio e a existência do
   `config.php`.
2. Busca o repositório no GitHub e posiciona na referência pedida.
3. Roda `php -l` em **todo** o código. Se algum arquivo não compilar, aborta
   antes de publicar qualquer coisa.
4. Mostra o `rsync --dry-run` com as diferenças.
5. Toma a trava do worker, para não trocar arquivos no meio de um envio.
6. Faz backup do que está no ar, depois publica.
7. Ajusta dono e permissões, incluindo `chmod 600` no `config.php`.
8. Roda `instalar.php --tabelas`, que cria tabelas novas se a versão trouxer.
9. Libera a trava, remove backups antigos e avisa se o worker não estiver no
   cron.

### O que ele nunca toca

- `private/config.php` — credenciais e a chave dos links de descadastro
- `private/logs/` — histórico do worker
- O banco de dados, além de criar tabelas que faltem

---

## Fluxo de trabalho sugerido

Na sua estação:

```bash
git switch -c ajuste/nome
# ... alterações ...
git commit -am "Descrição"
git push -u origin ajuste/nome
```

Depois de revisar e mesclar em `main`, feche a versão:

```bash
git switch main && git pull
# atualize o CHANGELOG.md
git commit -am "Prepara 1.1.0"
git tag -a v1.1.0 -m "Versão 1.1.0"
git push && git push origin v1.1.0
```

E no servidor:

```bash
./deploy.sh --simular v1.1.0
./deploy.sh v1.1.0
```

Publicar por **tag** em vez de `main` tem uma vantagem prática: `./deploy.sh
--listar` mostra exatamente qual versão está no ar, e reverter é publicar a tag
anterior.

---

## Quando algo dá errado

| Situação | O que fazer |
|---|---|
| `Permission denied (publickey)` | `ssh -T github-sendmail` para isolar; confira o `~/.ssh/config` e se a chave pública foi cadastrada no repositório certo |
| `referência não encontrada` | a tag existe no GitHub? `git push origin v1.1.0` costuma ser o passo esquecido |
| Abortou por erro de sintaxe | nada foi publicado; corrija, envie de novo e repita o deploy |
| `o worker não liberou a trava` | há um envio grande em andamento; espere o ciclo terminar ou pause a fila em Ajustes |
| Publicou e a aplicação quebrou | `./deploy.sh --voltar` |
| Falhou depois de publicar | os arquivos já estão no ar; `--voltar` restaura, e o registro em `/opt/sendmail-deploy/deploy.log` mostra o que aconteceu |

---

## Sobre credenciais, uma observação

Existe um atalho comum que convém evitar:

```bash
# NÃO faça isto
git remote set-url origin https://usuario:ghp_TOKEN@github.com/org/repo.git
```

O token fica em texto puro no `.git/config`, aparece no `git remote -v`, vaza em
qualquer print de tela, em log de terminal e em histórico de shell. Funciona,
mas o segredo passa a existir em lugares que ninguém audita.

A chave de deploy resolve o mesmo problema sem esse custo: fica em um arquivo
com permissão 600, vale só para este repositório, é somente-leitura, e revogar é
apagar uma linha na tela de Deploy keys.

Se algum token já tiver sido usado assim em outro servidor, o certo é revogá-lo
em <https://github.com/settings/tokens> — trocar a URL depois não resolve,
porque ele já esteve exposto.
