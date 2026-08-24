<?php
declare(strict_types=1);

/**
 * Ponto de entrada comum a toda a aplicação (web e linha de comando).
 */

define('RAIZ', dirname(__DIR__));                 // .../private
define('APP',  RAIZ . '/app');
define('LIB',  RAIZ . '/lib');
define('LOGS', RAIZ . '/logs');

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

// ---------------------------------------------------------------------
// Configuração
// ---------------------------------------------------------------------
$arquivoConfig = RAIZ . '/config.php';
if (!is_file($arquivoConfig)) {
    http_response_code(500);
    exit("Configuração ausente. Copie private/config.exemplo.php para private/config.php.\n");
}
$config = require $arquivoConfig;

// ---------------------------------------------------------------------
// Bibliotecas
// ---------------------------------------------------------------------
require_once LIB . '/Exception.php';
require_once LIB . '/PHPMailer.php';
require_once LIB . '/SMTP.php';

foreach (['Db', 'Auditoria', 'Auth', 'Contatos', 'Modelos', 'Campanhas', 'Anexos', 'Mensagem', 'Correio'] as $classe) {
    require_once APP . '/' . $classe . '.php';
}

try {
    Db::iniciar($config['banco']);
} catch (PDOException $erro) {
    $detalhe = 'Não foi possível conectar ao banco de dados em '
        . $config['banco']['host'] . ':' . ($config['banco']['porta'] ?? 3306) . '.';
    if (PHP_SAPI === 'cli') {
        exit($detalhe . "\n" . $erro->getMessage() . "\n");
    }
    error_log('[sendmail] ' . $detalhe . ' ' . $erro->getMessage());
    http_response_code(503);
    exit('Serviço indisponível: falha ao acessar o banco de dados. Avise a equipe de TI.');
}

// ---------------------------------------------------------------------
// Sessão (somente no contexto web)
// ---------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('SENDMAIL');
    session_start();
}

// ---------------------------------------------------------------------
// Funções auxiliares
// ---------------------------------------------------------------------

/** Escapa para saída em HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $caminho, $padrao = null)
{
    global $config;
    $atual = $config;
    foreach (explode('.', $caminho) as $parte) {
        if (!is_array($atual) || !array_key_exists($parte, $atual)) {
            return $padrao;
        }
        $atual = $atual[$parte];
    }
    return $atual;
}

/** Parâmetro ajustável em tempo de execução (tabela parametros). */
function parametro(string $chave, string $padrao = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Db::todos('SELECT chave, valor FROM parametros') as $linha) {
            $cache[$linha['chave']] = $linha['valor'];
        }
    }
    return $cache[$chave] ?? $padrao;
}

function definirParametro(string $chave, string $valor): void
{
    Db::executar(
        'INSERT INTO parametros (chave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
        [$chave, $valor]
    );
}

function token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function conferirToken(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $enviado)) {
        http_response_code(419);
        exit('Sessão expirada. Volte, atualize a página e refaça a operação.');
    }
}

function aviso(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['avisos'][] = ['texto' => $texto, 'tipo' => $tipo];
}

function avisos(): array
{
    $lista = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $lista;
}

function irPara(string $destino): never
{
    header('Location: ' . $destino);
    exit;
}

function ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'cli';
}

/** Token público que autoriza o descadastro de um contato. */
function tokenDescadastro(int $contatoId): string
{
    return substr(hash_hmac('sha256', 'optout:' . $contatoId, (string) config('app.chave')), 0, 32);
}

function linkDescadastro(int $contatoId): string
{
    $base = trim((string) config('app.url_descadastro', ''));
    if ($base === '') {
        $base = (string) config('app.url_base');
    }
    return rtrim($base, '/')
        . '/descadastro.php?c=' . $contatoId . '&t=' . tokenDescadastro($contatoId);
}

function emailValido(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function registrar(string $mensagem): void
{
    if (!is_dir(LOGS)) {
        @mkdir(LOGS, 0750, true);
    }
    @file_put_contents(
        LOGS . '/worker-' . date('Y-m') . '.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL,
        FILE_APPEND
    );
}

/**
 * Renderiza uma tela dentro da moldura padrão.
 *
 * Os nomes internos levam o prefixo __ de propósito: extract() usa EXTR_SKIP e
 * não sobrescreve variáveis já existentes, então um parâmetro chamado $dados
 * bloquearia silenciosamente a chave 'dados' vinda da tela.
 */
function incluirView(string $__tela, array $__variaveis = []): void
{
    $conteudo = APP . '/views/' . $__tela . '.php';
    if (!is_file($conteudo)) {
        http_response_code(500);
        exit('Tela não encontrada: ' . $__tela);
    }
    extract($__variaveis, EXTR_SKIP);
    require APP . '/views/layout.php';
}

/**
 * Teto de mensagens nas últimas 24 horas.
 *
 * A janela é rolante, não o dia do calendário: o que saiu ontem às 15h deixa
 * de contar hoje às 15h. Evita o efeito de meia-noite, em que a fila estoura
 * o teto todo de uma vez assim que a data vira.
 */
function limiteDiario(): int
{
    return max(0, (int) parametro('envios_por_dia', '1000'));
}

function enviadosNaJanela(): int
{
    return (int) Db::valor(
        'SELECT COUNT(*) FROM fila
          WHERE status = "enviado" AND enviado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
}

/** Quanto ainda cabe hoje. PHP_INT_MAX quando o limite está desligado (0). */
function restanteNaJanela(): int
{
    $limite = limiteDiario();
    if ($limite === 0) {
        return PHP_INT_MAX;
    }
    return max(0, $limite - enviadosNaJanela());
}

/** Quando a janela libera a próxima vaga. */
function proximaVaga(): ?string
{
    $limite = limiteDiario();
    if ($limite === 0) {
        return null;
    }
    return Db::valor(
        'SELECT DATE_ADD(enviado_em, INTERVAL 24 HOUR) FROM fila
          WHERE status = "enviado" AND enviado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          ORDER BY enviado_em ASC LIMIT 1'
    );
}
