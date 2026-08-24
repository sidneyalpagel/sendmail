<?php
/** @var string $conteudo caminho da view interna */
$autenticado = (bool) Auth::operador();
$atual = (string) ($_GET['p'] ?? 'painel');

$navegacao = [
    'painel'   => ['Painel',      ['painel']],
    'envios'   => ['Envios',      ['envios', 'envio', 'envio_novo']],
    'contatos' => ['Contatos',    ['contatos', 'contato', 'importar']],
    'bairros'  => ['Bairros',     ['bairros']],
    'modelos'  => ['Modelos',     ['modelos', 'modelo']],
    'relatorio'=> ['Relatórios',  ['relatorio']],
    'auditoria'=> ['Auditoria',   ['auditoria']],
];
if (Auth::eAdmin()) {
    $navegacao['ajustes'] = ['Ajustes', ['ajustes']];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e((string) config('app.nome')) ?></title>
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body class="<?= $autenticado ? 'app' : 'entrada' ?>">

<?php if ($autenticado): ?>
<aside class="lateral">
    <div class="marca">
        <span class="marca__selo">SH</span>
        <span class="marca__texto">
            <strong>Disparador</strong>
            <small>Prefeitura de Santa Helena</small>
        </span>
    </div>

    <nav class="menu">
        <?php foreach ($navegacao as $chave => [$rotulo, $paginas]): ?>
            <a href="?p=<?= $chave ?>" class="menu__item<?= in_array($atual, $paginas, true) ? ' menu__item--ativo' : '' ?>">
                <?= e($rotulo) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="lateral__rodape">
        <div class="operador">
            <strong><?= e(Auth::nome()) ?></strong>
            <small><?= Auth::eAdmin() ? 'Administrador' : 'Operador' ?></small>
        </div>
        <a href="?p=sair" class="sair">Sair</a>
    </div>
</aside>
<?php endif; ?>

<main class="conteudo">
    <?php foreach (avisos() as $item): ?>
        <div class="aviso aviso--<?= e($item['tipo']) ?>"><?= e($item['texto']) ?></div>
    <?php endforeach; ?>

    <?php require $conteudo; ?>
</main>

</body>
</html>
