#!/usr/bin/env bash
#
# Deploy do Disparador de e-mails.
#
# Busca a referência indicada no GitHub, publica no diretório do domínio
# preservando config.php e logs, confere a estrutura do banco e guarda um
# backup do que estava no ar.
#
#   ./deploy.sh                 publica a REFERENCIA do deploy.conf
#   ./deploy.sh v1.1.0          publica uma tag específica
#   ./deploy.sh --simular       mostra o que mudaria, sem escrever nada
#   ./deploy.sh --voltar        restaura o backup mais recente
#   ./deploy.sh --listar        lista os backups disponíveis
#
# A autenticação com o GitHub é feita por chave de deploy em ~/.ssh/.
# Nenhum segredo passa por este arquivo.

set -euo pipefail

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF="${AQUI}/deploy.conf"

[[ -f "$CONF" ]] || { echo "Falta ${CONF}. Copie de deploy.conf.exemplo e ajuste."; exit 1; }
# shellcheck disable=SC1090
source "$CONF"

: "${REPO:?defina REPO no deploy.conf}"
: "${DESTINO:?defina DESTINO no deploy.conf}"
: "${TRABALHO:=/opt/sendmail-deploy}"
: "${USUARIO:=informatica}"
: "${GRUPO:=$USUARIO}"
: "${PHP:=/usr/bin/php}"
: "${MANTER_BACKUPS:=5}"
: "${REFERENCIA:=main}"

ESPELHO="${TRABALHO}/repositorio"
BACKUPS="${TRABALHO}/backups"
REGISTRO="${TRABALHO}/deploy.log"

SIMULAR=0
VOLTAR=0
LISTAR=0

for arg in "$@"; do
    case "$arg" in
        --simular|-n) SIMULAR=1 ;;
        --voltar)     VOLTAR=1 ;;
        --listar)     LISTAR=1 ;;
        -h|--help)    sed -n '3,20p' "$0" | sed 's/^# \?//'; exit 0 ;;
        -*)           echo "Opção desconhecida: $arg"; exit 1 ;;
        *)            REFERENCIA="$arg" ;;
    esac
done

# ---------------------------------------------------------------------
# Saída
# ---------------------------------------------------------------------
azul()  { printf '\033[1;36m%s\033[0m\n' "$*"; }
ok()    { printf '  \033[0;32m✓\033[0m %s\n' "$*"; }
erro()  { printf '  \033[0;31m✗\033[0m %s\n' "$*" >&2; }
nota()  { printf '  %s\n' "$*"; }

anotar() {
    mkdir -p "$TRABALHO"
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >> "$REGISTRO"
}

abortar() { erro "$*"; anotar "ABORTADO: $*"; exit 1; }

# ---------------------------------------------------------------------
# Listar backups
# ---------------------------------------------------------------------
if [[ $LISTAR -eq 1 ]]; then
    azul "Backups em ${BACKUPS}"
    if [[ -d "$BACKUPS" ]]; then
        ls -1t "$BACKUPS" | while read -r b; do
            versao="$(cat "${BACKUPS}/${b}/.versao" 2>/dev/null || echo '?')"
            printf '  %-22s  %s\n' "$b" "$versao"
        done
    else
        nota "nenhum backup ainda"
    fi
    exit 0
fi

# ---------------------------------------------------------------------
# Restaurar
# ---------------------------------------------------------------------
if [[ $VOLTAR -eq 1 ]]; then
    ULTIMO="$(ls -1t "$BACKUPS" 2>/dev/null | head -1 || true)"
    [[ -n "$ULTIMO" ]] || abortar "não há backup para restaurar"

    azul "Restaurando ${ULTIMO}"
    nota "versão: $(cat "${BACKUPS}/${ULTIMO}/.versao" 2>/dev/null || echo '?')"
    read -r -p "  Confirma? [s/N] " resposta
    [[ "$resposta" =~ ^[sS]$ ]] || { nota "cancelado"; exit 0; }

    rsync -a --delete \
        --exclude 'private/config.php' \
        --exclude 'private/logs/' \
        "${BACKUPS}/${ULTIMO}/" "${DESTINO}/"
    chown -R "${USUARIO}:${GRUPO}" "${DESTINO}/public_html" "${DESTINO}/private"
    ok "restaurado"
    anotar "RESTAURADO a partir de ${ULTIMO}"
    exit 0
fi

# ---------------------------------------------------------------------
# Verificações
# ---------------------------------------------------------------------
azul "Deploy do Disparador de e-mails"
nota "referência: ${REFERENCIA}"
nota "destino:    ${DESTINO}"
[[ $SIMULAR -eq 1 ]] && nota "MODO SIMULAÇÃO — nada será escrito"
echo

azul "1. Verificações"

for programa in git rsync "$PHP"; do
    command -v "$programa" >/dev/null 2>&1 || abortar "não encontrei: ${programa}"
done
ok "git, rsync e php disponíveis"

[[ -d "$DESTINO" ]] || abortar "o diretório do domínio não existe: ${DESTINO}"
ok "diretório do domínio encontrado"

if [[ ! -f "${DESTINO}/private/config.php" ]]; then
    erro "não há private/config.php no destino"
    nota "esta é a primeira instalação: siga o INSTALL.md antes do primeiro deploy"
    exit 1
fi
ok "config.php presente (será preservado)"

id -u "$USUARIO" >/dev/null 2>&1 || abortar "usuário ${USUARIO} não existe"
ok "usuário ${USUARIO} confirmado"

# ---------------------------------------------------------------------
# Buscar o código
# ---------------------------------------------------------------------
echo
azul "2. Buscando o código"

mkdir -p "$TRABALHO" "$BACKUPS"

ORIGEM="$REPO"
if [[ -n "${HOST_SSH:-}" ]]; then
    # Usa o apelido do ~/.ssh/config, que aponta para a chave de deploy.
    ORIGEM="${REPO/git@github.com:/${HOST_SSH}:}"
fi

if [[ -d "${ESPELHO}/.git" ]]; then
    git -C "$ESPELHO" remote set-url origin "$ORIGEM"
    git -C "$ESPELHO" fetch --all --tags --prune --quiet || abortar "falha ao buscar do GitHub (confira a chave de deploy)"
    ok "repositório atualizado"
else
    rm -rf "$ESPELHO"
    git clone --quiet "$ORIGEM" "$ESPELHO" || abortar "falha ao clonar (confira a chave de deploy e a URL)"
    ok "repositório clonado"
fi

git -C "$ESPELHO" rev-parse --verify --quiet "${REFERENCIA}^{commit}" >/dev/null \
    || abortar "referência não encontrada no repositório: ${REFERENCIA}"

git -C "$ESPELHO" -c advice.detachedHead=false checkout --quiet --force "$REFERENCIA"
git -C "$ESPELHO" clean -qfd

COMMIT="$(git -C "$ESPELHO" rev-parse --short HEAD)"
DESCRICAO="$(git -C "$ESPELHO" log -1 --pretty='%s')"
ok "em ${REFERENCIA} (${COMMIT}) — ${DESCRICAO}"

for obrigatorio in public_html/index.php private/bin/worker.php sql/schema.sql; do
    [[ -f "${ESPELHO}/${obrigatorio}" ]] || abortar "o repositório não parece o certo: falta ${obrigatorio}"
done
ok "estrutura do repositório conferida"

# ---------------------------------------------------------------------
# Sintaxe
# ---------------------------------------------------------------------
echo
azul "3. Conferindo a sintaxe do PHP"

FALHAS=0
while IFS= read -r arquivo; do
    "$PHP" -l "$arquivo" >/dev/null 2>&1 || { erro "erro de sintaxe: ${arquivo#$ESPELHO/}"; FALHAS=1; }
done < <(find "$ESPELHO" -name '*.php' -not -path '*/lib/*' -not -path '*/.git/*')
[[ $FALHAS -eq 0 ]] || abortar "há erro de sintaxe; nada foi publicado"
ok "todos os arquivos compilam"

# ---------------------------------------------------------------------
# O que vai mudar
# ---------------------------------------------------------------------
echo
azul "4. Diferenças"

EXCLUIR=(
    --exclude '.git/'
    --exclude '.github/'
    --exclude '.gitignore'
    --exclude '.editorconfig'
    --exclude 'private/config.php'
    --exclude 'private/logs/'
    --exclude '*.md'
    # O próprio deploy vive fora do diretório publicado: o bash lê o script
    # em pedaços enquanto executa, e sobrescrevê-lo em pleno rsync faria a
    # execução continuar em um arquivo diferente do que começou.
    --exclude 'deploy/'
)

rsync -a --delete --itemize-changes --dry-run "${EXCLUIR[@]}" \
    "${ESPELHO}/" "${DESTINO}/" \
    | grep -Ev '^\.[df]\.\.\.\.\.\.\.\.' \
    | sed 's/^/  /' || true

if [[ $SIMULAR -eq 1 ]]; then
    echo
    nota "simulação encerrada — nada foi alterado"
    exit 0
fi

# ---------------------------------------------------------------------
# Pausar o worker durante a troca de arquivos
# ---------------------------------------------------------------------
echo
azul "5. Publicando"

TRAVA="${DESTINO}/private/logs/worker.lock"
mkdir -p "$(dirname "$TRAVA")"
exec 9>"$TRAVA"
if flock -w 90 9; then
    ok "worker pausado (trava obtida)"
else
    abortar "o worker não liberou a trava em 90s; tente de novo daqui a pouco"
fi

# Backup do que está no ar
CARIMBO="$(date '+%Y%m%d-%H%M%S')"
DESTINO_BACKUP="${BACKUPS}/${CARIMBO}"
mkdir -p "$DESTINO_BACKUP"
rsync -a --exclude 'private/logs/' "${DESTINO}/public_html" "${DESTINO}/private" "${DESTINO_BACKUP}/" 2>/dev/null || true
echo "${REFERENCIA} (${COMMIT}) — ${DESCRICAO}" > "${DESTINO_BACKUP}/.versao"
ok "backup em ${DESTINO_BACKUP}"

# Publicação
rsync -a --delete "${EXCLUIR[@]}" "${ESPELHO}/" "${DESTINO}/"
ok "arquivos publicados"

# Documentação, sem apagar nada
rsync -a "${ESPELHO}/README.md" "${ESPELHO}/INSTALL.md" "${ESPELHO}/CHANGELOG.md" "${DESTINO}/" 2>/dev/null || true

# ---------------------------------------------------------------------
# Permissões
# ---------------------------------------------------------------------
echo
azul "6. Permissões"

chown -R "${USUARIO}:${GRUPO}" "${DESTINO}/public_html" "${DESTINO}/private" "${DESTINO}/sql" 2>/dev/null || true
find "${DESTINO}/public_html" "${DESTINO}/private" -type d -exec chmod 755 {} \;
find "${DESTINO}/public_html" "${DESTINO}/private" -type f -exec chmod 644 {} \;
chmod 600 "${DESTINO}/private/config.php"
chmod 750 "${DESTINO}/private/logs"
chmod 755 "${DESTINO}/private/bin"/*.php
ok "dono e permissões ajustados"

# ---------------------------------------------------------------------
# Banco
# ---------------------------------------------------------------------
echo
azul "7. Estrutura do banco"

if sudo -u "$USUARIO" "$PHP" "${DESTINO}/private/bin/instalar.php" --tabelas; then
    ok "estrutura conferida"
else
    erro "a conferência do banco falhou"
    nota "os arquivos já estão publicados; use ./deploy.sh --voltar se precisar reverter"
    anotar "FALHA na conferência do banco após publicar ${REFERENCIA} (${COMMIT})"
    exit 1
fi

# ---------------------------------------------------------------------
# Limpeza e verificação
# ---------------------------------------------------------------------
echo
azul "8. Encerramento"

# Mantém apenas os N backups mais recentes
if [[ -d "$BACKUPS" ]]; then
    ls -1t "$BACKUPS" | tail -n +$((MANTER_BACKUPS + 1)) | while read -r velho; do
        rm -rf "${BACKUPS:?}/${velho}"
        nota "backup removido: ${velho}"
    done
fi

# Libera a trava antes de conferir, para o worker poder voltar
exec 9>&-
ok "worker liberado"

if crontab -u "$USUARIO" -l 2>/dev/null | grep -q 'worker.php'; then
    ok "cron do worker está agendado"
else
    erro "o worker NÃO está no cron do usuário ${USUARIO} — a fila não vai andar"
    nota "veja a seção 10 do INSTALL.md"
fi

# O deploy.sh não é publicado junto; avisa quando a versão do repositório mudou.
if ! cmp -s "${ESPELHO}/deploy/deploy.sh" "${AQUI}/deploy.sh" 2>/dev/null; then
    erro "o deploy.sh do repositório está diferente deste"
    nota "atualize com:  cp ${ESPELHO}/deploy/deploy.sh ${AQUI}/deploy.sh"
    nota "e confira se o deploy.conf.exemplo ganhou opções novas"
fi

anotar "PUBLICADO ${REFERENCIA} (${COMMIT}) — ${DESCRICAO}"

echo
azul "Pronto"
nota "versão no ar: ${REFERENCIA} (${COMMIT})"
nota "registro:     ${REGISTRO}"
nota "reverter:     ${AQUI}/deploy.sh --voltar"
