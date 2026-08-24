<div class="topo">
    <div>
        <span class="rotulo">Ajustes</span>
        <h1>Configuração do disparo</h1>
        <p>Cadência, conexão com o servidor de e-mail e quem pode operar o sistema.</p>
    </div>
</div>

<?php
$limiteDia  = limiteDiario();
$usadoHoje  = enviadosNaJanela();
$sobraHoje  = $limiteDia === 0 ? null : max(0, $limiteDia - $usadoHoje);
?>

<div class="cartao" style="max-width:720px">
    <h2>Consumo das últimas 24 horas</h2>
    <p class="ajuda">
        A janela é rolante: cada mensagem deixa de contar 24 horas depois de sair.
    </p>
    <div class="grade grade--2">
        <div class="numero">
            <div class="numero__valor"><?= number_format($usadoHoje, 0, ',', '.') ?></div>
            <div class="numero__rotulo">Enviadas na janela</div>
        </div>
        <div class="numero">
            <div class="numero__valor"><?= $sobraHoje === null ? '∞' : number_format($sobraHoje, 0, ',', '.') ?></div>
            <div class="numero__rotulo">Ainda cabem hoje</div>
        </div>
    </div>
    <?php if ($sobraHoje === 0): ?>
        <p class="ajuda" style="margin:14px 0 0">
            O teto foi atingido. A fila retoma sozinha conforme a janela desliza — a próxima vaga
            abre em <?= e(date('d/m/Y H:i', strtotime((string) proximaVaga()))) ?>.
        </p>
    <?php endif; ?>
</div>

<div class="cartao" style="max-width:720px">
    <h2>Cadência da fila</h2>
    <p class="ajuda">
        Dois limites atuam ao mesmo tempo. O <strong>diário</strong> protege a reputação do domínio
        — provedores como Gmail e Outlook avaliam volume e taxa de reclamação. O
        <strong>por minuto</strong> evita estourar a cota por remetente do servidor de e-mail
        num pico. O teto absoluto por minuto, definido no arquivo de configuração, é de
        <?= (int) config('fila.limite_por_minuto', 60) ?>.
    </p>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="ajustes_salvar">
        <input type="hidden" name="voltar" value="?p=ajustes">

        <div class="campo">
            <label for="envios_por_dia">Mensagens por dia</label>
            <input type="number" id="envios_por_dia" name="envios_por_dia" min="0" max="100000"
                   value="<?= e(parametro('envios_por_dia', '1000')) ?>">
            <span class="dica">
                Teto nas últimas 24 horas. Ao atingi-lo, a fila pausa e retoma sozinha —
                nenhuma mensagem é perdida. Use <strong>0</strong> para desligar o limite.
                Se o domínio ainda não envia volume, comece baixo (50 a 100) e suba ao longo
                de duas ou três semanas.
            </span>
        </div>

        <div class="linha-campos">
            <div class="campo">
                <label for="envios_por_minuto">Mensagens por minuto</label>
                <input type="number" id="envios_por_minuto" name="envios_por_minuto" min="1"
                       max="<?= (int) config('fila.limite_por_minuto', 60) ?>"
                       value="<?= e(parametro('envios_por_minuto', '20')) ?>">
            </div>
            <div class="campo">
                <label for="max_tentativas">Tentativas por endereço</label>
                <input type="number" id="max_tentativas" name="max_tentativas" min="1" max="5"
                       value="<?= e(parametro('max_tentativas', '3')) ?>">
                <span class="dica">Antes de marcar como falha definitiva.</span>
            </div>
        </div>

        <div class="opcao">
            <input type="checkbox" id="pausa_global" name="pausa_global" value="1"
                   <?= parametro('pausa_global', '0') === '1' ? 'checked' : '' ?>>
            <label for="pausa_global">
                Pausar toda a fila
                <span class="dica" style="margin-top:2px">
                    Freio de emergência: nenhum envio sai enquanto estiver marcado, em nenhuma campanha.
                </span>
            </label>
        </div>

        <button class="botao" style="margin-top:8px">Salvar ajustes</button>
    </form>
</div>

<div class="cartao" style="max-width:720px">
    <h2>Servidor de saída</h2>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="smtp_salvar">
        <input type="hidden" name="voltar" value="?p=ajustes">
        <div class="campo">
            <label for="smtp_host">Servidor SMTP</label>
            <input type="text" id="smtp_host" name="smtp_host"
                   value="<?= e(parametro('smtp_host', '') ?: (string) config('smtp.host')) ?>">
            <span class="dica">
                A porta (<?= (int) config('smtp.porta') ?>) e as credenciais ficam em
                <span class="dado">private/config.php</span>. Deixe vazio para voltar ao
                servidor definido no arquivo. Depois de trocar, use o teste abaixo.
            </span>
        </div>
        <button class="botao" style="margin-bottom:18px">Salvar servidor</button>
    </form>

    <table class="tabela">
        <tbody>
        <tr><td style="width:180px">Em uso</td><td class="dado"><?= e(parametro('smtp_host', '') ?: (string) config('smtp.host')) ?>:<?= (int) config('smtp.porta') ?></td></tr>
        <tr><td>Criptografia</td><td class="dado"><?= e(strtoupper((string) config('smtp.seguranca')) ?: 'nenhuma') ?></td></tr>
        <tr><td>Conta autenticada</td><td class="dado"><?= e((string) config('smtp.usuario')) ?></td></tr>
        <tr><td>Remetente exibido</td><td class="dado"><?= e((string) config('smtp.remetente_email')) ?></td></tr>
        <tr><td>Respostas vão para</td><td class="dado"><?= e((string) config('smtp.responder_para_email')) ?></td></tr>
        </tbody>
    </table>
    <form method="post" style="margin-top:14px">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="testar_smtp">
        <input type="hidden" name="voltar" value="?p=ajustes">
        <button class="botao botao--neutro">Testar conexão e autenticação</button>
    </form>
</div>

<div class="cartao" style="max-width:720px">
    <h2>Operadores</h2>
    <div class="rolagem" style="margin-bottom:18px">
        <table class="tabela">
            <thead><tr><th>Nome</th><th>Login</th><th>Papel</th><th>Último acesso</th></tr></thead>
            <tbody>
            <?php foreach ($operadores as $o): ?>
                <tr>
                    <td><?= e($o['nome']) ?></td>
                    <td class="dado"><?= e($o['login']) ?></td>
                    <td><span class="etiqueta <?= $o['papel'] === 'admin' ? 'etq-concluida' : 'etq-rascunho' ?>"><?= e($o['papel']) ?></span></td>
                    <td class="dado"><?= $o['ultimo_acesso'] ? e(date('d/m/Y H:i', strtotime($o['ultimo_acesso']))) : 'nunca' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= token() ?>">
        <input type="hidden" name="acao" value="operador_salvar">
        <input type="hidden" name="voltar" value="?p=ajustes">

        <div class="linha-campos">
            <div class="campo"><label for="op_nome">Nome</label><input type="text" id="op_nome" name="nome" required></div>
            <div class="campo"><label for="op_login">Login</label><input type="text" id="op_login" name="login" required></div>
        </div>
        <div class="linha-campos">
            <div class="campo"><label for="op_email">E-mail</label><input type="email" id="op_email" name="email" required></div>
            <div class="campo">
                <label for="op_papel">Papel</label>
                <select id="op_papel" name="papel">
                    <option value="operador">Operador — cria e dispara envios</option>
                    <option value="admin">Administrador — também mexe nos ajustes</option>
                </select>
            </div>
        </div>
        <div class="campo">
            <label for="op_senha">Senha inicial</label>
            <input type="password" id="op_senha" name="senha" required minlength="10" autocomplete="new-password">
            <span class="dica">Mínimo de 10 caracteres.</span>
        </div>
        <button class="botao">Cadastrar operador</button>
    </form>
</div>
