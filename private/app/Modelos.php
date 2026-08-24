<?php
declare(strict_types=1);

/**
 * Modelos de mensagem reutilizáveis.
 */
class Modelos
{
    public static function listar(bool $somenteAtivos = false): array
    {
        $sql = 'SELECT * FROM modelos';
        if ($somenteAtivos) {
            $sql .= ' WHERE ativo = 1';
        }
        return Db::todos($sql . ' ORDER BY nome');
    }

    public static function buscar(int $id): ?array
    {
        return Db::primeiro('SELECT * FROM modelos WHERE id = ?', [$id]);
    }

    public static function salvar(array $dados, ?int $id = null): int
    {
        $nome    = trim((string) $dados['nome']);
        $assunto = trim((string) $dados['assunto']);
        $corpo   = (string) $dados['corpo'];

        if ($nome === '' || $assunto === '' || trim($corpo) === '') {
            throw new RuntimeException('Nome, assunto e corpo da mensagem são obrigatórios.');
        }

        if ($id) {
            Db::executar(
                'UPDATE modelos SET nome=?, assunto=?, corpo=?, ativo=? WHERE id=?',
                [$nome, $assunto, $corpo, !empty($dados['ativo']) ? 1 : 0, $id]
            );
            Auditoria::registrar('modelo_editado', 'modelo', (string) $id, $nome);
            return $id;
        }

        Db::executar(
            'INSERT INTO modelos (nome, assunto, corpo, ativo, criado_por) VALUES (?,?,?,?,?)',
            [$nome, $assunto, $corpo, !empty($dados['ativo']) ? 1 : 0, Auth::id()]
        );
        $novo = Db::ultimoId();
        Auditoria::registrar('modelo_criado', 'modelo', (string) $novo, $nome);
        return $novo;
    }

    public static function excluir(int $id): void
    {
        Db::executar('DELETE FROM modelos WHERE id = ?', [$id]);
        Auditoria::registrar('modelo_excluido', 'modelo', (string) $id);
    }
}
