<div class="topo">
    <div>
        <span class="rotulo">Auditoria</span>
        <h1>Registro de atividades</h1>
        <p>Quem fez o quê, quando e de qual endereço. Últimos 300 registros.</p>
    </div>
</div>

<div class="rolagem">
    <table class="tabela">
        <thead><tr><th>Quando</th><th>Operador</th><th>Ação</th><th>Alvo</th><th>Detalhe</th><th>Origem</th></tr></thead>
        <tbody>
        <?php foreach ($registros as $r): ?>
            <tr>
                <td class="dado"><?= e(date('d/m/Y H:i:s', strtotime($r['criado_em']))) ?></td>
                <td><?= e($r['operador_nome'] ?? 'sistema') ?></td>
                <td class="dado"><?= e($r['acao']) ?></td>
                <td class="dado"><?= e(trim(($r['entidade'] ?? '') . ' ' . ($r['entidade_id'] ?? ''))) ?: '—' ?></td>
                <td style="color:var(--neutro)"><?= e($r['detalhe'] ?? '') ?></td>
                <td class="dado"><?= e($r['ip'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
