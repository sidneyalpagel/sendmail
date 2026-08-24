<?php
declare(strict_types=1);

/**
 * Anexos dos envios.
 *
 * Os arquivos vivem em private/anexos/<campanha>/, fora do alcance do
 * navegador, e seguem em todas as mensagens da campanha. O deploy preserva
 * o diretório, como já faz com config.php e logs.
 */
class Anexos
{
    /** Soma máxima por envio, em bytes. Mensagens maiores são recusadas
     *  por muitos provedores — e multiplicam o tráfego pela lista inteira. */
    public const LIMITE_TOTAL = 10 * 1024 * 1024;

    /** Extensões aceitas: documentos e imagens, nada executável. */
    private const EXTENSOES = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'csv', 'txt',
    ];

    public static function dir(int $campanhaId): string
    {
        return RAIZ . '/anexos/' . $campanhaId;
    }

    public static function listar(int $campanhaId): array
    {
        return Db::todos('SELECT * FROM anexos WHERE campanha_id = ? ORDER BY id', [$campanhaId]);
    }

    public static function tamanhoTotal(int $campanhaId): int
    {
        return (int) Db::valor(
            'SELECT COALESCE(SUM(tamanho), 0) FROM anexos WHERE campanha_id = ?',
            [$campanhaId]
        );
    }

    /** Caminho absoluto de um anexo em disco. */
    public static function caminho(array $anexo): string
    {
        return RAIZ . '/anexos/' . $anexo['arquivo'];
    }

    /** Recebe um item de $_FILES já desmembrado (name/tmp_name/size/error). */
    public static function adicionar(int $campanhaId, array $arquivo): void
    {
        $nome = trim((string) ($arquivo['name'] ?? ''));

        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                "Falha ao receber o arquivo {$nome} — ele pode ter passado do tamanho que o servidor aceita."
            );
        }
        if (!is_uploaded_file((string) $arquivo['tmp_name'])) {
            throw new RuntimeException('Arquivo inválido.');
        }

        $extensao = mb_strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        if (!in_array($extensao, self::EXTENSOES, true)) {
            throw new RuntimeException(
                "O tipo do arquivo {$nome} não é aceito. Use documentos (PDF, Office) ou imagens."
            );
        }

        $tamanho = (int) $arquivo['size'];
        if ($tamanho < 1) {
            throw new RuntimeException("O arquivo {$nome} está vazio.");
        }
        if (self::tamanhoTotal($campanhaId) + $tamanho > self::LIMITE_TOTAL) {
            throw new RuntimeException(
                'Os anexos deste envio passariam de ' . self::legivel(self::LIMITE_TOTAL)
                . ' somados. Prefira publicar arquivos grandes no site e anexar só o essencial.'
            );
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $arquivo['tmp_name']);

        $dir = self::dir($campanhaId);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar o diretório de anexos.');
        }

        // Nome em disco gerado aqui: o nome vindo do navegador só é exibido.
        $emDisco = bin2hex(random_bytes(8)) . '.' . $extensao;
        if (!move_uploaded_file((string) $arquivo['tmp_name'], $dir . '/' . $emDisco)) {
            throw new RuntimeException("Não foi possível guardar o arquivo {$nome}.");
        }

        Db::executar(
            'INSERT INTO anexos (campanha_id, nome, arquivo, mime, tamanho) VALUES (?,?,?,?,?)',
            [$campanhaId, mb_substr($nome, 0, 190), $campanhaId . '/' . $emDisco, $mime, $tamanho]
        );
        Auditoria::registrar(
            'anexo_adicionado',
            'campanha',
            (string) $campanhaId,
            $nome . ' (' . self::legivel($tamanho) . ')'
        );
    }

    public static function remover(int $id, int $campanhaId): void
    {
        $anexo = Db::primeiro('SELECT * FROM anexos WHERE id = ? AND campanha_id = ?', [$id, $campanhaId]);
        if (!$anexo) {
            return;
        }
        @unlink(self::caminho($anexo));
        Db::executar('DELETE FROM anexos WHERE id = ?', [$anexo['id']]);
        Auditoria::registrar('anexo_removido', 'campanha', (string) $campanhaId, $anexo['nome']);
    }

    /** Apaga arquivos e registros junto com a campanha. */
    public static function removerTodos(int $campanhaId): void
    {
        foreach (self::listar($campanhaId) as $anexo) {
            @unlink(self::caminho($anexo));
        }
        @rmdir(self::dir($campanhaId));
        Db::executar('DELETE FROM anexos WHERE campanha_id = ?', [$campanhaId]);
    }

    public static function legivel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.') . ' KB';
        }
        return $bytes . ' B';
    }
}
