<?php
declare(strict_types=1);

/**
 * Instalação inicial: cria as tabelas e o primeiro operador administrador.
 *
 *   php private/bin/instalar.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só roda pela linha de comando.\n");
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

function perguntar(string $rotulo, bool $oculto = false): string
{
    // O eco só é desligado quando dá: em servidores endurecidos (Hestia, por
    // exemplo) shell_exec costuma estar na lista de funções desabilitadas.
    $podeOcultar = $oculto
        && stream_isatty(STDIN)
        && DIRECTORY_SEPARATOR !== '\\'
        && function_exists('shell_exec')
        && !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);

    if ($oculto && !$podeOcultar) {
        echo '(a senha ficará visível ao digitar) ';
    }

    echo $rotulo;

    if ($podeOcultar) {
        shell_exec('stty -echo');
        $valor = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        echo PHP_EOL;
        return $valor;
    }

    return trim((string) fgets(STDIN));
}

// Modo usado pelo deploy: cria o que faltar e sai, sem perguntar nada.
$somenteTabelas = in_array('--tabelas', $argv, true);

if (!$somenteTabelas) {
    echo "== Instalação do Disparador de e-mails ==\n\n";
}

// ---------------------------------------------------------------------
// 1. Estrutura do banco
// ---------------------------------------------------------------------
$schema = file_get_contents(RAIZ . '/../sql/schema.sql');
if ($schema === false) {
    $schema = file_get_contents(RAIZ . '/sql/schema.sql');
}
if ($schema === false) {
    exit("Arquivo sql/schema.sql não encontrado.\n");
}

echo "Criando tabelas... ";

// Remove os comentários de linha antes de separar os comandos: senão o
// bloco de comentário do topo é colado no primeiro CREATE TABLE e o
// comando inteiro acaba descartado.
$semComentarios = preg_replace('/^\s*--.*$/m', '', $schema) ?? $schema;

$criadas = 0;
foreach (array_filter(array_map('trim', explode(';', $semComentarios))) as $comando) {
    Db::pdo()->exec($comando);
    $criadas++;
}
echo "pronto ({$criadas} comandos).\n";

foreach (['operadores', 'contatos', 'modelos', 'campanhas', 'fila', 'anexos', 'auditoria', 'parametros'] as $tabela) {
    $existe = Db::valor(
        'SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?',
        [$tabela]
    );
    if (!$existe) {
        exit("\nERRO: a tabela {$tabela} não foi criada. Verifique as permissões do usuário do banco.\n");
    }
}

// ---------------------------------------------------------------------
// 2. Chave da aplicação
// ---------------------------------------------------------------------
if (!$somenteTabelas && str_contains((string) config('app.chave'), 'GERE_UMA_CHAVE')) {
    echo "\nATENÇÃO: a chave da aplicação ainda é o valor de exemplo.\n";
    echo "Gere uma e coloque em config.php na opção app.chave:\n\n";
    echo '  ' . bin2hex(random_bytes(32)) . "\n\n";
    echo "Sem isso, os links de descadastro não são confiáveis.\n";
}

// ---------------------------------------------------------------------
// 3. Primeiro operador
// ---------------------------------------------------------------------
// ---------------------------------------------------------------------
// Ajustes em bases que já existiam. Cada um confere antes de aplicar,
// então rodar de novo não causa erro.
// ---------------------------------------------------------------------
$indice = Db::valor(
    'SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = "fila" AND index_name = "idx_fila_janela"'
);
if (!$indice) {
    Db::pdo()->exec('ALTER TABLE fila ADD KEY idx_fila_janela (status, enviado_em)');
    echo "Índice idx_fila_janela criado.\n";
}

$temModeloId = Db::valor(
    'SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = "anexos" AND column_name = "modelo_id"'
);
if (!$temModeloId) {
    Db::pdo()->exec('ALTER TABLE anexos MODIFY campanha_id INT UNSIGNED NULL');
    Db::pdo()->exec(
        'ALTER TABLE anexos
            ADD COLUMN modelo_id INT UNSIGNED NULL AFTER campanha_id,
            ADD KEY idx_anexo_modelo (modelo_id),
            ADD CONSTRAINT fk_anexo_modelo FOREIGN KEY (modelo_id)
                REFERENCES modelos(id) ON DELETE CASCADE'
    );
    echo "Tabela anexos ganhou a coluna modelo_id.\n";
}

// Alimenta o cadastro de bairros com o que já existe nos contatos.
// INSERT IGNORE: rodar de novo não duplica nada.
Db::pdo()->exec(
    'INSERT IGNORE INTO bairros (nome)
     SELECT DISTINCT bairro FROM contatos WHERE bairro IS NOT NULL AND bairro <> ""'
);

foreach ([
    'envios_por_dia'    => '1000',
    'envios_por_minuto' => '20',
    'max_tentativas'    => '3',
    'pausa_global'      => '0',
] as $chave => $padrao) {
    Db::executar(
        'INSERT IGNORE INTO parametros (chave, valor) VALUES (?, ?)',
        [$chave, $padrao]
    );
}

if ($somenteTabelas) {
    echo "Estrutura do banco conferida.\n";
    exit(0);
}

$existentes = (int) Db::valor('SELECT COUNT(*) FROM operadores');
if ($existentes > 0) {
    echo "\nJá existem {$existentes} operadores cadastrados. Nada mais a fazer.\n";
    exit(0);
}

echo "\n-- Primeiro operador (administrador) --\n";
$nome  = perguntar('Nome completo: ');
$login = perguntar('Login de acesso: ');
$email = perguntar('E-mail: ');

do {
    $senha = perguntar('Senha (mínimo 10 caracteres): ', true);
    if (mb_strlen($senha) < 10) {
        echo "Muito curta. Tente novamente.\n";
        continue;
    }
    $confirmacao = perguntar('Repita a senha: ', true);
    if ($senha !== $confirmacao) {
        echo "As senhas não conferem. Tente novamente.\n";
        $senha = '';
    }
} while (mb_strlen($senha) < 10);

Auth::criar($nome, $login, $email, $senha, 'admin');

echo "\nOperador criado. Acesse " . config('app.url_base') . " e entre com o login '{$login}'.\n";
echo "Não esqueça de agendar o worker no cron:\n";
echo "  * * * * * php " . RAIZ . "/bin/worker.php >> " . LOGS . "/cron.log 2>&1\n";
