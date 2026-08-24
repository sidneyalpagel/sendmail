<?php
declare(strict_types=1);

/**
 * Autenticação dos operadores do sistema.
 */
class Auth
{
    /** Proteção contra força bruta: falhas na janela que bloqueiam o login. */
    public const BLOQUEIO_FALHAS  = 10;
    public const BLOQUEIO_MINUTOS = 15;

    /**
     * Muitas falhas recentes vindas deste IP ou contra este login?
     *
     * Conta os "login_negado" da própria auditoria — sem tabela nova. A
     * janela expira sozinha: tentativas bloqueadas geram "login_bloqueado",
     * que não entra na conta, então o bloqueio não se renova a cada batida.
     */
    public static function bloqueadoTemporariamente(string $login): bool
    {
        $falhas = (int) Db::valor(
            'SELECT COUNT(*) FROM auditoria
              WHERE acao = "login_negado"
                AND criado_em > DATE_SUB(NOW(), INTERVAL ' . self::BLOQUEIO_MINUTOS . ' MINUTE)
                AND (ip = ? OR entidade_id = ?)',
            [ip(), $login]
        );
        return $falhas >= self::BLOQUEIO_FALHAS;
    }

    public static function entrar(string $login, string $senha): bool
    {
        $operador = Db::primeiro(
            'SELECT * FROM operadores WHERE login = ? AND ativo = 1',
            [$login]
        );

        // Compara sempre, mesmo sem operador, para não vazar quais logins existem.
        $hash = $operador['senha_hash'] ?? '$2y$12$invalidoinvalidoinvalidoinvalidoinvalidoinvalidoinvalido';
        if (!password_verify($senha, $hash) || !$operador) {
            Auditoria::registrar('login_negado', 'operador', $login, null, null);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['operador'] = [
            'id'    => (int) $operador['id'],
            'nome'  => $operador['nome'],
            'login' => $operador['login'],
            'email' => $operador['email'],
            'papel' => $operador['papel'],
        ];
        $_SESSION['visto_em'] = time();

        Db::executar('UPDATE operadores SET ultimo_acesso = NOW() WHERE id = ?', [$operador['id']]);
        Auditoria::registrar('login', 'operador', (string) $operador['id']);
        return true;
    }

    public static function sair(): void
    {
        Auditoria::registrar('logout', 'operador', (string) (self::id() ?? ''));
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function operador(): ?array
    {
        return $_SESSION['operador'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['operador']['id'] ?? null;
    }

    public static function nome(): string
    {
        return $_SESSION['operador']['nome'] ?? 'sistema';
    }

    public static function eAdmin(): bool
    {
        return ($_SESSION['operador']['papel'] ?? '') === 'admin';
    }

    /** Bloqueia a página para quem não estiver autenticado. */
    public static function exigir(): void
    {
        $limite = (int) config('app.timeout_sessao', 3600);
        if (!self::operador()) {
            irPara('?p=login');
        }
        if (time() - (int) ($_SESSION['visto_em'] ?? 0) > $limite) {
            self::sair();
            session_start();
            aviso('Sessão expirada por inatividade. Entre novamente.', 'erro');
            irPara('?p=login');
        }
        $_SESSION['visto_em'] = time();
    }

    public static function exigirAdmin(): void
    {
        self::exigir();
        if (!self::eAdmin()) {
            http_response_code(403);
            exit('Esta área é restrita a administradores.');
        }
    }

    public static function criar(string $nome, string $login, string $email, string $senha, string $papel = 'operador'): int
    {
        Db::executar(
            'INSERT INTO operadores (nome, login, email, senha_hash, papel) VALUES (?,?,?,?,?)',
            [$nome, $login, $email, password_hash($senha, PASSWORD_DEFAULT), $papel]
        );
        return Db::ultimoId();
    }
}
