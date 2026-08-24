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
        if (Auth::entrar(trim((string) $_POST['login']), (string) $_POST['senha'])) {
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

            // ---------------------------------------------------- modelos
            case 'modelo_salvar':
                $id = (int) ($_POST['id'] ?? 0);
                Modelos::salvar($_POST, $id ?: null);
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
                    $campanha['corpo']
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
        $bairros = Contatos::bairros();
        incluirView('contato_form', compact('contato', 'bairros'));
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
        incluirView('modelo_form', compact('modelo'));
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
        incluirView('envio_form', compact('campanha', 'modelos', 'bairros', 'totalGeral'));
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
        incluirView('envio', compact('campanha', 'numeros', 'itens', 'situacao', 'previsto'));
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
