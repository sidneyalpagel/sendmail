<?php $ed = $modelo !== null; ?>

<div class="topo">
    <div>
        <span class="rotulo"><?= $ed ? 'Editar modelo' : 'Novo modelo' ?></span>
        <h1><?= $ed ? e($modelo['nome']) : 'Criar modelo' ?></h1>
    </div>
    <a href="?p=modelos" class="botao botao--neutro">Voltar</a>
</div>

<form method="post">
    <input type="hidden" name="csrf" value="<?= token() ?>">
    <input type="hidden" name="acao" value="modelo_salvar">
    <input type="hidden" name="voltar" value="?p=modelo<?= $ed ? '&id=' . (int) $modelo['id'] : '' ?>">
    <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int) $modelo['id'] ?>"><?php endif; ?>

    <div class="cartao">
        <div class="linha-campos">
            <div class="campo">
                <label for="nome">Nome do modelo</label>
                <input type="text" id="nome" name="nome" required value="<?= e($modelo['nome'] ?? '') ?>"
                       placeholder="Ex.: Aviso de manutenção da rede">
            </div>
            <div class="campo">
                <label for="assunto">Assunto do e-mail</label>
                <input type="text" id="assunto" name="assunto" required value="<?= e($modelo['assunto'] ?? '') ?>">
            </div>
        </div>

        <div class="campo">
            <label for="corpo">Corpo da mensagem</label>
            <textarea id="corpo" name="corpo" required><?= e($modelo['corpo'] ?? '') ?></textarea>
            <span class="dica">
                Aceita HTML simples: &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;a href&gt;.
                O cabeçalho e o rodapé institucionais são acrescentados automaticamente.
            </span>
        </div>

        <div class="opcao">
            <input type="checkbox" id="ativo" name="ativo" value="1" <?= (!$ed || (int) $modelo['ativo'] === 1) ? 'checked' : '' ?>>
            <label for="ativo">Disponível ao criar um envio</label>
        </div>

        <button class="botao" style="margin-top:12px">Salvar modelo</button>
    </div>
</form>

<div class="cartao">
    <h2>Variáveis disponíveis</h2>
    <p class="ajuda">Escreva no texto e o sistema troca pelo dado de cada destinatário no momento do envio.</p>
    <table class="tabela">
        <tbody>
        <?php foreach (Mensagem::VARIAVEIS as $variavel => $descricao): ?>
            <tr><td class="dado" style="width:190px"><?= e($variavel) ?></td><td><?= e($descricao) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
