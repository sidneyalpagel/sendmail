<?php
$soma = ['total' => 0, 'enviados' => 0, 'falhas' => 0, 'suprimidos' => 0];
foreach ($linhas as $c) {
    $soma['total']      += (int) $c['total'];
    $soma['enviados']   += (int) $c['enviados'];
    $soma['falhas']     += (int) $c['falhas'];
    $soma['suprimidos'] += (int) $c['suprimidos'];
}

$duracao = static function (?string $inicio, ?string $fim): string {
    if (!$inicio || !$fim) {
        return '—';
    }
    $seg = max(0, strtotime($fim) - strtotime($inicio));
    if ($seg < 60)    { return $seg . 's'; }
    if ($seg < 3600)  { return round($seg / 60) . ' min'; }
    return number_format($seg / 3600, 1, ',', '.') . ' h';
};

$taxa = static fn(array $c) => (int) $c['total'] > 0
    ? number_format((int) $c['enviados'] * 100 / (int) $c['total'], 1, ',', '.') . '%'
    : '—';

$query = 'de=' . urlencode($de) . '&ate=' . urlencode($ate) . '&status=' . urlencode($status);
?>

<div class="topo">
    <div>
        <span class="rotulo">Relatórios</span>
        <h1>Envios por campanha</h1>
        <p>Somente campanhas liberadas — rascunhos ficam de fora. O período filtra pela data de liberação.</p>
    </div>
    <a href="?p=relatorio_csv&amp;<?= $query ?>" class="botao botao--neutro">Baixar CSV</a>
</div>

<form method="get" class="filtros">
    <input type="hidden" name="p" value="relatorio">
    <div class="campo">
        <label for="de">De</label>
        <input type="date" id="de" name="de" value="<?= e($de) ?>">
    </div>
    <div class="campo">
        <label for="ate">Até</label>
        <input type="date" id="ate" name="ate" value="<?= e($ate) ?>">
    </div>
    <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
            <option value="">Todas</option>
            <?php foreach (['concluida' => 'Concluídas', 'enviando' => 'Enviando', 'na_fila' => 'Na fila',
                            'pausada' => 'Pausadas', 'cancelada' => 'Canceladas'] as $chave => $rotulo): ?>
                <option value="<?= $chave ?>" <?= $status === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="botao botao--neutro">Filtrar</button>
</form>

<?php if (!$linhas): ?>
    <div class="vazio">
        <strong>Nenhum envio no período</strong>
        Ajuste os filtros acima.
    </div>
<?php else: ?>

<div class="cartao">
    <h2>Resumo do período</h2>
    <div class="grade grade--4">
        <div class="numero"><div class="numero__valor"><?= count($linhas) ?></div><div class="numero__rotulo"><?= count($linhas) === 1 ? 'Campanha' : 'Campanhas' ?></div></div>
        <div class="numero"><div class="numero__valor"><?= number_format($soma['enviados'], 0, ',', '.') ?></div><div class="numero__rotulo">Entregues</div></div>
        <div class="numero"><div class="numero__valor"><?= number_format($soma['falhas'], 0, ',', '.') ?></div><div class="numero__rotulo">Falhas</div></div>
        <div class="numero"><div class="numero__valor"><?= $soma['total'] > 0 ? number_format($soma['enviados'] * 100 / $soma['total'], 1, ',', '.') . '%' : '—' ?></div><div class="numero__rotulo">Taxa de entrega</div></div>
    </div>
</div>

<div class="rolagem">
    <table class="tabela">
        <thead>
        <tr>
            <th>Envio</th><th>Público</th><th>Liberado em</th><th>Duração</th>
            <th>Total</th><th>Entregues</th><th>Falhas</th><th>Suprimidos</th>
            <th>Taxa</th><th>Situação</th><th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($linhas as $c): ?>
            <tr>
                <td><a href="?p=envio&amp;id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></a></td>
                <td><?= e(Campanhas::descricaoEscopo($c)) ?></td>
                <td class="dado"><?= $c['iniciado_em'] ? e(date('d/m/Y H:i', strtotime($c['iniciado_em']))) : '—' ?></td>
                <td class="dado"><?= e($duracao($c['iniciado_em'], $c['concluido_em'])) ?></td>
                <td class="dado"><?= (int) $c['total'] ?></td>
                <td class="dado"><?= (int) $c['enviados'] ?></td>
                <td class="dado"><?= (int) $c['falhas'] ?></td>
                <td class="dado"><?= (int) $c['suprimidos'] ?></td>
                <td class="dado"><?= e($taxa($c)) ?></td>
                <td><span class="etiqueta etq-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                <td><a href="?p=envio_csv&amp;id=<?= (int) $c['id'] ?>" class="botao botao--neutro botao--pequeno">CSV</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <td colspan="4"><strong>Total do período</strong></td>
            <td class="dado"><strong><?= number_format($soma['total'], 0, ',', '.') ?></strong></td>
            <td class="dado"><strong><?= number_format($soma['enviados'], 0, ',', '.') ?></strong></td>
            <td class="dado"><strong><?= number_format($soma['falhas'], 0, ',', '.') ?></strong></td>
            <td class="dado"><strong><?= number_format($soma['suprimidos'], 0, ',', '.') ?></strong></td>
            <td class="dado" colspan="3">
                <strong><?= $soma['total'] > 0 ? number_format($soma['enviados'] * 100 / $soma['total'], 1, ',', '.') . '%' : '—' ?></strong>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
