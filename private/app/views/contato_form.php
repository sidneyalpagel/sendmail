<?php $ed = $contato !== null; ?>

<div class="topo">
    <div>
        <span class="rotulo"><?= $ed ? 'Editar contato' : 'Novo contato' ?></span>
        <h1><?= $ed ? e($contato['nome']) : 'Cadastrar contato' ?></h1>
    </div>
    <a href="?p=contatos" class="botao botao--neutro">Voltar</a>
</div>

<div class="cartao" style="max-width:720px">
    <form method="post">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="contato_salvar">
        <input type="hidden" name="voltar" value="?p=contato<?= $ed ? '&id=' . (int) $contato['id'] : '' ?>">
        <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int) $contato['id'] ?>"><?php endif; ?>

        <div class="campo">
            <label for="nome">Nome completo</label>
            <input type="text" id="nome" name="nome" required value="<?= e($contato['nome'] ?? '') ?>">
        </div>

        <div class="linha-campos">
            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required value="<?= e($contato['email'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="bairro">Bairro</label>
                <?php
                $bairroAtual = (string) ($contato['bairro'] ?? '');
                $nomes = array_column($bairros, 'nome');
                ?>
                <select id="bairro" name="bairro">
                    <option value="">— sem bairro —</option>
                    <?php foreach ($nomes as $nome): ?>
                        <option value="<?= e($nome) ?>" <?= $bairroAtual === $nome ? 'selected' : '' ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                    <?php if ($bairroAtual !== '' && !in_array($bairroAtual, $nomes, true)): ?>
                        <option value="<?= e($bairroAtual) ?>" selected><?= e($bairroAtual) ?> (fora do cadastro)</option>
                    <?php endif; ?>
                </select>
                <span class="dica">
                    Usado para enviar só para um bairro.
                    Faltando algum? Cadastre na tela <a href="?p=bairros">Bairros</a>.
                </span>
            </div>
        </div>

        <div class="linha-campos">
            <div class="campo">
                <label for="telefone">Telefone</label>
                <input type="text" id="telefone" name="telefone" value="<?= e($contato['telefone'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="documento">Documento ou matrícula</label>
                <input type="text" id="documento" name="documento" value="<?= e($contato['documento'] ?? '') ?>">
            </div>
        </div>

        <div class="campo">
            <label for="observacao">Observação</label>
            <input type="text" id="observacao" name="observacao" value="<?= e($contato['observacao'] ?? '') ?>">
        </div>

        <div class="opcao">
            <input type="checkbox" id="ativo" name="ativo" value="1" <?= (!$ed || (int) $contato['ativo'] === 1) ? 'checked' : '' ?>>
            <label for="ativo">Ativo — recebe os envios</label>
        </div>

        <?php if ($ed && (int) $contato['opt_out'] === 1): ?>
            <div class="aviso aviso--erro">
                Este contato pediu para não receber mais mensagens em
                <?= e(date('d/m/Y', strtotime($contato['opt_out_em'] ?? 'now'))) ?>.
                Ele fica de fora de qualquer envio até ser reativado na listagem.
            </div>
        <?php endif; ?>

        <div class="acoes" style="margin-top:20px">
            <button class="botao">Salvar contato</button>
            <?php if ($ed): ?>
                <form method="post" onsubmit="return confirm('Excluir definitivamente este contato?')">
                    <input type="hidden" name="csrf" value="<?= token() ?>">
                    <input type="hidden" name="acao" value="contato_excluir">
                    <input type="hidden" name="id" value="<?= (int) $contato['id'] ?>">
                    <button class="botao botao--perigo">Excluir</button>
                </form>
            <?php endif; ?>
        </div>
    </form>
</div>
