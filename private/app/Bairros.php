<?php
declare(strict_types=1);

/**
 * Cadastro de bairros.
 *
 * O catálogo é a referência para o formulário de contato e para a tela de
 * gestão. O contato continua guardando o nome do bairro (texto), então
 * renomear aqui propaga para todos os contatos daquele bairro. A importação
 * de CSV registra sozinha os bairros que ainda não existem.
 */
class Bairros
{
    /** Colapsa espaços e padroniza a caixa: "CENTRO " e "centro" viram "Centro". */
    public static function normalizar(?string $bairro): ?string
    {
        $bairro = trim((string) $bairro);
        if ($bairro === '') {
            return null;
        }
        $bairro = preg_replace('/\s+/u', ' ', $bairro);
        return mb_convert_case(mb_strtolower($bairro, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /** Todos os bairros do catálogo, com contagem de contatos e de aptos. */
    public static function listar(): array
    {
        return Db::todos(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM contatos c WHERE c.bairro = b.nome) AS contatos,
                    (SELECT COUNT(*) FROM contatos c
                      WHERE c.bairro = b.nome AND c.ativo = 1 AND c.opt_out = 0) AS aptos
               FROM bairros b
              ORDER BY b.nome'
        );
    }

    /** Garante o nome no catálogo e devolve a forma normalizada. */
    public static function registrar(?string $bairro): ?string
    {
        $nome = self::normalizar($bairro);
        if ($nome !== null) {
            Db::executar('INSERT IGNORE INTO bairros (nome) VALUES (?)', [$nome]);
        }
        return $nome;
    }

    public static function criar(string $nome): string
    {
        $nome = self::normalizar($nome);
        if ($nome === null) {
            throw new RuntimeException('Informe o nome do bairro.');
        }
        if (Db::valor('SELECT id FROM bairros WHERE nome = ?', [$nome])) {
            throw new RuntimeException('O bairro ' . $nome . ' já está cadastrado.');
        }
        Db::executar('INSERT INTO bairros (nome) VALUES (?)', [$nome]);
        Auditoria::registrar('bairro_criado', 'bairro', (string) Db::ultimoId(), $nome);
        return $nome;
    }

    /**
     * Renomeia um bairro e propaga aos contatos. Se o nome novo já existir
     * no catálogo, os dois são fundidos.
     */
    public static function renomear(int $id, string $novoNome): void
    {
        $bairro = Db::primeiro('SELECT * FROM bairros WHERE id = ?', [$id]);
        if (!$bairro) {
            throw new RuntimeException('Bairro não encontrado.');
        }
        $novo = self::normalizar($novoNome);
        if ($novo === null) {
            throw new RuntimeException('Informe o novo nome do bairro.');
        }
        if ($novo === $bairro['nome']) {
            return;
        }

        $duplicado = Db::primeiro('SELECT id FROM bairros WHERE nome = ? AND id <> ?', [$novo, $id]);

        $afetados = 0;
        Db::transacao(static function () use ($id, $bairro, $novo, $duplicado, &$afetados) {
            $afetados = Db::executar(
                'UPDATE contatos SET bairro = ? WHERE bairro = ?',
                [$novo, $bairro['nome']]
            )->rowCount();
            if ($duplicado) {
                Db::executar('DELETE FROM bairros WHERE id = ?', [$id]);
            } else {
                Db::executar('UPDATE bairros SET nome = ? WHERE id = ?', [$novo, $id]);
            }
        });

        Auditoria::registrar(
            $duplicado ? 'bairro_mesclado' : 'bairro_renomeado',
            'bairro',
            (string) $id,
            $bairro['nome'] . ' → ' . $novo . " ({$afetados} contatos)"
        );
    }

    public static function excluir(int $id): void
    {
        $bairro = Db::primeiro('SELECT * FROM bairros WHERE id = ?', [$id]);
        if (!$bairro) {
            return;
        }
        $emUso = (int) Db::valor('SELECT COUNT(*) FROM contatos WHERE bairro = ?', [$bairro['nome']]);
        if ($emUso > 0) {
            throw new RuntimeException(
                "O bairro {$bairro['nome']} tem {$emUso} contato(s). "
                . 'Renomeie-o para outro bairro (os contatos vão junto) em vez de excluir.'
            );
        }
        Db::executar('DELETE FROM bairros WHERE id = ?', [$id]);
        Auditoria::registrar('bairro_excluido', 'bairro', (string) $id, $bairro['nome']);
    }
}
