<?php
declare(strict_types=1);

/**
 * Página pública de cancelamento de recebimento.
 *
 * Autônoma de propósito: roda em um domínio alcançável pela internet, separada
 * do painel, que fica na rede interna. Não tem sessão, não tem login, não lê
 * campanhas nem operadores. A única coisa que faz é marcar um contato como
 * descadastrado, e só mediante um token assinado.
 *
 * Acessada pelo link do rodapé das mensagens e pelo botão de cancelar inscrição
 * dos clientes de e-mail (RFC 8058, requisição One-Click).
 */

$config = require __DIR__ . '/config.php';

date_default_timezone_set($config['fuso'] ?? 'America/Sao_Paulo');
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------
// Sem indexação, sem referrer vazando o token
// ---------------------------------------------------------------------
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'");

function e(?string $t): string
{
    return htmlspecialchars((string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------
// Banco
// ---------------------------------------------------------------------
function banco(array $cfg): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['porta'] ?? 3306,
            $cfg['nome']
        );
        $pdo = new PDO($dsn, $cfg['usuario'], $cfg['senha'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

// ---------------------------------------------------------------------
// Validação do pedido
// ---------------------------------------------------------------------
$contatoId = (int) ($_GET['c'] ?? 0);
$token     = (string) ($_GET['t'] ?? '');
$metodo    = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$esperado = $contatoId > 0
    ? substr(hash_hmac('sha256', 'optout:' . $contatoId, (string) $config['chave']), 0, 32)
    : '';

$autorizado = $contatoId > 0 && $esperado !== '' && hash_equals($esperado, $token);

// Requisição One-Click disparada pelo próprio cliente de e-mail: confirma
// sem apresentar tela, e responde apenas com o código de status.
$umClique = $metodo === 'POST'
    && str_contains((string) ($_POST['List-Unsubscribe'] ?? ''), 'One-Click');

$estado  = 'invalido';   // invalido | confirmar | pronto | erro
$contato = null;

if ($autorizado) {
    try {
        $pdo = banco($config['banco']);

        $consulta = $pdo->prepare('SELECT id, nome, email, opt_out FROM contatos WHERE id = ?');
        $consulta->execute([$contatoId]);
        $contato = $consulta->fetch() ?: null;

        if (!$contato) {
            $estado = 'invalido';
        } elseif ((int) $contato['opt_out'] === 1) {
            $estado = 'pronto';
        } elseif ($umClique || ($metodo === 'POST' && isset($_POST['confirmar']))) {
            $pdo->prepare('UPDATE contatos SET opt_out = 1, opt_out_em = NOW() WHERE id = ?')
                ->execute([$contatoId]);

            $pdo->prepare(
                'INSERT INTO auditoria (operador_id, operador_nome, acao, entidade, entidade_id, detalhe, ip)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?)'
            )->execute([
                'público',
                'descadastro',
                'contato',
                (string) $contatoId,
                $umClique ? 'um clique pelo cliente de e-mail' : 'link do rodapé',
                $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            $estado = 'pronto';
        } else {
            $estado = 'confirmar';
        }
    } catch (Throwable $erro) {
        error_log('[descadastro] ' . $erro->getMessage());
        $estado = 'erro';
    }
}

// O cliente de e-mail não exibe página: responde só o status.
if ($umClique) {
    http_response_code($estado === 'pronto' ? 200 : 400);
    exit;
}

if ($estado === 'invalido') {
    http_response_code(404);
} elseif ($estado === 'erro') {
    http_response_code(503);
}

$orgao   = (string) ($config['orgao'] ?? 'Prefeitura Municipal de Santa Helena');
$suporte = (string) ($config['email_suporte'] ?? 'ti@santahelena.pr.gov.br');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Cancelar recebimento — <?= e($orgao) ?></title>
<link rel="stylesheet" href="assets/estilo.css">
</head>
<body>
<main class="caixa">
    <div class="marca">
        <span class="selo">SH</span>
        <div>
            <strong><?= e($orgao) ?></strong>
            <small>Comunicados por e-mail</small>
        </div>
    </div>

    <?php if ($estado === 'pronto'): ?>
        <h1>Cancelamento confirmado</h1>
        <p>
            O endereço <strong><?= e($contato['email'] ?? '') ?></strong> não receberá mais
            os comunicados desta lista.
        </p>
        <p class="miudo">
            Mensagens individuais de atendimento, respondendo a solicitações que você fizer,
            continuam funcionando normalmente.
        </p>

    <?php elseif ($estado === 'confirmar'): ?>
        <h1>Cancelar o recebimento</h1>
        <p>
            Confirmando, o endereço <strong><?= e($contato['email'] ?? '') ?></strong> deixa de
            receber os comunicados enviados pela <?= e($orgao) ?>.
        </p>
        <form method="post">
            <button type="submit" name="confirmar" value="1" class="botao">
                Confirmar cancelamento
            </button>
        </form>
        <p class="miudo">Você pode voltar a receber depois, entrando em contato com o setor responsável.</p>

    <?php elseif ($estado === 'erro'): ?>
        <h1>Não foi possível concluir agora</h1>
        <p>
            Houve uma falha ao registrar o cancelamento. Tente novamente em alguns minutos.
        </p>
        <p class="miudo">
            Se o problema continuar, escreva para
            <a href="mailto:<?= e($suporte) ?>"><?= e($suporte) ?></a> pedindo a remoção —
            ela será feita manualmente.
        </p>

    <?php else: ?>
        <h1>Link inválido</h1>
        <p>
            Este endereço de cancelamento não confere. O motivo mais comum é o link ter sido
            copiado pela metade.
        </p>
        <p class="miudo">
            Tente clicar direto no link do e-mail. Se não funcionar, escreva para
            <a href="mailto:<?= e($suporte) ?>"><?= e($suporte) ?></a> que o cadastro será
            removido manualmente.
        </p>
    <?php endif; ?>
</main>
</body>
</html>
