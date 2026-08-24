<?php $totalPaginas = max(1, (int) ceil($dados['total'] / $por)); ?>

<div class="topo">
    <div>
        <span class="rotulo">Contatos</span>
        <h1><?= number_format($dados['total'], 0, ',', '.') ?> <?= $dados['total'] === 1 ? 'contato' : 'contatos' ?></h1>
        <p>Quem está descadastrado nunca entra em um envio, mesmo que o escopo seja "todos".</p>
    </div>
    <div class="acoes">
        <a href="?p=importar" class="botao botao--neutro">Importar CSV</a>
        <a href="?p=contato" class="botao">Novo contato</a>
    </div>
</div>

<form method="get" class="filtros">
    <input type="hidden" name="p" value="contatos">
    <div class="campo campo--largo">
        <label for="q">Buscar</label>
        <input type="text" id="q" name="q" value="<?= e($filtros['texto']) ?>" placeholder="nome, e-mail ou telefone">
    </div>
    <div class="campo">
        <label for="bairro">Bairro</label>
        <select id="bairro" name="bairro">
            <option value="">Todos</option>
            <?php foreach ($bairros as $b): ?>
                <option value="<?= e($b['bairro']) ?>" <?= $filtros['bairro'] === $b['bairro'] ? 'selected' : '' ?>>
                    <?= e($b['bairro']) ?> (<?= (int) $b['aptos'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label for="situacao">Situação</label>
        <select id="situacao" name="situacao">
            <option value="">Todas</option>
            <option value="ativos" <?= $filtros['situacao'] === 'ativos' ? 'selected' : '' ?>>Aptos a receber</option>
            <option value="inativos" <?= $filtros['situacao'] === 'inativos' ? 'selected' : '' ?>>Inativos</option>
            <option value="descadastrados" <?= $filtros['situacao'] === 'descadastrados' ? 'selected' : '' ?>>Descadastrados</option>
        </select>
    </div>
    <button class="botao botao--neutro">Filtrar</button>
</form>

<?php if (!$dados['itens']): ?>
    <div class="vazio">
        <strong>Nenhum contato encontrado</strong>
        Ajuste os filtros ou importe a lista por CSV.
    </div>
<?php else: ?>
<div class="rolagem">
    <table class="tabela">
        <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Bairro</th><th>Telefone</th><th>Situação</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($dados['itens'] as $c): ?>
            <tr>
                <td><a href="?p=contato&amp;id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></a></td>
                <td class="dado"><?= e($c['email']) ?></td>
                <td><?= e($c['bairro'] ?? '—') ?></td>
                <td class="dado"><?= e($c['telefone'] ?? '—') ?></td>
                <td>
                    <?php if ((int) $c['opt_out'] === 1): ?>
                        <span class="etiqueta etq-cancelada">descadastrado</span>
                    <?php elseif ((int) $c['ativo'] === 0): ?>
                        <span class="etiqueta etq-suprimido">inativo</span>
                    <?php else: ?>
                        <span class="etiqueta etq-concluida">apto</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $c['opt_out'] === 1): ?>
                        <form method="post" onsubmit="return confirm('Reativar o recebimento para este contato?')">
                            <input type="hidden" name="csrf" value="<?= token() ?>">
                            <input type="hidden" name="acao" value="contato_recadastrar">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="botao botao--neutro botao--pequeno">Reativar</button>
                        </form>
                    <?php else: ?>
                        <form method="post" onsubmit="return confirm('Marcar como descadastrado? Ele deixa de receber envios.')">
                            <input type="hidden" name="csrf" value="<?= token() ?>">
                            <input type="hidden" name="acao" value="contato_descadastrar">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="botao botao--neutro botao--pequeno">Descadastrar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPaginas > 1):
    $base = '?p=contatos&q=' . urlencode($filtros['texto'])
          . '&bairro=' . urlencode($filtros['bairro'])
          . '&situacao=' . urlencode($filtros['situacao']) . '&pag=';
?>
<div class="paginacao">
    <?php if ($pag > 1): ?><a href="<?= $base . ($pag - 1) ?>">Anterior</a><?php endif; ?>
    <span>Página <?= $pag ?> de <?= $totalPaginas ?></span>
    <?php if ($pag < $totalPaginas): ?><a href="<?= $base . ($pag + 1) ?>">Próxima</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
