<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/private/app/bootstrap.php';

$pagina = (string) ($_GET['p'] ?? 'painel');
$acao   = (string) ($_POST['acao'] ?? '');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// =====================================================================
// Entrada e saída
// =====================================================================
if ($pagina === 'login') {
    if ($metodo === 'POST') {
        conferirToken();
        $login = trim((string) $_POST['login']);
        if (Auth::bloqueadoTemporariamente($login)) {
            Auditoria::registrar('login_bloqueado', 'operador', $login);
            aviso('Muitas tentativas seguidas. Aguarde ' . Auth::BLOQUEIO_MINUTOS
                . ' minutos e tente novamente.', 'erro');
            irPara('?p=login');
        }
        if (Auth::entrar($login, (string) $_POST['senha'])) {
            irPara('?p=painel');
        }
        aviso('Login ou senha incorretos.', 'erro');
        irPara('?p=login');
    }
    if (Auth::operador()) {
        irPara('?p=painel');
    }
    incluirView('login');
    exit;
}

if ($pagina === 'sair') {
    Auth::sair();
    session_start();
    aviso('Você saiu do sistema.');
    irPara('?p=login');
}

Auth::exigir();

// =====================================================================
// Ações (POST)
// =====================================================================
if ($metodo === 'POST') {
    conferirToken();
    try {
        switch ($acao) {

            // ---------------------------------------------------- contatos
            case 'contato_salvar':
                $id = (int) ($_POST['id'] ?? 0);
                Contatos::salvar($_POST, $id ?: null);
                aviso($id ? 'Contato atualizado.' : 'Contato cadastrado.');
                irPara('?p=contatos');

            case 'contato_excluir':
                Contatos::excluir((int) $_POST['id']);
                aviso('Contato excluído.');
                irPara('?p=contatos');

            case 'contato_descadastrar':
                Contatos::descadastrar((int) $_POST['id']);
                aviso('Contato marcado como descadastrado. Ele não receberá novos envios.');
                irPara('?p=contatos');

            case 'contato_recadastrar':
                Contatos::recadastrar((int) $_POST['id']);
                aviso('Contato reativado para recebimento.');
                irPara('?p=contatos');

            case 'importar':
                if (empty($_FILES['arquivo']['tmp_name']) || !is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
                    throw new RuntimeException('Escolha um arquivo CSV para enviar.');
                }
                $r = Contatos::importarCsv(
                    $_FILES['arquivo']['tmp_name'],
                    ($_POST['separador'] ?? ';') === ',' ? ',' : ';',
                    !empty($_POST['atualizar'])
                );
                $_SESSION['resultado_importacao'] = $r;
                aviso("Importação concluída: {$r['criados']} novos, {$r['atualizados']} atualizados, {$r['ignorados']} ignorados.");
                irPara('?p=importar');

            // ---------------------------------------------------- bairros
            case 'bairro_criar':
                $nome = Bairros::criar((string) ($_POST['nome'] ?? ''));
                aviso('Bairro ' . $nome . ' cadastrado.');
                irPara('?p=bairros');

            case 'bairro_renomear':
                Bairros::renomear((int) $_POST['id'], (string) ($_POST['nome'] ?? ''));
                aviso('Bairro atualizado. Os contatos dele foram ajustados.');
                irPara('?p=bairros');

            case 'bairro_fundir':
                $id = (int) $_POST['id'];
                $bairro  = Bairros::buscar($id);
                $destino = Bairros::buscar((int) ($_POST['destino_id'] ?? 0));
                if (!$bairro || !$destino) {
                    throw new RuntimeException('Escolha o bairro de destino.');
                }
                $movidos = Bairros::fundir($id, (int) $destino['id']);
                aviso("Bairro {$bairro['nome']} fundido em {$destino['nome']}: "
                    . ($movidos === 1 ? '1 contato movido.' : "{$movidos} contatos movidos."));
                irPara('?p=bairros');

            case 'bairro_excluir':
                $id = (int) $_POST['id'];
                $bairro = Bairros::buscar($id);
                if (!$bairro) {
                    throw new RuntimeException('Bairro não encontrado.');
                }
                $moradores = count(Bairros::contatosDoBairro($id));
                if ($moradores > 0) {
                    aviso("O bairro {$bairro['nome']} não pode ser excluído: há {$moradores} "
                        . ($moradores === 1 ? 'contato cadastrado' : 'contatos cadastrados')
                        . ' nele. Veja a lista abaixo e mova-os antes.', 'erro');
                    irPara('?p=bairro_contatos&id=' . $id);
                }
                Bairros::excluir($id);
                aviso('Bairro ' . $bairro['nome'] . ' excluído.');
                irPara('?p=bairros');

            // ---------------------------------------------------- modelos
            case 'modelo_salvar':
                $id = (int) ($_POST['id'] ?? 0);
                $modeloId = Modelos::salvar($_POST, $id ?: null);
                foreach ((array) ($_POST['remover_anexo'] ?? []) as $anexoId) {
                    Anexos::removerDoModelo((int) $anexoId, $modeloId);
                }
                if (!empty($_FILES['anexos']['name'][0])) {
                    foreach ((array) $_FILES['anexos']['name'] as $i => $nomeArquivo) {
                        if (($_FILES['anexos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        Anexos::adicionarAoModelo($modeloId, [
                            'name'     => $nomeArquivo,
                            'tmp_name' => $_FILES['anexos']['tmp_name'][$i] ?? '',
                            'size'     => $_FILES['anexos']['size'][$i] ?? 0,
                            'error'    => $_FILES['anexos']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        ]);
                    }
                }
                aviso($id ? 'Modelo atualizado.' : 'Modelo criado.');
                irPara('?p=modelos');

            case 'modelo_excluir':
                Modelos::excluir((int) $_POST['id']);
                aviso('Modelo excluído.');
                irPara('?p=modelos');

            // ---------------------------------------------------- envios
            case 'envio_salvar':
                $id = (int) ($_POST['id'] ?? 0);
                $novo = Campanhas::salvarRascunho($_POST, $id ?: null);
                // Envio novo criado a partir de um modelo herda os anexos dele.
                if (!$id && !empty($_POST['modelo_id'])) {
                    Anexos::copiarModeloParaCampanha((int) $_POST['modelo_id'], $novo);
                }
                foreach ((array) ($_POST['remover_anexo'] ?? []) as $anexoId) {
                    Anexos::remover((int) $anexoId, $novo);
                }
                if (!empty($_FILES['anexos']['name'][0])) {
                    foreach ((array) $_FILES['anexos']['name'] as $i => $nomeArquivo) {
                        if (($_FILES['anexos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        Anexos::adicionar($novo, [
                            'name'     => $nomeArquivo,
                            'tmp_name' => $_FILES['anexos']['tmp_name'][$i] ?? '',
                            'size'     => $_FILES['anexos']['size'][$i] ?? 0,
                            'error'    => $_FILES['anexos']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        ]);
                    }
                }
                aviso('Rascunho salvo. Confira o público e a prévia antes de liberar o envio.');
                irPara('?p=envio&id=' . $novo);

            case 'envio_liberar':
                $id = (int) $_POST['id'];
                $qtd = Campanhas::enfileirar($id);
                aviso("Envio liberado para {$qtd} destinatários. A fila começa a ser processada em até um minuto.");
                irPara('?p=envio&id=' . $id);

            case 'envio_pausar':
                Campanhas::pausar((int) $_POST['id']);
                aviso('Envio pausado. Nenhuma nova mensagem sai até você retomar.');
                irPara('?p=envio&id=' . (int) $_POST['id']);

            case 'envio_retomar':
                Campanhas::retomar((int) $_POST['id']);
                aviso('Envio retomado.');
                irPara('?p=envio&id=' . (int) $_POST['id']);

            case 'envio_cancelar':
                Campanhas::cancelar((int) $_POST['id']);
                aviso('Envio cancelado. As mensagens que ainda não saíram foram descartadas.');
                irPara('?p=envio&id=' . (int) $_POST['id']);

            case 'envio_reenviar_falhas':
                $qtd = Campanhas::reenviarFalhas((int) $_POST['id']);
                aviso($qtd > 0
                    ? "{$qtd} endereços voltaram para a fila."
                    : 'Não há falhas para reenviar.');
                irPara('?p=envio&id=' . (int) $_POST['id']);

            case 'envio_excluir':
                Campanhas::excluir((int) $_POST['id']);
                aviso('Envio excluído.');
                irPara('?p=envios');

            case 'envio_para_modelo':
                $campanha = Campanhas::buscar((int) $_POST['id']);
                if (!$campanha) {
                    throw new RuntimeException('Envio não encontrado.');
                }
                $modeloId = Modelos::salvar([
                    'nome'    => $campanha['nome'],
                    'assunto' => $campanha['assunto'],
                    'corpo'   => $campanha['corpo'],
                    'ativo'   => 1,
                ]);
                $copiados = Anexos::copiarCampanhaParaModelo((int) $campanha['id'], $modeloId);
                Auditoria::registrar('envio_virou_modelo', 'modelo', (string) $modeloId,
                    'a partir do envio ' . $campanha['id']
                    . ($copiados ? " com {$copiados} anexo(s)" : ''));
                aviso('Modelo criado a partir deste envio'
                    . ($copiados ? ', com os anexos' : '') . '. Ajuste o nome se quiser.');
                irPara('?p=modelo&id=' . $modeloId);

            case 'envio_teste':
                $campanha = Campanhas::buscar((int) $_POST['id']);
                if (!$campanha) {
                    throw new RuntimeException('Envio não encontrado.');
                }
                $para = trim((string) ($_POST['email_teste'] ?? '')) ?: (Auth::operador()['email'] ?? '');
                if (!emailValido($para)) {
                    throw new RuntimeException('Informe um e-mail válido para o teste.');
                }
                $correio = new Correio(false);
                $correio->enviar(
                    ['nome' => Auth::nome(), 'email' => $para, 'bairro' => 'Centro', 'contato_id' => null],
                    '[TESTE] ' . $campanha['assunto'],
                    $campanha['corpo'],
                    Anexos::listar((int) $campanha['id'])
                );
                Auditoria::registrar('envio_teste', 'campanha', (string) $campanha['id'], $para);
                aviso('Mensagem de teste enviada para ' . $para . '.');
                irPara('?p=envio&id=' . $campanha['id']);

            // ---------------------------------------------------- ajustes
            case 'ajustes_salvar':
                Auth::exigirAdmin();
                $porMinuto = max(1, min(
                    (int) config('fila.limite_por_minuto', 60),
                    (int) $_POST['envios_por_minuto']
                ));
                definirParametro('envios_por_minuto', (string) $porMinuto);
                definirParametro('envios_por_dia', (string) max(0, min(100000, (int) $_POST['envios_por_dia'])));
                definirParametro('max_tentativas', (string) max(1, min(5, (int) $_POST['max_tentativas'])));
                definirParametro('pausa_global', empty($_POST['pausa_global']) ? '0' : '1');
                Auditoria::registrar('ajustes_alterados', 'parametros', null,
                    'por_dia=' . (int) $_POST['envios_por_dia']
                    . ' por_minuto=' . $porMinuto
                    . ' pausa=' . (empty($_POST['pausa_global']) ? 'não' : 'sim'));
                aviso('Ajustes salvos.');
                irPara('?p=ajustes');

            case 'smtp_salvar':
                Auth::exigirAdmin();
                $host = trim((string) ($_POST['smtp_host'] ?? ''));
                if ($host !== '' && !preg_match('/^[a-z0-9][a-z0-9.-]*$/i', $host)) {
                    throw new RuntimeException('Informe apenas o nome do servidor, sem barras nem espaços — por exemplo: zldapmta.santahelena.pr.gov.br');
                }
                $usuario = trim((string) ($_POST['smtp_usuario'] ?? ''));
                if ($usuario !== '' && preg_match('/\s/', $usuario)) {
                    throw new RuntimeException('A conta de envio não pode conter espaços.');
                }
                definirParametro('smtp_host', $host);
                definirParametro('smtp_usuario', $usuario);

                // A senha nunca é exibida: em branco mantém a atual, e a
                // opção de limpar volta a valer a do config.php.
                $detalheSenha = 'senha mantida';
                if (!empty($_POST['smtp_senha_limpar'])) {
                    definirParametro('smtp_senha', '');
                    $detalheSenha = 'senha voltou ao config.php';
                } elseif (($_POST['smtp_senha'] ?? '') !== '') {
                    definirParametro('smtp_senha', (string) $_POST['smtp_senha']);
                    $detalheSenha = 'senha alterada';
                }

                Auditoria::registrar('smtp_alterado', 'parametros', null,
                    'host=' . ($host ?: '(config.php)')
                    . ' conta=' . ($usuario ?: '(config.php)')
                    . ' | ' . $detalheSenha);
                aviso('Configuração do servidor salva. Use o teste de conexão para confirmar.');
                irPara('?p=ajustes');

            case 'testar_smtp':
                Auth::exigirAdmin();
                [$ok, $detalhe] = (new Correio(false))->testarConexao();
                aviso(($ok ? 'SMTP: ' : 'SMTP falhou: ') . $detalhe, $ok ? 'ok' : 'erro');
                irPara('?p=ajustes');

            // ---------------------------------------------------- operadores
            case 'operador_salvar':
                Auth::exigirAdmin();
                $senha = (string) $_POST['senha'];
                if (mb_strlen($senha) < 10) {
                    throw new RuntimeException('A senha precisa ter pelo menos 10 caracteres.');
                }
                Auth::criar(
                    trim((string) $_POST['nome']),
                    trim((string) $_POST['login']),
                    trim((string) $_POST['email']),
                    $senha,
                    ($_POST['papel'] ?? 'operador') === 'admin' ? 'admin' : 'operador'
                );
                aviso('Operador cadastrado.');
                irPara('?p=ajustes');

            default:
                throw new RuntimeException('Ação desconhecida.');
        }
    } catch (Throwable $erro) {
        aviso($erro->getMessage(), 'erro');
        irPara((string) ($_POST['voltar'] ?? '?p=painel'));
    }
}

// =====================================================================
// Páginas (GET)
// =====================================================================
switch ($pagina) {

    case 'painel':
        $resumo    = Contatos::resumo();
        $recentes  = Campanhas::listar(8);
        $emAndamento = Db::todos(
            'SELECT * FROM campanhas WHERE status IN ("na_fila","enviando","pausada") ORDER BY id DESC'
        );
        $naFila = (int) Db::valor('SELECT COUNT(*) FROM fila WHERE status IN ("pendente","enviando")');
        incluirView('painel', compact('resumo', 'recentes', 'emAndamento', 'naFila'));
        break;

    case 'contatos':
        $filtros = [
            'texto'    => trim((string) ($_GET['q'] ?? '')),
            'bairro'   => (string) ($_GET['bairro'] ?? ''),
            'situacao' => (string) ($_GET['situacao'] ?? ''),
        ];
        $pag  = max(1, (int) ($_GET['pag'] ?? 1));
        $por  = (int) config('app.itens_por_pagina', 50);
        $dados = Contatos::listar($filtros, $pag, $por);
        $bairros = Contatos::bairros();
        incluirView('contatos', compact('dados', 'filtros', 'bairros', 'pag', 'por'));
        break;

    case 'contato':
        $id = (int) ($_GET['id'] ?? 0);
        $contato = $id ? Contatos::buscar($id) : null;
        if ($id && !$contato) {
            aviso('Contato não encontrado.', 'erro');
            irPara('?p=contatos');
        }
        $bairros = Bairros::listar();
        incluirView('contato_form', compact('contato', 'bairros'));
        break;

    case 'bairros':
        incluirView('bairros', ['bairros' => Bairros::listar()]);
        break;

    case 'bairro_fundir':
        $id = (int) ($_GET['id'] ?? 0);
        $bairro = Bairros::buscar($id);
        if (!$bairro) {
            aviso('Bairro não encontrado.', 'erro');
            irPara('?p=bairros');
        }
        $totalMoradores = count(Bairros::contatosDoBairro($id));
        $destinos = array_values(array_filter(
            Bairros::listar(),
            static fn(array $b) => (int) $b['id'] !== $id
        ));
        if (!$destinos) {
            aviso('Não há outro bairro cadastrado para receber a fusão.', 'erro');
            irPara('?p=bairros');
        }
        incluirView('bairro_fundir', compact('bairro', 'totalMoradores', 'destinos'));
        break;

    case 'bairro_contatos':
        $id = (int) ($_GET['id'] ?? 0);
        $bairro = Bairros::buscar($id);
        if (!$bairro) {
            aviso('Bairro não encontrado.', 'erro');
            irPara('?p=bairros');
        }
        $moradores = Bairros::contatosDoBairro($id);
        incluirView('bairro_contatos', compact('bairro', 'moradores'));
        break;

    case 'importar':
        $resultado = $_SESSION['resultado_importacao'] ?? null;
        unset($_SESSION['resultado_importacao']);
        incluirView('importar', compact('resultado'));
        break;

    case 'modelos':
        incluirView('modelos', ['modelos' => Modelos::listar()]);
        break;

    case 'modelo':
        $id = (int) ($_GET['id'] ?? 0);
        $modelo = $id ? Modelos::buscar($id) : null;
        $anexos = $modelo ? Anexos::listarDoModelo((int) $modelo['id']) : [];
        incluirView('modelo_form', compact('modelo', 'anexos'));
        break;

    case 'envios':
        incluirView('envios', ['campanhas' => Campanhas::listar(200)]);
        break;

    case 'envio_novo':
        $id = (int) ($_GET['id'] ?? 0);
        $campanha = $id ? Campanhas::buscar($id) : null;
        $modelos  = Modelos::listar(true);
        $bairros  = Contatos::bairros();
        $totalGeral = Campanhas::contarPublico('todos', null);
        $anexos = $campanha ? Anexos::listar((int) $campanha['id']) : [];
        // Para a tela avisar quais anexos cada modelo traria ao envio.
        $anexosDeModelos = [];
        foreach (Db::todos('SELECT modelo_id, nome, tamanho FROM anexos WHERE modelo_id IS NOT NULL ORDER BY id') as $a) {
            $anexosDeModelos[(int) $a['modelo_id']][] =
                $a['nome'] . ' (' . Anexos::legivel((int) $a['tamanho']) . ')';
        }
        incluirView('envio_form', compact('campanha', 'modelos', 'bairros', 'totalGeral', 'anexos', 'anexosDeModelos'));
        break;

    case 'envio':
        $id = (int) ($_GET['id'] ?? 0);
        $campanha = Campanhas::buscar($id);
        if (!$campanha) {
            aviso('Envio não encontrado.', 'erro');
            irPara('?p=envios');
        }
        $numeros = Campanhas::recontar($id);
        $situacao = (string) ($_GET['situacao'] ?? '');
        $itens = $campanha['status'] === 'rascunho' ? [] : Campanhas::itensFila($id, $situacao);
        $previsto = $campanha['status'] === 'rascunho'
            ? Campanhas::contarPublico($campanha['escopo'], $campanha['escopo_valor'])
            : (int) $campanha['total'];
        $anexos = Anexos::listar($id);
        incluirView('envio', compact('campanha', 'numeros', 'itens', 'situacao', 'previsto', 'anexos'));
        break;

    case 'previa':
        $id = (int) ($_GET['id'] ?? 0);
        $campanha = Campanhas::buscar($id);
        if (!$campanha) {
            http_response_code(404);
            exit('Envio não encontrado.');
        }
        $exemplo = Db::primeiro(
            'SELECT id AS contato_id, nome, email, bairro FROM contatos WHERE ativo = 1 AND opt_out = 0 LIMIT 1'
        ) ?: ['contato_id' => null, 'nome' => 'Maria da Silva', 'email' => 'maria@exemplo.gov.br', 'bairro' => 'Centro'];

        header('Content-Type: text/html; charset=utf-8');
        echo Mensagem::moldura(Mensagem::preencher($campanha['corpo'], $exemplo), $exemplo);
        exit;

    case 'fila':
        // Consultada pela tela de acompanhamento para atualizar os números.
        header('Content-Type: application/json; charset=utf-8');
        $id = (int) ($_GET['id'] ?? 0);
        $campanha = Campanhas::buscar($id);
        $numeros  = Campanhas::recontar($id);
        echo json_encode([
            'status'     => $campanha['status'] ?? 'desconhecido',
            'total'      => (int) ($numeros['total'] ?? 0),
            'enviados'   => (int) ($numeros['enviados'] ?? 0),
            'falhas'     => (int) ($numeros['falhas'] ?? 0),
            'suprimidos' => (int) ($numeros['suprimidos'] ?? 0),
            'pendentes'  => (int) ($numeros['pendentes'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'relatorio':
        $de     = (string) ($_GET['de'] ?? '');
        $ate    = (string) ($_GET['ate'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $linhas = Campanhas::relatorio($de, $ate, $status);
        incluirView('relatorio', compact('linhas', 'de', 'ate', 'status'));
        break;

    case 'relatorio_csv':
        $linhas = Campanhas::relatorio(
            (string) ($_GET['de'] ?? ''),
            (string) ($_GET['ate'] ?? ''),
            (string) ($_GET['status'] ?? '')
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio-envios.csv"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF"); // BOM: Excel abre UTF-8 corretamente
        fputcsv($saida, ['Envio', 'Público', 'Situação', 'Liberado em', 'Concluído em',
                         'Total', 'Entregues', 'Falhas', 'Suprimidos', 'Taxa de entrega'], ';');
        foreach ($linhas as $c) {
            fputcsv($saida, [
                $c['nome'],
                Campanhas::descricaoEscopo($c),
                $c['status'],
                $c['iniciado_em'] ? date('d/m/Y H:i', strtotime($c['iniciado_em'])) : '',
                $c['concluido_em'] ? date('d/m/Y H:i', strtotime($c['concluido_em'])) : '',
                (int) $c['total'],
                (int) $c['enviados'],
                (int) $c['falhas'],
                (int) $c['suprimidos'],
                (int) $c['total'] > 0
                    ? number_format((int) $c['enviados'] * 100 / (int) $c['total'], 1, ',', '') . '%'
                    : '',
            ], ';');
        }
        fclose($saida);
        Auditoria::registrar('relatorio_baixado', 'campanha', null, 'resumo do período');
        exit;

    case 'envio_csv':
        $id = (int) ($_GET['id'] ?? 0);
        $campanha = Campanhas::buscar($id);
        if (!$campanha) {
            http_response_code(404);
            exit('Envio não encontrado.');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="envio-' . $id . '.csv"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, ['Nome', 'E-mail', 'Bairro', 'Situação', 'Tentativas', 'Enviado em', 'Detalhe'], ';');
        foreach (Campanhas::itensFila($id, '', 0) as $item) {
            fputcsv($saida, [
                $item['nome'],
                $item['email'],
                $item['bairro'],
                $item['status'],
                (int) $item['tentativas'],
                $item['enviado_em'] ? date('d/m/Y H:i', strtotime($item['enviado_em'])) : '',
                $item['ultimo_erro'],
            ], ';');
        }
        fclose($saida);
        Auditoria::registrar('relatorio_baixado', 'campanha', (string) $id, $campanha['nome']);
        exit;

    case 'ajustes':
        Auth::exigirAdmin();
        $operadores = Db::todos('SELECT * FROM operadores ORDER BY nome');
        incluirView('ajustes', compact('operadores'));
        break;

    case 'auditoria':
        incluirView('auditoria', ['registros' => Auditoria::listar(300)]);
        break;

    default:
        http_response_code(404);
        incluirView('painel', [
            'resumo' => Contatos::resumo(),
            'recentes' => Campanhas::listar(8),
            'emAndamento' => [],
            'naFila' => 0,
        ]);
}
