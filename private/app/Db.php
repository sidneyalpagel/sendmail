<?php
declare(strict_types=1);

/**
 * Acesso ao banco. Fina camada sobre PDO, sempre com consultas preparadas.
 */
class Db
{
    private static ?PDO $pdo = null;

    public static function iniciar(array $cfg): void
    {
        if (self::$pdo !== null) {
            return;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['porta'] ?? 3306,
            $cfg['nome']
        );
        self::$pdo = new PDO($dsn, $cfg['usuario'], $cfg['senha'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('Banco não inicializado.');
        }
        return self::$pdo;
    }

    public static function executar(string $sql, array $parametros = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt;
    }

    public static function todos(string $sql, array $parametros = []): array
    {
        return self::executar($sql, $parametros)->fetchAll();
    }

    public static function primeiro(string $sql, array $parametros = []): ?array
    {
        $linha = self::executar($sql, $parametros)->fetch();
        return $linha === false ? null : $linha;
    }

    public static function valor(string $sql, array $parametros = [])
    {
        $valor = self::executar($sql, $parametros)->fetchColumn();
        return $valor === false ? null : $valor;
    }

    public static function ultimoId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function transacao(callable $bloco)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $resultado = $bloco();
            $pdo->commit();
            return $resultado;
        } catch (Throwable $erro) {
            $pdo->rollBack();
            throw $erro;
        }
    }
}
