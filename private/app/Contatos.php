<?php
declare(strict_types=1);

/**
 * Cadastro de destinatários.
 */
class Contatos
{
    public static function buscar(int $id): ?array
    {
        return Db::primeiro('SELECT * FROM contatos WHERE id = ?', [$id]);
    }

    public static function porEmail(string $email): ?array
    {
        return Db::primeiro('SELECT * FROM contatos WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /**
     * Listagem paginada com filtros de texto, bairro e situação.
     * @return array{itens: array, total: int}
     */
    public static function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        $onde = [];
        $par  = [];

        if (!empty($filtros['texto'])) {
            $onde[] = '(nome LIKE ? OR email LIKE ? OR telefone LIKE ?)';
            $curinga = '%' . $filtros['texto'] . '%';
            array_push($par, $curinga, $curinga, $curinga);
        }
        if (!empty($filtros['bairro'])) {
            $onde[] = 'bairro = ?';
            $par[]  = $filtros['bairro'];
        }
        if (($filtros['situacao'] ?? '') === 'ativos') {
            $onde[] = 'ativo = 1 AND opt_out = 0';
        } elseif (($filtros['situacao'] ?? '') === 'inativos') {
            $onde[] = 'ativo = 0';
        } elseif (($filtros['situacao'] ?? '') === 'descadastrados') {
            $onde[] = 'opt_out = 1';
        }

        $sqlOnde = $onde ? ' WHERE ' . implode(' AND ', $onde) : '';
        $total   = (int) Db::valor('SELECT COUNT(*) FROM contatos' . $sqlOnde, $par);

        $pagina    = max(1, $pagina);
        $porPagina = max(1, min(500, $porPagina));
        $deslocar  = ($pagina - 1) * $porPagina;

        $itens = Db::todos(
            'SELECT * FROM contatos' . $sqlOnde . ' ORDER BY nome LIMIT ' . $porPagina . ' OFFSET ' . $deslocar,
            $par
        );

        return ['itens' => $itens, 'total' => $total];
    }

    /** Bairros distintos, com a contagem de destinatários aptos a receber. */
    public static function bairros(): array
    {
        return Db::todos(
            "SELECT bairro,
                    COUNT(*) AS total,
                    SUM(ativo = 1 AND opt_out = 0) AS aptos
               FROM contatos
              WHERE bairro IS NOT NULL AND bairro <> ''
              GROUP BY bairro
              ORDER BY bairro"
        );
    }

    public static function resumo(): array
    {
        return Db::primeiro(
            "SELECT COUNT(*) AS total,
                    SUM(ativo = 1 AND opt_out = 0) AS aptos,
                    SUM(opt_out = 1)               AS descadastrados,
                    SUM(ativo = 0)                 AS inativos,
                    COUNT(DISTINCT NULLIF(bairro, '')) AS bairros
               FROM contatos"
        ) ?? [];
    }

    public static function salvar(array $dados, ?int $id = null): int
    {
        $email = mb_strtolower(trim((string) $dados['email']));
        if (!emailValido($email)) {
            throw new RuntimeException('Endereço de e-mail inválido: ' . $email);
        }
        $nome = trim((string) $dados['nome']);
        if ($nome === '') {
            throw new RuntimeException('O nome é obrigatório.');
        }

        $duplicado = Db::valor(
            'SELECT id FROM contatos WHERE email = ? AND id <> ?',
            [$email, $id ?? 0]
        );
        if ($duplicado) {
            throw new RuntimeException('Já existe um contato com o e-mail ' . $email . '.');
        }

        $campos = [
            $nome,
            $email,
            Bairros::registrar($dados['bairro'] ?? null),
            trim((string) ($dados['telefone'] ?? '')) ?: null,
            trim((string) ($dados['documento'] ?? '')) ?: null,
            trim((string) ($dados['observacao'] ?? '')) ?: null,
            !empty($dados['ativo']) ? 1 : 0,
        ];

        if ($id) {
            $campos[] = $id;
            Db::executar(
                'UPDATE contatos SET nome=?, email=?, bairro=?, telefone=?, documento=?, observacao=?, ativo=?
                  WHERE id=?',
                $campos
            );
            Auditoria::registrar('contato_editado', 'contato', (string) $id, $email);
            return $id;
        }

        $campos[] = $dados['origem'] ?? 'manual';
        Db::executar(
            'INSERT INTO contatos (nome, email, bairro, telefone, documento, observacao, ativo, origem)
             VALUES (?,?,?,?,?,?,?,?)',
            $campos
        );
        $novo = Db::ultimoId();
        Auditoria::registrar('contato_criado', 'contato', (string) $novo, $email);
        return $novo;
    }

    public static function excluir(int $id): void
    {
        $contato = self::buscar($id);
        Db::executar('DELETE FROM contatos WHERE id = ?', [$id]);
        Auditoria::registrar('contato_excluido', 'contato', (string) $id, $contato['email'] ?? null);
    }

    public static function descadastrar(int $id, string $origem = 'operador'): void
    {
        Db::executar(
            'UPDATE contatos SET opt_out = 1, opt_out_em = NOW() WHERE id = ?',
            [$id]
        );
        Auditoria::registrar('descadastro', 'contato', (string) $id, 'origem: ' . $origem);
    }

    public static function recadastrar(int $id): void
    {
        Db::executar('UPDATE contatos SET opt_out = 0, opt_out_em = NULL WHERE id = ?', [$id]);
        Auditoria::registrar('recadastro', 'contato', (string) $id);
    }

    /**
     * Importa um CSV com cabeçalho. Colunas reconhecidas:
     * nome, email, bairro, telefone, documento, observacao
     *
     * @return array{criados:int, atualizados:int, ignorados:int, erros:array}
     */
    public static function importarCsv(string $caminho, string $separador = ';', bool $atualizar = true): array
    {
        $conteudo = file_get_contents($caminho);
        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        // Exportações de sistemas Windows costumam vir em ANSI (Windows-1252),
        // não em UTF-8. Sem converter, cabeçalhos acentuados ("Nome Razão")
        // não são reconhecidos e os acentos dos dados viram lixo no banco.
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }

        $ponteiro = fopen('php://temp', 'r+');
        fwrite($ponteiro, $conteudo);
        rewind($ponteiro);
        unset($conteudo);

        $cabecalho = fgetcsv($ponteiro, 0, $separador);
        if (!$cabecalho) {
            fclose($ponteiro);
            throw new RuntimeException('O arquivo está vazio.');
        }
        // Remove BOM do UTF-8, se houver.
        $cabecalho[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cabecalho[0]);

        $mapa = [];
        foreach ($cabecalho as $indice => $nomeColuna) {
            $chave = self::chaveColuna((string) $nomeColuna);
            if ($chave !== null) {
                $mapa[$chave] = $indice;
            }
        }
        if (!isset($mapa['email'])) {
            // Exportações de outros sistemas nem sempre nomeiam a coluna:
            // o endereço vem em campos genéricos ("Descrição", "Contato").
            // Fareja uma amostra do conteúdo; se exatamente uma coluna
            // contém e-mails, é ela.
            $indice = self::farejarColunaEmail($ponteiro, $separador);
            if ($indice === null) {
                fclose($ponteiro);
                throw new RuntimeException(
                    'Não encontrei a coluna de e-mail. Nomeie-a "email" no cabeçalho, '
                    . 'ou confira se o separador escolhido é o mesmo do arquivo.'
                );
            }
            $mapa['email'] = $indice;
            rewind($ponteiro);
            fgetcsv($ponteiro, 0, $separador); // pula o cabeçalho de novo
        }

        // -------------------------------------------------------------
        // 1ª fase: lê e valida o arquivo inteiro em memória.
        //
        // Nada de consulta por linha: com o banco em outra máquina, a
        // latência de rede multiplicada por milhares de linhas estourava
        // o tempo da requisição e deixava importações pela metade.
        // -------------------------------------------------------------
        $ignorados = 0;
        $erros = [];
        $linha = 1;
        $lidos = [];   // email => dados; e-mail repetido no arquivo: vale a última linha

        while (($colunas = fgetcsv($ponteiro, 0, $separador)) !== false) {
            $linha++;
            if (count($colunas) === 1 && trim((string) $colunas[0]) === '') {
                continue;
            }

            $pegar = static fn(string $c) => isset($mapa[$c]) ? trim((string) ($colunas[$mapa[$c]] ?? '')) : '';
            $email = mb_strtolower($pegar('email'));

            if (!emailValido($email)) {
                $ignorados++;
                if (count($erros) < 20) {
                    $erros[] = "linha {$linha}: e-mail inválido (" . ($email ?: 'vazio') . ')';
                }
                continue;
            }

            $lidos[$email] = [
                'nome'       => $pegar('nome') ?: $email,
                'email'      => $email,
                'bairro'     => Bairros::normalizar($pegar('bairro')),
                'telefone'   => $pegar('telefone') ?: null,
                'documento'  => $pegar('documento') ?: null,
                'observacao' => $pegar('observacao') ?: null,
            ];
        }
        fclose($ponteiro);

        // -------------------------------------------------------------
        // 2ª fase: uma única consulta separa novos de já cadastrados.
        // -------------------------------------------------------------
        $existentes = [];
        foreach (Db::todos('SELECT email FROM contatos') as $l) {
            $existentes[$l['email']] = true;
        }

        $criados = $atualizados = 0;
        $gravar = [];
        foreach ($lidos as $email => $dados) {
            if (isset($existentes[$email])) {
                if (!$atualizar) {
                    $ignorados++;
                    continue;
                }
                $atualizados++;
            } else {
                $criados++;
            }
            $gravar[] = $dados;
        }

        // -------------------------------------------------------------
        // 3ª fase: grava em lotes, dentro de uma transação.
        //
        // O ON DUPLICATE cobre criação e atualização de uma vez. Ele não
        // toca opt_out nem origem: quem pediu descadastro continua fora
        // dos envios mesmo aparecendo de novo no arquivo.
        // -------------------------------------------------------------
        Db::transacao(static function () use ($gravar) {
            foreach (array_chunk($gravar, 500) as $lote) {
                $marcadores = rtrim(str_repeat('(?,?,?,?,?,?,1,\'csv\'),', count($lote)), ',');
                $par = [];
                foreach ($lote as $d) {
                    array_push(
                        $par,
                        $d['nome'], $d['email'], $d['bairro'],
                        $d['telefone'], $d['documento'], $d['observacao']
                    );
                }
                Db::executar(
                    'INSERT INTO contatos (nome, email, bairro, telefone, documento, observacao, ativo, origem)
                     VALUES ' . $marcadores . '
                     ON DUPLICATE KEY UPDATE
                         nome = VALUES(nome), bairro = VALUES(bairro),
                         telefone = VALUES(telefone), documento = VALUES(documento),
                         observacao = VALUES(observacao), ativo = VALUES(ativo)',
                    $par
                );
            }
        });

        // Bairros novos do arquivo entram no cadastro automaticamente.
        $nomesBairros = array_values(array_unique(array_filter(array_column($gravar, 'bairro'))));
        if ($nomesBairros) {
            Db::executar(
                'INSERT IGNORE INTO bairros (nome) VALUES '
                . rtrim(str_repeat('(?),', count($nomesBairros)), ','),
                $nomesBairros
            );
        }

        Auditoria::registrar(
            'importacao_csv',
            'contato',
            null,
            "criados={$criados} atualizados={$atualizados} ignorados={$ignorados}"
        );

        return compact('criados', 'atualizados', 'ignorados', 'erros');
    }

    /**
     * Descobre a coluna de e-mail pelo conteúdo, quando o cabeçalho não a
     * nomeia. Lê uma amostra e devolve o índice da coluna em que ao menos
     * metade dos valores preenchidos é um e-mail válido — ou null quando
     * nenhuma (ou mais de uma) se qualifica, caso em que adivinhar é pior
     * que pedir o cabeçalho certo.
     *
     * @param resource $ponteiro posicionado logo após o cabeçalho
     */
    private static function farejarColunaEmail($ponteiro, string $separador): ?int
    {
        $validos = $preenchidos = [];
        $lidas = 0;
        while ($lidas < 50 && ($colunas = fgetcsv($ponteiro, 0, $separador)) !== false) {
            if (count($colunas) === 1 && trim((string) $colunas[0]) === '') {
                continue;
            }
            $lidas++;
            foreach ($colunas as $i => $valor) {
                $valor = trim((string) $valor);
                if ($valor === '') {
                    continue;
                }
                $preenchidos[$i] = ($preenchidos[$i] ?? 0) + 1;
                if (emailValido(mb_strtolower($valor))) {
                    $validos[$i] = ($validos[$i] ?? 0) + 1;
                }
            }
        }

        $candidatas = [];
        foreach ($validos as $i => $qtd) {
            if ($qtd * 2 >= $preenchidos[$i]) {
                $candidatas[] = $i;
            }
        }
        return count($candidatas) === 1 ? $candidatas[0] : null;
    }

    private static function chaveColuna(string $nome): ?string
    {
        $nome = mb_strtolower(trim($nome));
        $nome = strtr($nome, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        $equivalentes = [
            'nome'       => ['nome', 'nome completo', 'destinatario', 'contato',
                             'pessoas - nome razao', 'nome razao', 'razao social'],
            'email'      => ['email', 'e-mail', 'endereco de email', 'mail'],
            'bairro'     => ['bairro', 'localidade', 'regiao', 'bairro - nome'],
            'telefone'   => ['telefone', 'fone', 'celular', 'whatsapp'],
            'documento'  => ['documento', 'cpf', 'matricula', 'cpf/cnpj',
                             'pessoas - cpf/cnpj'],
            'observacao' => ['observacao', 'obs', 'anotacao'],
        ];
        foreach ($equivalentes as $chave => $lista) {
            if (in_array($nome, $lista, true)) {
                return $chave;
            }
        }
        return null;
    }
}
