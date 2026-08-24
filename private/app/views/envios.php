<div class="topo">
    <div>
        <span class="rotulo">Envios</span>
        <h1>Histórico de envios</h1>
        <p>Todo disparo fica registrado, com quem recebeu e quem falhou.</p>
    </div>
    <a href="?p=envio_novo" class="botao">Novo envio</a>
</div>

<?php if (!$campanhas): ?>
    <div class="vazio">
        <strong>Nenhum envio criado</strong>
        O primeiro passo é ter contatos na lista; depois é só preparar a mensagem.
    </div>
<?php else: ?>
<div class="rolagem">
    <table class="tabela">
        <thead>
        <tr><th>#</th><th>Envio</th><th>Público</th><th>Situação</th>
            <th>Entregues</th><th>Falhas</th><th>Total</th><th>Criado</th></tr>
        </thead>
        <tbody>
        <?php foreach ($campanhas as $c): ?>
            <tr>
                <td class="dado"><?= (int) $c['id'] ?></td>
                <td><a href="?p=envio&amp;id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></a><br>
                    <span class="dado" style="color:var(--neutro)"><?= e($c['assunto']) ?></span></td>
                <td><?= e(Campanhas::descricaoEscopo($c)) ?></td>
                <td><span class="etiqueta etq-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                <td class="dado"><?= (int) $c['enviados'] ?></td>
                <td class="dado"><?= (int) $c['falhas'] ?></td>
                <td class="dado"><?= (int) $c['total'] ?></td>
                <td class="dado"><?= e(date('d/m/Y H:i', strtotime($c['criado_em']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
