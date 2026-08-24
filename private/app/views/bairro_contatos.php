<div class="topo">
    <div>
        <span class="rotulo">Bairros</span>
        <h1><?= e($bairro['nome']) ?></h1>
        <p>
            <?= count($moradores) === 1
                ? '1 contato tem este bairro como endereço.'
                : count($moradores) . ' contatos têm este bairro como endereço.' ?>
            Enquanto houver contatos nele, o bairro não pode ser excluído.
        </p>
    </div>
    <a href="?p=bairros" class="botao botao--neutro">Voltar</a>
</div>

<div class="cartao" style="max-width:720px">
    <h2>Como resolver</h2>
    <p class="ajuda">
        <strong>Para mover todos de uma vez</strong>, renomeie o bairro abaixo — se o novo nome
        já existir no cadastro, os dois são fundidos e os contatos vão junto. Depois disso o
        bairro antigo deixa de existir. <strong>Para mover um por um</strong>, abra o contato
        na lista e troque o bairro dele.
    </p>
    <form method="post" class="filtros"
          onsubmit="return confirm('Renomear o bairro <?= e($bairro['nome']) ?>? Os <?= count($moradores) ?> contatos dele serão atualizados.')">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="bairro_renomear">
        <input type="hidden" name="id" value="<?= (int) $bairro['id'] ?>">
        <input type="hidden" name="voltar" value="?p=bairro_contatos&id=<?= (int) $bairro['id'] ?>">
        <div class="campo campo--largo">
            <input type="text" name="nome" value="<?= e($bairro['nome']) ?>" maxlength="120">
        </div>
        <button class="botao botao--neutro">Renomear / fundir</button>
    </form>
</div>

<?php if ($moradores): ?>
<div class="rolagem">
    <table class="tabela">
        <thead>
        <tr><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Situação</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($moradores as $c): ?>
            <tr>
                <td><a href="?p=contato&amp;id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></a></td>
                <td class="dado"><?= e($c['email']) ?></td>
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
                <td><a href="?p=contato&amp;id=<?= (int) $c['id'] ?>" class="botao botao--neutro botao--pequeno">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <div class="cartao" style="max-width:720px">
        <p class="ajuda">Não há mais contatos neste bairro — agora ele pode ser excluído.</p>
        <form method="post" onsubmit="return confirm('Excluir o bairro <?= e($bairro['nome']) ?>?')">
            <input type="hidden" name="csrf" value="<?= token() ?>">
            <input type="hidden" name="acao" value="bairro_excluir">
            <input type="hidden" name="id" value="<?= (int) $bairro['id'] ?>">
            <input type="hidden" name="voltar" value="?p=bairros">
            <button class="botao botao--perigo">Excluir bairro</button>
        </form>
    </div>
<?php endif; ?>
