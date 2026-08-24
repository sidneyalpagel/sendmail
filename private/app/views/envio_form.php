<?php
$ed     = $campanha !== null;
$escopo = $campanha['escopo'] ?? 'bairro';
$valor  = $campanha['escopo_valor'] ?? '';
?>

<div class="topo">
    <div>
        <span class="rotulo"><?= $ed ? 'Editar rascunho' : 'Novo envio' ?></span>
        <h1><?= $ed ? e($campanha['nome']) : 'Preparar envio' ?></h1>
        <p>Nada sai até você liberar. O rascunho pode ser revisto quantas vezes quiser.</p>
    </div>
    <a href="?p=envios" class="botao botao--neutro">Voltar</a>
</div>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= token() ?>">
    <input type="hidden" name="acao" value="envio_salvar">
    <input type="hidden" name="voltar" value="?p=envio_novo<?= $ed ? '&id=' . (int) $campanha['id'] : '' ?>">
    <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int) $campanha['id'] ?>"><?php endif; ?>

    <div class="cartao">
        <h2>1. Quem vai receber</h2>

        <div class="opcao">
            <input type="radio" id="e_contato" name="escopo" value="contato" <?= $escopo === 'contato' ? 'checked' : '' ?>>
            <label for="e_contato">Um contato específico</label>
        </div>
        <div class="campo" style="margin-left:26px">
            <input type="text" name="escopo_valor_contato" id="busca_contato"
                   placeholder="digite o e-mail exato do destinatário"
                   value="<?= $escopo === 'contato' ? e((string) (Contatos::buscar((int) $valor)['email'] ?? '')) : '' ?>">
            <span class="dica">O contato precisa estar cadastrado e apto a receber.</span>
        </div>

        <div class="opcao">
            <input type="radio" id="e_bairro" name="escopo" value="bairro" <?= $escopo === 'bairro' ? 'checked' : '' ?>>
            <label for="e_bairro">Todos os contatos de um bairro</label>
        </div>
        <div class="campo" style="margin-left:26px">
            <select name="escopo_valor_bairro">
                <option value="">— escolha o bairro —</option>
                <?php foreach ($bairros as $b): ?>
                    <option value="<?= e($b['bairro']) ?>" <?= ($escopo === 'bairro' && $valor === $b['bairro']) ? 'selected' : '' ?>>
                        <?= e($b['bairro']) ?> — <?= (int) $b['aptos'] ?> aptos
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="opcao">
            <input type="radio" id="e_todos" name="escopo" value="todos" <?= $escopo === 'todos' ? 'checked' : '' ?>>
            <label for="e_todos">
                Toda a lista
                <span class="dica" style="margin-top:2px">
                    <?= number_format($totalGeral, 0, ',', '.') ?> destinatários aptos neste momento.
                </span>
            </label>
        </div>
    </div>

    <div class="cartao">
        <h2>2. A mensagem</h2>

        <div class="linha-campos">
            <div class="campo">
                <label for="nome">Nome do envio</label>
                <input type="text" id="nome" name="nome" required value="<?= e($campanha['nome'] ?? '') ?>"
                       placeholder="Ex.: Manutenção do Wi-Fi — bairro Centro">
                <span class="dica">Só aparece aqui no sistema, para você localizar depois.</span>
            </div>
            <div class="campo">
                <label for="modelo_id">Partir de um modelo</label>
                <select id="modelo_id" name="modelo_id">
                    <option value="">— escrever do zero —</option>
                    <?php foreach ($modelos as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"
                                data-assunto="<?= e($m['assunto']) ?>"
                                data-corpo="<?= e($m['corpo']) ?>"
                                data-anexos="<?= e(implode(', ', $anexosDeModelos[(int) $m['id']] ?? [])) ?>"
                                <?= (int) ($campanha['modelo_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                            <?= e($m['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="campo">
            <label for="assunto">Assunto</label>
            <input type="text" id="assunto" name="assunto" required value="<?= e($campanha['assunto'] ?? '') ?>">
        </div>

        <div class="campo">
            <label for="corpo">Corpo da mensagem</label>
            <textarea id="corpo" name="corpo" required><?= e($campanha['corpo'] ?? '') ?></textarea>
            <span class="dica">
                Variáveis:
                <?php foreach (array_keys(Mensagem::VARIAVEIS) as $v): ?>
                    <span class="dado"><?= e($v) ?></span>&nbsp;
                <?php endforeach; ?>
            </span>
        </div>

    </div>

    <div class="cartao">
        <h2>3. Anexos (opcional)</h2>

        <?php if (!empty($anexos)): ?>
        <div class="rolagem" style="margin-bottom:14px">
            <table class="tabela">
                <thead><tr><th>Arquivo</th><th>Tamanho</th><th>Remover</th></tr></thead>
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

        <?php if (!$ed): ?>
            <p class="ajuda" id="anexos_do_modelo" style="display:none;margin:0 0 14px"></p>
        <?php endif; ?>

        <div class="campo">
            <label for="anexos">Adicionar arquivos</label>
            <input type="file" id="anexos" name="anexos[]" multiple
                   accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.csv,.txt">
            <span class="dica">
                Documentos e imagens, até <?= e(Anexos::legivel(Anexos::LIMITE_TOTAL)) ?> somados por envio —
                seguem em todas as mensagens desta campanha. Para remover um anexo já
                enviado, marque "Remover" e salve. Um envio novo criado a partir de um
                modelo com anexos já nasce com eles.
            </span>
        </div>

        <button class="botao">Salvar rascunho</button>
    </div>
</form>

<script>
(function () {
    // Ao trocar de modelo, preenche assunto e corpo se ainda estiverem vazios.
    var seletor = document.getElementById('modelo_id');
    var assunto = document.getElementById('assunto');
    var corpo   = document.getElementById('corpo');

    seletor.addEventListener('change', function () {
        var opcao = seletor.options[seletor.selectedIndex];
        if (!opcao.value) { return; }
        var vazio = assunto.value.trim() === '' && corpo.value.trim() === '';
        if (vazio || confirm('Substituir o assunto e o texto atuais pelo conteúdo do modelo?')) {
            assunto.value = opcao.dataset.assunto || '';
            corpo.value   = opcao.dataset.corpo || '';
        }
    });

    // Mostra os anexos que o modelo escolhido vai trazer para o envio.
    // A cópia em si acontece ao salvar o rascunho.
    var avisoAnexos = document.getElementById('anexos_do_modelo');
    function mostrarAnexosDoModelo() {
        if (!avisoAnexos) { return; }
        var opcao = seletor.options[seletor.selectedIndex];
        var lista = (opcao && opcao.value) ? (opcao.dataset.anexos || '') : '';
        avisoAnexos.style.display = lista ? '' : 'none';
        if (lista) {
            avisoAnexos.innerHTML = '<strong>Anexos do modelo</strong> — entram neste envio ao salvar o rascunho: ';
            avisoAnexos.appendChild(document.createTextNode(lista));
        }
    }
    seletor.addEventListener('change', mostrarAnexosDoModelo);
    mostrarAnexosDoModelo();

    // Marca o escopo correspondente quando o operador mexe no campo.
    document.getElementById('busca_contato').addEventListener('input', function () {
        document.getElementById('e_contato').checked = true;
    });
    document.querySelector('[name=escopo_valor_bairro]').addEventListener('change', function () {
        document.getElementById('e_bairro').checked = true;
    });

    // Consolida o valor do escopo em um único campo antes de enviar.
    document.querySelector('form').addEventListener('submit', function (evento) {
        var escolhido = document.querySelector('[name=escopo]:checked');
        if (!escolhido) {
            evento.preventDefault();
            alert('Escolha quem vai receber a mensagem.');
            return;
        }
        var valor = '';
        if (escolhido.value === 'bairro') {
            valor = document.querySelector('[name=escopo_valor_bairro]').value;
        } else if (escolhido.value === 'contato') {
            valor = document.getElementById('busca_contato').value.trim();
        }
        var oculto = document.createElement('input');
        oculto.type = 'hidden';
        oculto.name = 'escopo_valor';
        oculto.value = valor;
        this.appendChild(oculto);
    });
})();
</script>
