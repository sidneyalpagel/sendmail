<?php
declare(strict_types=1);

/**
 * Página pública de descadastro. Acessada pelo link do rodapé das mensagens
 * e pelo botão "cancelar inscrição" dos clientes de e-mail (List-Unsubscribe).
 */

require_once dirname(__DIR__) . '/private/app/bootstrap.php';

$contatoId = (int) ($_GET['c'] ?? 0);
$token     = (string) ($_GET['t'] ?? '');

$valido  = $contatoId > 0 && hash_equals(tokenDescadastro($contatoId), $token);
$contato = $valido ? Contatos::buscar($contatoId) : null;

// Requisição One-Click do cliente de e-mail (RFC 8058): confirma sem tela.
$umClique = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && str_contains((string) ($_POST['List-Unsubscribe'] ?? ''), 'One-Click');

$feito = false;

if ($contato && (int) $contato['opt_out'] === 1) {
    $feito = true;
} elseif ($contato && ($umClique || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['confirmar'])))) {
    Contatos::descadastrar($contatoId, 'link do e-mail');
    $feito = true;
}

if ($umClique) {
    http_response_code(200);
    exit;
}

$orgao = (string) config('app.orgao');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Cancelar recebimento — <?= e($orgao) ?></title>
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body class="entrada">
<main class="conteudo">
    <div class="entrada__marca">
        <span class="marca__selo">SH</span>
        <h1><?= e($orgao) ?></h1>
    </div>

    <div class="cartao">
        <?php if (!$valido || !$contato): ?>
            <h2>Link inválido</h2>
            <p class="ajuda">
                Este endereço de cancelamento não confere. Ele pode ter sido copiado pela metade.
                Escreva para <a href="mailto:ti@santahelena.pr.gov.br">ti@santahelena.pr.gov.br</a>
                e o cadastro será removido manualmente.
            </p>

        <?php elseif ($feito): ?>
            <h2>Cancelamento confirmado</h2>
            <p class="ajuda">
                O endereço <strong><?= e($contato['email']) ?></strong> não receberá mais
                comunicados desta lista. Mensagens individuais de atendimento continuam
                funcionando normalmente.
            </p>

        <?php else: ?>
            <h2>Cancelar o recebimento</h2>
            <p class="ajuda">
                Confirmando, o endereço <strong><?= e($contato['email']) ?></strong> deixa de
                receber os comunicados enviados por esta lista.
            </p>
            <form method="post">
                <button class="botao botao--perigo" name="confirmar" value="1" style="width:100%">
                    Confirmar cancelamento
                </button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
