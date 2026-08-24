<div class="topo">
    <div>
        <span class="rotulo">Modelos</span>
        <h1>Modelos de mensagem</h1>
        <p>Textos que você reaproveita ao criar um envio.</p>
    </div>
    <a href="?p=modelo" class="botao">Novo modelo</a>
</div>

<?php if (!$modelos): ?>
    <div class="vazio">
        <strong>Nenhum modelo cadastrado</strong>
        Crie um modelo para não reescrever a mesma mensagem toda vez.
    </div>
<?php else: ?>
<div class="rolagem">
    <table class="tabela">
        <thead><tr><th>Nome</th><th>Assunto</th><th>Situação</th><th>Atualizado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($modelos as $m): ?>
            <tr>
                <td><a href="?p=modelo&amp;id=<?= (int) $m['id'] ?>"><?= e($m['nome']) ?></a></td>
                <td><?= e($m['assunto']) ?></td>
                <td>
                    <span class="etiqueta <?= (int) $m['ativo'] === 1 ? 'etq-concluida' : 'etq-suprimido' ?>">
                        <?= (int) $m['ativo'] === 1 ? 'ativo' : 'inativo' ?>
                    </span>
                </td>
                <td class="dado"><?= e(date('d/m/Y H:i', strtotime($m['atualizado_em']))) ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Excluir este modelo?')">
                        <input type="hidden" name="csrf" value="<?= token() ?>">
                        <input type="hidden" name="acao" value="modelo_excluir">
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="botao botao--neutro botao--pequeno">Excluir</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
