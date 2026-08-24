<?php $ed = $modelo !== null; ?>

<div class="topo">
    <div>
        <span class="rotulo"><?= $ed ? 'Editar modelo' : 'Novo modelo' ?></span>
        <h1><?= $ed ? e($modelo['nome']) : 'Criar modelo' ?></h1>
    </div>
    <a href="?p=modelos" class="botao botao--neutro">Voltar</a>
</div>

<form method="post" enctype="multipart/form-data">
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

        <?php if (!empty($anexos)): ?>
        <div class="rolagem" style="margin-bottom:14px">
            <table class="tabela">
                <thead><tr><th>Anexo</th><th>Tamanho</th><th>Remover</th></tr></thead>
                <tbody>
                <?php foreach ($anexos as $a): ?>
                    <tr>
                        <td><?= e($a['nome']) ?></td>
                        <td class="dado"><?= e(Anexos::legivel((int) $a['tamanho'])) ?></td>
                        <td><input type="checkbox" name="remover_anexo[]" value="<?= (int) $a['id'] ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="campo">
            <label for="anexos">Anexos do modelo</label>
            <input type="file" id="anexos" name="anexos[]" multiple
                   accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.csv,.txt">
            <span class="dica">
                Documentos e imagens, até <?= e(Anexos::legivel(Anexos::LIMITE_TOTAL)) ?> somados.
                Todo envio criado a partir deste modelo já nasce com estes anexos —
                dá para removê-los ou trocar no próprio envio.
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
