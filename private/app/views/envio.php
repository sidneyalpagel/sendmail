<?php
$id        = (int) $campanha['id'];
$status    = $campanha['status'];
$rascunho  = $status === 'rascunho';
$emCurso   = in_array($status, ['na_fila', 'enviando'], true);

$total      = max(1, (int) ($numeros['total'] ?? 0));
$enviados   = (int) ($numeros['enviados'] ?? 0);
$falhas     = (int) ($numeros['falhas'] ?? 0);
$suprimidos = (int) ($numeros['suprimidos'] ?? 0);
$pendentes  = (int) ($numeros['pendentes'] ?? 0);
$pct = static fn(int $n) => round($n * 100 / $total, 2);
?>

<div class="topo">
    <div>
        <span class="rotulo">Envio nº <?= $id ?></span>
        <h1><?= e($campanha['nome']) ?></h1>
        <p><?= e(Campanhas::descricaoEscopo($campanha)) ?> &nbsp;·&nbsp;
           criado em <?= e(date('d/m/Y H:i', strtotime($campanha['criado_em']))) ?></p>
        <?php if (!empty($anexos)): ?>
            <p style="margin-top:4px">
                Anexos:
                <?php foreach ($anexos as $a): ?>
                    <span class="dado"><?= e($a['nome']) ?> (<?= e(Anexos::legivel((int) $a['tamanho'])) ?>)</span>&nbsp;
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>
    <span class="etiqueta etq-<?= e($status) ?>" style="font-size:12px;padding:6px 12px"><?= e($status) ?></span>
</div>

<?php if ($rascunho): ?>
<div class="cartao">
    <h2>Pronto para liberar</h2>
    <p class="ajuda">
        Este envio alcança <strong><?= number_format($previsto, 0, ',', '.') ?></strong>
        <?= $previsto === 1 ? 'destinatário apto' : 'destinatários aptos' ?> neste momento.
        Ao liberar, a lista é congelada: quem entrar na base depois não recebe esta mensagem.
    </p>

    <div class="acoes">
        <a href="?p=previa&amp;id=<?= $id ?>" target="_blank" rel="noopener" class="botao botao--neutro">Ver prévia</a>
        <a href="?p=envio_novo&amp;id=<?= $id ?>" class="botao botao--neutro">Editar</a>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= token() ?>">
            <input type="hidden" name="acao" value="envio_teste">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="voltar" value="?p=envio&amp;id=<?= $id ?>">
            <input type="hidden" name="email_teste" value="<?= e(Auth::operador()['email'] ?? '') ?>">
            <button class="botao botao--neutro">Enviar teste para mim</button>
        </form>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= token() ?>">
            <input type="hidden" name="acao" value="envio_para_modelo">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="voltar" value="?p=envio&amp;id=<?= $id ?>">
            <button class="botao botao--neutro">Salvar como modelo</button>
        </form>

        <form method="post" onsubmit="return confirm('Liberar o envio para <?= $previsto ?> destinatários? Não há como recolher mensagens já entregues.')">
            <input type="hidden" name="csrf" value="<?= token() ?>">
            <input type="hidden" name="acao" value="envio_liberar">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="voltar" value="?p=envio&amp;id=<?= $id ?>">
            <button class="botao">Liberar envio</button>
        </form>
    </div>
</div>

<?php else: ?>

<div class="cartao">
    <h2>Andamento</h2>
    <div class="fila-barra" id="barra">
        <span class="seg-enviado"   style="width:<?= $pct($enviados) ?>%"></span>
        <span class="seg-falha"     style="width:<?= $pct($falhas) ?>%"></span>
        <span class="seg-suprimido" style="width:<?= $pct($suprimidos) ?>%"></span>
        <span class="seg-pendente"  style="width:<?= $pct($pendentes) ?>%"></span>
    </div>

    <div class="fila-legenda">
        <div><i class="i-enviado"></i> <b id="n-enviados"><?= $enviados ?></b> entregues</div>
        <div><i class="i-falha"></i> <b id="n-falhas"><?= $falhas ?></b> falharam</div>
        <div><i class="i-suprimido"></i> <b id="n-suprimidos"><?= $suprimidos ?></b> suprimidos</div>
        <div><i class="i-pendente"></i> <b id="n-pendentes"><?= $pendentes ?></b> na fila</div>
        <div>de <b><?= (int) ($numeros['total'] ?? 0) ?></b> no total</div>
    </div>

    <?php if ($emCurso): ?>
        <?php $sobraDia = limiteDiario() === 0 ? null : restanteNaJanela(); ?>
        <?php if ($sobraDia !== null && $sobraDia === 0): ?>
            <p class="ajuda" style="margin:16px 0 0">
                <strong>Aguardando o teto diário.</strong> O limite de
                <?= number_format(limiteDiario(), 0, ',', '.') ?> mensagens em 24 horas foi atingido.
                Os <?= $pendentes ?> pendentes saem a partir de
                <?= e(date('d/m/Y H:i', strtotime((string) proximaVaga()))) ?>, sem precisar de
                nenhuma ação sua.
            </p>
        <?php else: ?>
            <p class="ajuda" style="margin:16px 0 0">
                A fila é processada em segundo plano, a até
                <strong><?= e(parametro('envios_por_minuto', '20')) ?></strong> por minuto<?php
                if ($sobraDia !== null): ?>, com
                <strong><?= number_format($sobraDia, 0, ',', '.') ?></strong> ainda disponíveis
                nas próximas 24 horas<?php endif; ?>.
                Você pode fechar esta página — o envio continua.
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="acoes" style="margin-top:18px">
        <a href="?p=previa&amp;id=<?= $id ?>" target="_blank" rel="noopener" class="botao botao--neutro botao--pequeno">Ver a mensagem</a>
        <a href="?p=envio_csv&amp;id=<?= $id ?>" class="botao botao--neutro botao--pequeno">Baixar relatório (CSV)</a>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= token() ?>">
            <input type="hidden" name="acao" value="envio_para_modelo">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="voltar" value="?p=envio&amp;id=<?= $id ?>">
            <button class="botao botao--neutro botao--pequeno">Salvar como modelo</button>
        </form>

        <?php if ($emCurso): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= token() ?>">
                <input type="hidden" name="acao" value="envio_pausar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="botao botao--alerta botao--pequeno">Pausar</button>
            </form>
        <?php elseif ($status === 'pausada'): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= token() ?>">
                <input type="hidden" name="acao" value="envio_retomar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="botao botao--pequeno">Retomar</button>
            </form>
        <?php endif; ?>

        <?php if ($falhas > 0): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= token() ?>">
                <input type="hidden" name="acao" value="envio_reenviar_falhas">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="voltar" value="?p=envio&amp;id=<?= $id ?>">
                <button class="botao botao--neutro botao--pequeno">Reenviar os <?= $falhas ?> que falharam</button>
            </form>
        <?php endif; ?>

        <?php if ($emCurso || $status === 'pausada'): ?>
            <form method="post" onsubmit="return confirm('Cancelar o envio? As mensagens que ainda não saíram serão descartadas.')">
                <input type="hidden" name="csrf" value="<?= token() ?>">
                <input type="hidden" name="acao" value="envio_cancelar">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="botao botao--perigo botao--pequeno">Cancelar envio</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="cartao">
    <h2>Destinatários</h2>
    <div class="filtros">
        <?php
        $abas = ['' => 'Todos', 'enviado' => 'Entregues', 'falha' => 'Falhas',
                 'pendente' => 'Na fila', 'suprimido' => 'Suprimidos'];
        foreach ($abas as $chave => $rotulo): ?>
            <a href="?p=envio&amp;id=<?= $id ?>&amp;situacao=<?= $chave ?>"
               class="botao botao--pequeno <?= $situacao === $chave ? '' : 'botao--neutro' ?>"><?= e($rotulo) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$itens): ?>
        <div class="vazio"><strong>Nada nesta situação</strong>Escolha outro filtro acima.</div>
    <?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead><tr><th>Nome</th><th>E-mail</th><th>Bairro</th><th>Situação</th><th>Quando</th><th>Detalhe</th></tr></thead>
            <tbody>
            <?php foreach ($itens as $item): ?>
                <tr>
                    <td><?= e($item['nome']) ?></td>
                    <td class="dado"><?= e($item['email']) ?></td>
                    <td><?= e($item['bairro'] ?? '—') ?></td>
                    <td><span class="etiqueta etq-<?= e($item['status']) ?>"><?= e($item['status']) ?></span></td>
                    <td class="dado"><?= $item['enviado_em'] ? e(date('d/m H:i', strtotime($item['enviado_em']))) : '—' ?></td>
                    <td class="dado" style="max-width:340px;word-break:break-word;color:var(--neutro)">
                        <?= e($item['ultimo_erro'] ?? '') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="ajuda" style="margin-top:12px">Mostrando no máximo 300 linhas por filtro.</p>
    <?php endif; ?>
</div>

<?php if (in_array($status, ['concluida', 'cancelada'], true)): ?>
<div class="cartao">
    <h2>Encerrar</h2>
    <form method="post" onsubmit="return confirm('Excluir este envio e todo o seu histórico de destinatários?')">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="envio_excluir">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button class="botao botao--perigo botao--pequeno">Excluir envio</button>
    </form>
</div>
<?php endif; ?>

<?php if ($emCurso): ?>
<script>
(function () {
    var id = <?= $id ?>;
    var barra = document.getElementById('barra');

    function atualizar() {
        fetch('?p=fila&id=' + id, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var total = Math.max(1, d.total);
                var faixa = function (n) { return (n * 100 / total).toFixed(2) + '%'; };

                barra.querySelector('.seg-enviado').style.width   = faixa(d.enviados);
                barra.querySelector('.seg-falha').style.width     = faixa(d.falhas);
                barra.querySelector('.seg-suprimido').style.width = faixa(d.suprimidos);
                barra.querySelector('.seg-pendente').style.width  = faixa(d.pendentes);

                document.getElementById('n-enviados').textContent   = d.enviados;
                document.getElementById('n-falhas').textContent     = d.falhas;
                document.getElementById('n-suprimidos').textContent = d.suprimidos;
                document.getElementById('n-pendentes').textContent  = d.pendentes;

                if (d.status !== 'na_fila' && d.status !== 'enviando') {
                    window.location.reload();
                }
            })
            .catch(function () { /* rede instável: tenta de novo no próximo ciclo */ });
    }

    setInterval(atualizar, 5000);
})();
</script>
<?php endif; ?>

<?php endif; ?>
