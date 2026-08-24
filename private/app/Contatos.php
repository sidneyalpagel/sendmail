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
            self::normalizarBairro($dados['bairro'] ?? null),
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

    private static function normalizarBairro(?string $bairro): ?string
    {
        $bairro = trim((string) $bairro);
        if ($bairro === '') {
            return null;
        }
        // Colapsa espaços e padroniza a caixa para não criar "Centro" e "CENTRO".
        $bairro = preg_replace('/\s+/u', ' ', $bairro);
        return mb_convert_case(mb_strtolower($bairro, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Importa um CSV com cabeçalho. Colunas reconhecidas:
     * nome, email, bairro, telefone, documento, observacao
     *
     * @return array{criados:int, atualizados:int, ignorados:int, erros:array}
     */
    public static function importarCsv(string $caminho, string $separador = ';', bool $atualizar = true): array
    {
        $ponteiro = fopen($caminho, 'r');
        if (!$ponteiro) {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

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
            fclose($ponteiro);
            throw new RuntimeException('O arquivo precisa ter uma coluna "email".');
        }

        $criados = $atualizados = $ignorados = 0;
        $erros = [];
        $linha = 1;

        while (($colunas = fgetcsv($ponteiro, 0, $separador)) !== false) {
            $linha++;
            if (count($colunas) === 1 && trim((string) $colunas[0]) === '') {
                continue;
            }

            $pegar = static fn(string $c) => isset($mapa[$c]) ? trim((string) ($colunas[$mapa[$c]] ?? '')) : '';
            $email = mb_strtolower($pegar('email'));
            $nome  = $pegar('nome') ?: $email;

            if (!emailValido($email)) {
                $ignorados++;
                if (count($erros) < 20) {
                    $erros[] = "linha {$linha}: e-mail inválido (" . ($email ?: 'vazio') . ')';
                }
                continue;
            }

            $dados = [
                'nome'       => $nome,
                'email'      => $email,
                'bairro'     => $pegar('bairro'),
                'telefone'   => $pegar('telefone'),
                'documento'  => $pegar('documento'),
                'observacao' => $pegar('observacao'),
                'ativo'      => 1,
                'origem'     => 'csv',
            ];

            $existente = self::porEmail($email);
            try {
                if ($existente) {
                    if (!$atualizar) {
                        $ignorados++;
                        continue;
                    }
                    // Preserva o descadastro: quem pediu para sair não volta por importação.
                    self::salvar($dados, (int) $existente['id']);
                    $atualizados++;
                } else {
                    self::salvar($dados);
                    $criados++;
                }
            } catch (Throwable $erro) {
                $ignorados++;
                if (count($erros) < 20) {
                    $erros[] = "linha {$linha}: " . $erro->getMessage();
                }
            }
        }
        fclose($ponteiro);

        Auditoria::registrar(
            'importacao_csv',
            'contato',
            null,
            "criados={$criados} atualizados={$atualizados} ignorados={$ignorados}"
        );

        return compact('criados', 'atualizados', 'ignorados', 'erros');
    }

    private static function chaveColuna(string $nome): ?string
    {
        $nome = mb_strtolower(trim($nome));
        $nome = strtr($nome, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        $equivalentes = [
            'nome'       => ['nome', 'nome completo', 'destinatario', 'contato'],
            'email'      => ['email', 'e-mail', 'endereco de email', 'mail'],
            'bairro'     => ['bairro', 'localidade', 'regiao'],
            'telefone'   => ['telefone', 'fone', 'celular', 'whatsapp'],
            'documento'  => ['documento', 'cpf', 'matricula'],
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
