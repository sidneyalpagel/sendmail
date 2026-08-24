<div class="topo">
    <div>
        <span class="rotulo">Contatos</span>
        <h1>Importar lista por CSV</h1>
        <p>Cada linha vira um contato. Quem já existe é reconhecido pelo e-mail.</p>
    </div>
    <a href="?p=contatos" class="botao botao--neutro">Voltar</a>
</div>

<?php if ($resultado): ?>
<div class="cartao">
    <h2>Resultado da última importação</h2>
    <div class="grade grade--4">
        <div class="numero"><div class="numero__valor"><?= (int) $resultado['criados'] ?></div><div class="numero__rotulo">Criados</div></div>
        <div class="numero"><div class="numero__valor"><?= (int) $resultado['atualizados'] ?></div><div class="numero__rotulo">Atualizados</div></div>
        <div class="numero"><div class="numero__valor"><?= (int) $resultado['ignorados'] ?></div><div class="numero__rotulo">Ignorados</div></div>
        <div class="numero"><div class="numero__valor"><?= count($resultado['erros']) ?></div><div class="numero__rotulo">Avisos</div></div>
    </div>
    <?php if ($resultado['erros']): ?>
        <p class="ajuda" style="margin-top:16px">Linhas que não entraram:</p>
        <ul class="dado" style="font-family:var(--dado);font-size:13px;color:var(--neutro)">
            <?php foreach ($resultado['erros'] as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="cartao" style="max-width:720px">
    <h2>Enviar arquivo</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="importar">
        <input type="hidden" name="voltar" value="?p=importar">

        <div class="campo">
            <label for="arquivo">Arquivo CSV</label>
            <input type="file" id="arquivo" name="arquivo" accept=".csv,text/csv" required>
        </div>

        <div class="campo">
            <label for="separador">Separador de colunas</label>
            <select id="separador" name="separador">
                <option value=";">Ponto e vírgula (padrão do Excel em português)</option>
                <option value=",">Vírgula</option>
            </select>
        </div>

        <div class="opcao">
            <input type="checkbox" id="atualizar" name="atualizar" value="1" checked>
            <label for="atualizar">Atualizar os dados de quem já está cadastrado</label>
        </div>

        <button class="botao" style="margin-top:12px">Importar</button>
    </form>
</div>

<div class="cartao" style="max-width:720px">
    <h2>Formato esperado</h2>
    <p class="ajuda">
        A primeira linha precisa ser o cabeçalho. Só a coluna <strong>email</strong> é obrigatória;
        as demais são opcionais e podem vir em qualquer ordem.
    </p>
<pre class="dado" style="background:#f7f9f8;border:1px solid var(--linha);border-radius:3px;padding:14px;overflow-x:auto;font-size:13px">nome;email;bairro;telefone
Maria da Silva;maria.silva@exemplo.com;Centro;(45) 99999-0000
João Pereira;joao.pereira@exemplo.com;São Cristóvão;(45) 98888-1111</pre>
    <p class="ajuda" style="margin-top:14px">
        Também são aceitos os cabeçalhos <span class="dado">documento</span> e
        <span class="dado">observacao</span>. Quem já pediu descadastro continua fora dos envios
        mesmo que apareça de novo no arquivo.
    </p>
</div>
