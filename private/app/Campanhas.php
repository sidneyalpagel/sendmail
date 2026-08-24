<?php
declare(strict_types=1);

/**
 * Campanhas de envio.
 *
 * O público é resolvido e congelado no momento em que a campanha entra na
 * fila. Contatos incluídos ou descadastrados depois disso não alteram um
 * envio já iniciado — o que garante que o relatório reflita exatamente
 * quem estava na lista naquele instante.
 */
class Campanhas
{
    public static function buscar(int $id): ?array
    {
        return Db::primeiro('SELECT * FROM campanhas WHERE id = ?', [$id]);
    }

    public static function listar(int $limite = 100): array
    {
        return Db::todos('SELECT * FROM campanhas ORDER BY id DESC LIMIT ' . max(1, $limite));
    }

    /**
     * Resolve o público de acordo com o escopo escolhido, já descartando
     * inativos e descadastrados.
     */
    public static function publico(string $escopo, ?string $valor): array
    {
        if ($escopo === 'contato') {
            return Db::todos(
                'SELECT id, nome, email, bairro FROM contatos WHERE id = ? AND ativo = 1 AND opt_out = 0',
                [(int) $valor]
            );
        }
        if ($escopo === 'bairro') {
            return Db::todos(
                'SELECT id, nome, email, bairro FROM contatos
                  WHERE bairro = ? AND ativo = 1 AND opt_out = 0 ORDER BY nome',
                [$valor]
            );
        }
        if ($escopo === 'todos') {
            return Db::todos(
                'SELECT id, nome, email, bairro FROM contatos
                  WHERE ativo = 1 AND opt_out = 0 ORDER BY nome'
            );
        }
        throw new RuntimeException('Escopo de envio desconhecido: ' . $escopo);
    }

    /** Quantos destinatários o escopo alcança, sem criar nada. */
    public static function contarPublico(string $escopo, ?string $valor): int
    {
        if ($escopo === 'contato') {
            return (int) Db::valor(
                'SELECT COUNT(*) FROM contatos WHERE id = ? AND ativo = 1 AND opt_out = 0',
                [(int) $valor]
            );
        }
        if ($escopo === 'bairro') {
            return (int) Db::valor(
                'SELECT COUNT(*) FROM contatos WHERE bairro = ? AND ativo = 1 AND opt_out = 0',
                [$valor]
            );
        }
        return (int) Db::valor('SELECT COUNT(*) FROM contatos WHERE ativo = 1 AND opt_out = 0');
    }

    public static function salvarRascunho(array $dados, ?int $id = null): int
    {
        $nome    = trim((string) $dados['nome']);
        $assunto = trim((string) $dados['assunto']);
        $corpo   = Mensagem::limpar((string) $dados['corpo']);
        $escopo  = (string) $dados['escopo'];
        $valor   = $dados['escopo_valor'] ?? null;

        if ($nome === '' || $assunto === '' || trim(strip_tags($corpo)) === '') {
            throw new RuntimeException('Preencha o nome do envio, o assunto e o corpo da mensagem.');
        }
        if (!in_array($escopo, ['contato', 'bairro', 'todos'], true)) {
            throw new RuntimeException('Escolha para quem a mensagem será enviada.');
        }
        if ($escopo !== 'todos' && ($valor === null || $valor === '')) {
            throw new RuntimeException($escopo === 'bairro'
                ? 'Escolha o bairro que vai receber a mensagem.'
                : 'Escolha o destinatário.');
        }
        if ($escopo === 'todos') {
            $valor = null;
        }

        // O operador digita o e-mail; guardamos o identificador do contato.
        if ($escopo === 'contato' && !ctype_digit((string) $valor)) {
            $contato = Contatos::porEmail((string) $valor);
            if (!$contato) {
                throw new RuntimeException('Não há contato cadastrado com o e-mail ' . $valor . '.');
            }
            if ((int) $contato['ativo'] === 0 || (int) $contato['opt_out'] === 1) {
                throw new RuntimeException('O contato ' . $valor . ' está inativo ou descadastrado.');
            }
            $valor = (string) $contato['id'];
        }

        if ($id) {
            $campanha = self::buscar($id);
            if (!$campanha || $campanha['status'] !== 'rascunho') {
                throw new RuntimeException('Só é possível editar envios que ainda estão como rascunho.');
            }
            Db::executar(
                'UPDATE campanhas SET nome=?, assunto=?, corpo=?, escopo=?, escopo_valor=?, modelo_id=? WHERE id=?',
                [$nome, $assunto, $corpo, $escopo, $valor, $dados['modelo_id'] ?: null, $id]
            );
            Auditoria::registrar('campanha_editada', 'campanha', (string) $id, $nome);
            return $id;
        }

        Db::executar(
            'INSERT INTO campanhas (nome, assunto, corpo, escopo, escopo_valor, modelo_id, criado_por)
             VALUES (?,?,?,?,?,?,?)',
            [$nome, $assunto, $corpo, $escopo, $valor, $dados['modelo_id'] ?: null, Auth::id()]
        );
        $novo = Db::ultimoId();
        Auditoria::registrar('campanha_criada', 'campanha', (string) $novo, $nome . ' | escopo: ' . $escopo);
        return $novo;
    }

    /**
     * Congela o público e coloca a campanha na fila.
     * @return int quantidade de destinatários enfileirados
     */
    public static function enfileirar(int $id): int
    {
        $campanha = self::buscar($id);
        if (!$campanha) {
            throw new RuntimeException('Envio não encontrado.');
        }
        if ($campanha['status'] !== 'rascunho') {
            throw new RuntimeException('Este envio já foi liberado anteriormente.');
        }

        $destinatarios = self::publico($campanha['escopo'], $campanha['escopo_valor']);
        if (!$destinatarios) {
            throw new RuntimeException('Nenhum destinatário apto foi encontrado para este escopo.');
        }

        Db::transacao(static function () use ($id, $destinatarios) {
            $stmt = Db::pdo()->prepare(
                'INSERT INTO fila (campanha_id, contato_id, nome, email, bairro, liberar_em)
                 VALUES (?,?,?,?,?, NOW())'
            );
            foreach ($destinatarios as $d) {
                $stmt->execute([$id, $d['id'], $d['nome'], $d['email'], $d['bairro']]);
            }
            Db::executar(
                'UPDATE campanhas SET status = "na_fila", total = ?, iniciado_em = NOW() WHERE id = ?',
                [count($destinatarios), $id]
            );
        });

        Auditoria::registrar(
            'campanha_liberada',
            'campanha',
            (string) $id,
            $campanha['nome'] . ' | ' . count($destinatarios) . ' destinatários'
        );

        return count($destinatarios);
    }

    public static function pausar(int $id): void
    {
        Db::executar(
            'UPDATE campanhas SET status = "pausada" WHERE id = ? AND status IN ("na_fila","enviando")',
            [$id]
        );
        Auditoria::registrar('campanha_pausada', 'campanha', (string) $id);
    }

    public static function retomar(int $id): void
    {
        Db::executar('UPDATE campanhas SET status = "na_fila" WHERE id = ? AND status = "pausada"', [$id]);
        Auditoria::registrar('campanha_retomada', 'campanha', (string) $id);
    }

    public static function cancelar(int $id): void
    {
        Db::transacao(static function () use ($id) {
            Db::executar('UPDATE fila SET status = "suprimido", ultimo_erro = "envio cancelado pelo operador"
                           WHERE campanha_id = ? AND status IN ("pendente","enviando")', [$id]);
            Db::executar('UPDATE campanhas SET status = "cancelada", concluido_em = NOW() WHERE id = ?', [$id]);
        });
        self::recontar($id);
        Auditoria::registrar('campanha_cancelada', 'campanha', (string) $id);
    }

    public static function excluir(int $id): void
    {
        $campanha = self::buscar($id);
        if ($campanha && in_array($campanha['status'], ['na_fila', 'enviando'], true)) {
            throw new RuntimeException('Cancele o envio antes de excluí-lo.');
        }
        Anexos::removerTodos($id);
        Db::executar('DELETE FROM campanhas WHERE id = ?', [$id]);
        Auditoria::registrar('campanha_excluida', 'campanha', (string) $id);
    }

    /** Recoloca na fila apenas os endereços que falharam. */
    public static function reenviarFalhas(int $id): int
    {
        $afetados = Db::executar(
            'UPDATE fila SET status = "pendente", tentativas = 0, ultimo_erro = NULL, liberar_em = NOW()
              WHERE campanha_id = ? AND status = "falha"',
            [$id]
        )->rowCount();

        if ($afetados > 0) {
            Db::executar(
                'UPDATE campanhas SET status = "na_fila", concluido_em = NULL WHERE id = ?',
                [$id]
            );
            self::recontar($id);
        }
        Auditoria::registrar('reenvio_falhas', 'campanha', (string) $id, $afetados . ' endereços');
        return $afetados;
    }

    /** Atualiza os contadores da campanha a partir da fila. */
    public static function recontar(int $id): array
    {
        $n = Db::primeiro(
            'SELECT COUNT(*) AS total,
                    SUM(status = "enviado")   AS enviados,
                    SUM(status = "falha")     AS falhas,
                    SUM(status = "suprimido") AS suprimidos,
                    SUM(status IN ("pendente","enviando")) AS pendentes
               FROM fila WHERE campanha_id = ?',
            [$id]
        ) ?: [];

        Db::executar(
            'UPDATE campanhas SET total = ?, enviados = ?, falhas = ?, suprimidos = ? WHERE id = ?',
            [(int) ($n['total'] ?? 0), (int) ($n['enviados'] ?? 0),
             (int) ($n['falhas'] ?? 0), (int) ($n['suprimidos'] ?? 0), $id]
        );

        return $n;
    }

    /** Marca como concluída quando não sobra nada pendente. */
    public static function concluirSePronta(int $id): void
    {
        $pendentes = (int) Db::valor(
            'SELECT COUNT(*) FROM fila WHERE campanha_id = ? AND status IN ("pendente","enviando")',
            [$id]
        );
        if ($pendentes === 0) {
            Db::executar(
                'UPDATE campanhas SET status = "concluida", concluido_em = NOW()
                  WHERE id = ? AND status IN ("na_fila","enviando")',
                [$id]
            );
        }
    }

    public static function itensFila(int $id, string $situacao = '', int $limite = 300): array
    {
        $sql = 'SELECT * FROM fila WHERE campanha_id = ?';
        $par = [$id];
        if ($situacao !== '') {
            $sql .= ' AND status = ?';
            $par[] = $situacao;
        }
        $sql .= ' ORDER BY id';
        if ($limite > 0) {
            $sql .= ' LIMIT ' . $limite;
        }
        return Db::todos($sql, $par);
    }

    /**
     * Campanhas já liberadas (tudo menos rascunho), para o relatório.
     * Datas no formato AAAA-MM-DD, filtrando pela liberação do envio.
     */
    public static function relatorio(string $de = '', string $ate = '', string $status = ''): array
    {
        $sql = 'SELECT * FROM campanhas WHERE status <> "rascunho"';
        $par = [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
            $sql .= ' AND iniciado_em >= ?';
            $par[] = $de . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
            $sql .= ' AND iniciado_em <= ?';
            $par[] = $ate . ' 23:59:59';
        }
        if (in_array($status, ['na_fila', 'enviando', 'pausada', 'concluida', 'cancelada'], true)) {
            $sql .= ' AND status = ?';
            $par[] = $status;
        }
        return Db::todos($sql . ' ORDER BY iniciado_em DESC, id DESC', $par);
    }

    public static function descricaoEscopo(array $campanha): string
    {
        return match ($campanha['escopo']) {
            'todos'   => 'Todos os contatos ativos',
            'bairro'  => 'Bairro: ' . $campanha['escopo_valor'],
            'contato' => 'Contato: ' . (Contatos::buscar((int) $campanha['escopo_valor'])['email'] ?? 'não encontrado'),
            default   => $campanha['escopo'],
        };
    }
}
