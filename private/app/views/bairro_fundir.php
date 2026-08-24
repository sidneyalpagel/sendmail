<div class="topo">
    <div>
        <span class="rotulo">Bairros</span>
        <h1>Fundir: <?= e($bairro['nome']) ?></h1>
        <p>Os contatos deste bairro passam para o bairro escolhido, e este deixa de existir.</p>
    </div>
    <a href="?p=bairros" class="botao botao--neutro">Voltar</a>
</div>

<div class="cartao" style="max-width:720px">
    <div class="aviso aviso--erro" style="margin-bottom:16px">
        O bairro <strong><?= e($bairro['nome']) ?></strong> deixará de existir.
        <?= $totalMoradores === 1
            ? 'O 1 contato que tem'
            : "Os {$totalMoradores} contatos que têm" ?>
        este bairro como endereço <?= $totalMoradores === 1 ? 'passa' : 'passam' ?>
        automaticamente para o bairro de destino. A ação fica registrada na auditoria.
    </div>

    <form method="post" id="form-fundir">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="bairro_fundir">
        <input type="hidden" name="id" value="<?= (int) $bairro['id'] ?>">
        <input type="hidden" name="voltar" value="?p=bairro_fundir&id=<?= (int) $bairro['id'] ?>">

        <div class="campo">
            <label for="destino_id">Fundir com o bairro</label>
            <select id="destino_id" name="destino_id" required>
                <option value="">— escolha o bairro de destino —</option>
                <?php foreach ($destinos as $d): ?>
                    <option value="<?= (int) $d['id'] ?>">
                        <?= e($d['nome']) ?> — <?= (int) $d['contatos'] ?> contatos
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="dica">
                Ao concluir, o destino passa a ter os contatos dos dois bairros.
            </span>
        </div>

        <div class="acoes" style="margin-top:16px">
            <button class="botao botao--perigo">Fundir bairros</button>
            <a href="?p=bairros" class="botao botao--neutro">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.getElementById('form-fundir').addEventListener('submit', function (evento) {
    var seletor = document.getElementById('destino_id');
    if (!seletor.value) { return; }
    var destino = seletor.options[seletor.selectedIndex].text.split('—')[0].trim();
    var confirmado = confirm(
        'Fundir <?= e($bairro['nome']) ?> em ' + destino + '?\n\n'
        + '<?= e($bairro['nome']) ?> deixará de existir e '
        + '<?= (int) $totalMoradores ?> contato(s) passarão para ' + destino + '.'
    );
    if (!confirmado) { evento.preventDefault(); }
});
</script>
