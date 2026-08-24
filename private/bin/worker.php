<?php
declare(strict_types=1);

/**
 * Processador da fila de envio.
 *
 * Executado pelo cron a cada minuto. Trabalha por um ciclo fixo (padrão 50s)
 * e encerra, deixando a próxima execução continuar de onde parou. Um bloqueio
 * de arquivo garante que nunca haja dois processos enviando ao mesmo tempo.
 *
 *   * * * * * php /caminho/private/bin/worker.php >> /caminho/private/logs/cron.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só roda pela linha de comando.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

// ---------------------------------------------------------------------
// Exclusão mútua
// ---------------------------------------------------------------------
if (!is_dir(LOGS)) {
    mkdir(LOGS, 0750, true);
}
$trava = fopen(LOGS . '/worker.lock', 'c');
if (!$trava || !flock($trava, LOCK_EX | LOCK_NB)) {
    exit(0); // já existe um worker rodando
}

$inicio        = time();
$duracaoCiclo  = (int) config('fila.duracao_ciclo', 50);
$maxTentativas = max(1, (int) parametro('max_tentativas', '3'));
$esperaRetry   = max(1, (int) config('fila.espera_retentativa', 10));

$porMinuto = min(
    (int) config('fila.limite_por_minuto', 60),
    max(1, (int) parametro('envios_por_minuto', '20'))
);
$intervalo = 60 / $porMinuto;   // segundos entre mensagens

if (parametro('pausa_global', '0') === '1') {
    exit(0);
}

// ---------------------------------------------------------------------
// Recuperação de itens presos em "enviando"
// ---------------------------------------------------------------------
// Se um ciclo anterior morreu no meio de um envio (queda de energia, kill),
// a linha capturada fica em "enviando" para sempre: nenhuma consulta a
// retoma e a campanha nunca conclui. Com a trava em mãos não há outro
// processo enviando, então é seguro recolocá-la na fila. Conta como
// tentativa: se for a própria mensagem que derruba o processo, ela desiste
// ao atingir o máximo em vez de repetir o estrago indefinidamente.
$presos = Db::executar(
    'UPDATE fila SET status = "pendente", tentativas = tentativas + 1,
            ultimo_erro = "processo interrompido durante o envio"
      WHERE status = "enviando"'
)->rowCount();
if ($presos > 0) {
    registrar("recuperados {$presos} itens presos em \"enviando\" de um ciclo interrompido");
}

$correio    = null;
$processados = 0;

try {
    while ((time() - $inicio) < $duracaoCiclo) {

        if (parametro('pausa_global', '0') === '1') {
            registrar('pausa global ativada; encerrando ciclo');
            break;
        }

        // Teto das últimas 24 horas. Recalculado a cada volta: a janela é
        // rolante e outros ciclos podem ter enviado nesse meio-tempo.
        if (restanteNaJanela() <= 0) {
            if ($processados === 0) {
                $vaga = proximaVaga();
                registrar(
                    'teto diário atingido (' . limiteDiario() . ' em 24h)'
                    . ($vaga ? '; próxima vaga em ' . $vaga : '')
                );
            }
            break;
        }

        $item = proximoItem();
        if (!$item) {
            break; // fila vazia
        }

        // Conexão SMTP aberta sob demanda e reaproveitada no ciclo inteiro.
        if ($correio === null) {
            $correio = new Correio(true);
        }

        $marcoEnvio = microtime(true);
        entregar($correio, $item, $maxTentativas, $esperaRetry);
        $processados++;

        Campanhas::recontar((int) $item['campanha_id']);
        Campanhas::concluirSePronta((int) $item['campanha_id']);

        // Cadência: respeita o teto de mensagens por minuto.
        $gasto = microtime(true) - $marcoEnvio;
        $pausa = $intervalo - $gasto;
        if ($pausa > 0 && (time() - $inicio) < $duracaoCiclo) {
            usleep((int) ($pausa * 1_000_000));
        }
    }
} catch (Throwable $erro) {
    registrar('ERRO no ciclo: ' . $erro->getMessage());
} finally {
    if ($correio !== null) {
        $correio->encerrar();
    }
    if ($processados > 0) {
        registrar("ciclo encerrado: {$processados} mensagens processadas");
    }
    flock($trava, LOCK_UN);
    fclose($trava);
}

exit(0);

// =====================================================================
// Funções do worker
// =====================================================================

/**
 * Retira o próximo item da fila, marcando-o como "enviando" de forma
 * atômica para que dois processos jamais peguem a mesma linha.
 */
function proximoItem(): ?array
{
    for ($tentativa = 0; $tentativa < 5; $tentativa++) {
        $candidato = Db::primeiro(
            'SELECT f.*, c.assunto, c.corpo, c.nome AS campanha_nome
               FROM fila f
               JOIN campanhas c ON c.id = f.campanha_id
              WHERE f.status = "pendente"
                AND (f.liberar_em IS NULL OR f.liberar_em <= NOW())
                AND c.status IN ("na_fila","enviando")
              ORDER BY f.id
              LIMIT 1'
        );
        if (!$candidato) {
            return null;
        }

        $capturado = Db::executar(
            'UPDATE fila SET status = "enviando" WHERE id = ? AND status = "pendente"',
            [$candidato['id']]
        )->rowCount();

        if ($capturado === 1) {
            Db::executar(
                'UPDATE campanhas SET status = "enviando" WHERE id = ? AND status = "na_fila"',
                [$candidato['campanha_id']]
            );
            return $candidato;
        }
        // Outro processo levou a linha; tenta a próxima.
    }
    return null;
}

/** Entrega uma mensagem e grava o desfecho na fila. */
function entregar(Correio $correio, array $item, int $maxTentativas, int $esperaRetry): void
{
    // Recusa de última hora: o contato pode ter se descadastrado
    // depois que a campanha foi liberada.
    if (!empty($item['contato_id'])) {
        $situacao = Db::primeiro(
            'SELECT ativo, opt_out FROM contatos WHERE id = ?',
            [$item['contato_id']]
        );
        if ($situacao && ((int) $situacao['opt_out'] === 1 || (int) $situacao['ativo'] === 0)) {
            Db::executar(
                'UPDATE fila SET status = "suprimido", ultimo_erro = ? WHERE id = ?',
                ['contato descadastrado ou inativo no momento do envio', $item['id']]
            );
            return;
        }
    }

    $destinatario = [
        'nome'       => $item['nome'],
        'email'      => $item['email'],
        'bairro'     => $item['bairro'],
        'contato_id' => $item['contato_id'],
    ];

    try {
        $messageId = $correio->enviar($destinatario, $item['assunto'], $item['corpo']);
        Db::executar(
            'UPDATE fila SET status = "enviado", message_id = ?, enviado_em = NOW(),
                    tentativas = tentativas + 1, ultimo_erro = NULL
              WHERE id = ?',
            [$messageId, $item['id']]
        );
    } catch (Throwable $erro) {
        $tentativas = (int) $item['tentativas'] + 1;
        $mensagem   = mb_substr(trim($erro->getMessage()), 0, 500);

        if ($tentativas >= $maxTentativas) {
            Db::executar(
                'UPDATE fila SET status = "falha", tentativas = ?, ultimo_erro = ? WHERE id = ?',
                [$tentativas, $mensagem, $item['id']]
            );
            registrar("FALHA definitiva para {$item['email']} (campanha {$item['campanha_id']}): {$mensagem}");
        } else {
            Db::executar(
                'UPDATE fila SET status = "pendente", tentativas = ?, ultimo_erro = ?,
                        liberar_em = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                  WHERE id = ?',
                [$tentativas, $mensagem, $esperaRetry, $item['id']]
            );
            registrar("retentativa {$tentativas} para {$item['email']}: {$mensagem}");
        }
    }
}
