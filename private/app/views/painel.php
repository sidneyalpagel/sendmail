<div class="topo">
    <div>
        <span class="rotulo">Painel</span>
        <h1>Bom dia, <?= e(explode(' ', Auth::nome())[0]) ?></h1>
        <p>Aqui você vê o estado da lista e o que está saindo agora.</p>
    </div>
    <a href="?p=envio_novo" class="botao">Novo envio</a>
</div>

<div class="grade grade--4" style="margin-bottom:22px">
    <div class="numero">
        <div class="numero__valor"><?= number_format((int) ($resumo['aptos'] ?? 0), 0, ',', '.') ?></div>
        <div class="numero__rotulo">Aptos a receber</div>
    </div>
    <div class="numero">
        <div class="numero__valor"><?= number_format((int) ($resumo['bairros'] ?? 0), 0, ',', '.') ?></div>
        <div class="numero__rotulo">Bairros</div>
    </div>
    <div class="numero">
        <div class="numero__valor"><?= number_format((int) ($resumo['descadastrados'] ?? 0), 0, ',', '.') ?></div>
        <div class="numero__rotulo">Descadastrados</div>
    </div>
    <div class="numero">
        <div class="numero__valor"><?= number_format($naFila, 0, ',', '.') ?></div>
        <div class="numero__rotulo">Na fila agora</div>
    </div>
</div>

<?php $sobra = limiteDiario() === 0 ? null : restanteNaJanela(); ?>
<div class="grade grade--2" style="margin-bottom:22px">
    <div class="numero">
        <div class="numero__valor"><?= number_format(enviadosNaJanela(), 0, ',', '.') ?></div>
        <div class="numero__rotulo">Enviadas em 24h</div>
    </div>
    <div class="numero">
        <div class="numero__valor"><?= $sobra === null ? '∞' : number_format($sobra, 0, ',', '.') ?></div>
        <div class="numero__rotulo">Ainda cabem hoje</div>
    </div>
</div>

<?php if ($sobra !== null && $sobra === 0): ?>
    <div class="aviso aviso--erro">
        O teto de <?= number_format(limiteDiario(), 0, ',', '.') ?> mensagens em 24 horas foi
        atingido. A fila continua parada até <?= e(date('d/m/Y H:i', strtotime((string) proximaVaga()))) ?>,
        quando a janela abre a próxima vaga. Nada foi perdido — os envios pendentes retomam sozinhos.
    </div>
<?php endif; ?>

<?php if (parametro('pausa_global', '0') === '1'): ?>
    <div class="aviso aviso--erro">
        A fila está pausada globalmente. Nenhuma mensagem sai enquanto isso durar — reative em Ajustes.
    </div>
<?php endif; ?>

<?php if ($emAndamento): ?>
<div class="cartao">
    <h2>Em andamento</h2>
    <div class="rolagem">
        <table class="tabela">
            <thead>
            <tr><th>Envio</th><th>Situação</th><th>Progresso</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($emAndamento as $c):
                $total = max(1, (int) $c['total']);
                $feito = (int) $c['enviados'] + (int) $c['falhas'] + (int) $c['suprimidos'];
            ?>
                <tr>
                    <td><strong><?= e($c['nome']) ?></strong><br><span class="dado"><?= e(Campanhas::descricaoEscopo($c)) ?></span></td>
                    <td><span class="etiqueta etq-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                    <td class="dado"><?= $feito ?> / <?= (int) $c['total'] ?></td>
                    <td><a href="?p=envio&amp;id=<?= (int) $c['id'] ?>" class="botao botao--neutro botao--pequeno">Acompanhar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="cartao">
    <h2>Últimos envios</h2>
    <?php if (!$recentes): ?>
        <div class="vazio">
            <strong>Nenhum envio ainda</strong>
            Comece cadastrando os contatos e criando o primeiro envio.
        </div>
    <?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
            <tr><th>Envio</th><th>Público</th><th>Situação</th><th>Enviados</th><th>Falhas</th><th>Quando</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recentes as $c): ?>
                <tr>
                    <td><a href="?p=envio&amp;id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></a></td>
                    <td class="dado"><?= e(Campanhas::descricaoEscopo($c)) ?></td>
                    <td><span class="etiqueta etq-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                    <td class="dado"><?= (int) $c['enviados'] ?></td>
                    <td class="dado"><?= (int) $c['falhas'] ?></td>
                    <td class="dado"><?= e(date('d/m/Y H:i', strtotime($c['criado_em']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
