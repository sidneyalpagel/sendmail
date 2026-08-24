<?php
declare(strict_types=1);

/**
 * Trilha de auditoria. Toda ação que altera dados ou envia mensagem passa aqui.
 */
class Auditoria
{
    public static function registrar(
        string $acao,
        ?string $entidade = null,
        ?string $entidadeId = null,
        ?string $detalhe = null,
        ?int $operadorId = null
    ): void {
        $id   = $operadorId ?? (class_exists('Auth') ? Auth::id() : null);
        $nome = $id ? Auth::nome() : 'sistema';

        try {
            Db::executar(
                'INSERT INTO auditoria (operador_id, operador_nome, acao, entidade, entidade_id, detalhe, ip)
                 VALUES (?,?,?,?,?,?,?)',
                [$id, $nome, $acao, $entidade, $entidadeId, $detalhe ? mb_substr($detalhe, 0, 500) : null, ip()]
            );
        } catch (Throwable $erro) {
            // Auditoria nunca derruba a operação principal.
            registrar('falha ao gravar auditoria: ' . $erro->getMessage());
        }
    }

    public static function listar(int $limite = 200): array
    {
        return Db::todos('SELECT * FROM auditoria ORDER BY id DESC LIMIT ' . max(1, $limite));
    }
}
