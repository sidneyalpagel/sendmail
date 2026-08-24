<div class="topo">
    <div>
        <span class="rotulo">Contatos</span>
        <h1>Bairros</h1>
        <p>Renomear um bairro atualiza todos os contatos dele. Se o novo nome já existir, os dois são fundidos.</p>
    </div>
</div>

<div class="cartao" style="max-width:720px">
    <h2>Cadastrar bairro</h2>
    <form method="post" class="filtros">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="bairro_criar">
        <input type="hidden" name="voltar" value="?p=bairros">
        <div class="campo campo--largo">
            <input type="text" name="nome" required placeholder="Nome do bairro" maxlength="120">
        </div>
        <button class="botao">Cadastrar</button>
    </form>
    <p class="ajuda" style="margin-top:10px">
        A importação de CSV também cadastra sozinha os bairros que ainda não existem.
        A caixa é padronizada automaticamente ("CENTRO" vira "Centro").
    </p>
</div>

<?php if (!$bairros): ?>
    <div class="vazio">
        <strong>Nenhum bairro cadastrado</strong>
        Cadastre acima ou importe contatos por CSV.
    </div>
<?php else: ?>
<div class="rolagem">
    <table class="tabela">
        <thead>
        <tr><th>Bairro</th><th>Contatos</th><th>Aptos a receber</th><th style="width:340px"></th></tr>
        </thead>
        <tbody>
        <?php foreach ($bairros as $b): ?>
            <tr>
                <td><a href="?p=bairro_contatos&amp;id=<?= (int) $b['id'] ?>"><?= e($b['nome']) ?></a></td>
                <td class="dado"><?= (int) $b['contatos'] ?></td>
                <td class="dado"><?= (int) $b['aptos'] ?></td>
                <td>
                    <div class="acoes">
                        <form method="post" class="filtros" style="margin:0"
                              onsubmit="return confirm('Renomear o bairro <?= e($b['nome']) ?>? Os <?= (int) $b['contatos'] ?> contatos dele serão atualizados.')">
                            <input type="hidden" name="csrf" value="<?= token() ?>">
                            <input type="hidden" name="acao" value="bairro_renomear">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <input type="hidden" name="voltar" value="?p=bairros">
                            <input type="text" name="nome" value="<?= e($b['nome']) ?>" maxlength="120"
                                   style="width:180px">
                            <button class="botao botao--neutro botao--pequeno">Renomear</button>
                        </form>
                        <form method="post"
                              <?php if ((int) $b['contatos'] === 0): ?>
                                  onsubmit="return confirm('Excluir o bairro <?= e($b['nome']) ?>?')"
                              <?php endif; ?>>
                            <input type="hidden" name="csrf" value="<?= token() ?>">
                            <input type="hidden" name="acao" value="bairro_excluir">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <input type="hidden" name="voltar" value="?p=bairros">
                            <button class="botao botao--perigo botao--pequeno"
                                    <?php if ((int) $b['contatos'] > 0): ?>
                                        title="Há contatos neste bairro — abre a lista deles"
                                    <?php endif; ?>>Excluir</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
