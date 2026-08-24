<?php
declare(strict_types=1);

/**
 * Anexos de envios e de modelos.
 *
 * Os arquivos vivem em private/anexos/, fora do alcance do navegador:
 * campanhas em <id>/, modelos em m<id>/. O deploy preserva o diretório,
 * como já faz com config.php e logs.
 *
 * Um modelo com anexos os transfere para cada envio criado a partir dele;
 * dali em diante o envio tem cópia própria — editar o modelo depois não
 * altera envios já criados, o mesmo congelamento que o texto tem.
 */
class Anexos
{
    /** Soma máxima por envio ou modelo, em bytes. Mensagens maiores são
     *  recusadas por muitos provedores — e multiplicam pelo tamanho da lista. */
    public const LIMITE_TOTAL = 10 * 1024 * 1024;

    /** Extensões aceitas: documentos e imagens, nada executável. */
    private const EXTENSOES = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'csv', 'txt',
    ];

    // -----------------------------------------------------------------
    // Anexos de campanhas
    // -----------------------------------------------------------------

    public static function listar(int $campanhaId): array
    {
        return Db::todos('SELECT * FROM anexos WHERE campanha_id = ? ORDER BY id', [$campanhaId]);
    }

    public static function adicionar(int $campanhaId, array $arquivo): void
    {
        self::guardar('campanha_id', $campanhaId, $arquivo);
    }

    public static function remover(int $id, int $campanhaId): void
    {
        self::apagar('campanha_id', $campanhaId, $id);
    }

    public static function removerTodos(int $campanhaId): void
    {
        self::apagarTodos('campanha_id', $campanhaId);
    }

    // -----------------------------------------------------------------
    // Anexos de modelos
    // -----------------------------------------------------------------

    public static function listarDoModelo(int $modeloId): array
    {
        return Db::todos('SELECT * FROM anexos WHERE modelo_id = ? ORDER BY id', [$modeloId]);
    }

    public static function adicionarAoModelo(int $modeloId, array $arquivo): void
    {
        self::guardar('modelo_id', $modeloId, $arquivo);
    }

    public static function removerDoModelo(int $id, int $modeloId): void
    {
        self::apagar('modelo_id', $modeloId, $id);
    }

    public static function removerTodosDoModelo(int $modeloId): void
    {
        self::apagarTodos('modelo_id', $modeloId);
    }

    // -----------------------------------------------------------------
    // Cópias entre modelo e campanha
    // -----------------------------------------------------------------

    /** Um envio novo criado a partir de um modelo herda os anexos dele. */
    public static function copiarModeloParaCampanha(int $modeloId, int $campanhaId): int
    {
        return self::copiar(self::listarDoModelo($modeloId), 'campanha_id', $campanhaId);
    }

    /** "Salvar como modelo": o modelo novo leva os anexos do envio. */
    public static function copiarCampanhaParaModelo(int $campanhaId, int $modeloId): int
    {
        return self::copiar(self::listar($campanhaId), 'modelo_id', $modeloId);
    }

    // -----------------------------------------------------------------
    // Comuns
    // -----------------------------------------------------------------

    /** Caminho absoluto de um anexo em disco. */
    public static function caminho(array $anexo): string
    {
        return RAIZ . '/anexos/' . $anexo['arquivo'];
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

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private static function dirDe(string $coluna, int $donoId): string
    {
        return RAIZ . '/anexos/' . ($coluna === 'modelo_id' ? 'm' : '') . $donoId;
    }

    private static function entidadeDe(string $coluna): string
    {
        return $coluna === 'modelo_id' ? 'modelo' : 'campanha';
    }

    private static function tamanhoTotalDe(string $coluna, int $donoId): int
    {
        return (int) Db::valor(
            "SELECT COALESCE(SUM(tamanho), 0) FROM anexos WHERE {$coluna} = ?",
            [$donoId]
        );
    }

    private static function guardar(string $coluna, int $donoId, array $arquivo): void
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
        if (self::tamanhoTotalDe($coluna, $donoId) + $tamanho > self::LIMITE_TOTAL) {
            throw new RuntimeException(
                'Os anexos passariam de ' . self::legivel(self::LIMITE_TOTAL)
                . ' somados. Prefira publicar arquivos grandes no site e anexar só o essencial.'
            );
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $arquivo['tmp_name']);

        $dir = self::dirDe($coluna, $donoId);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar o diretório de anexos.');
        }

        // Nome em disco gerado aqui: o nome vindo do navegador só é exibido.
        $emDisco = bin2hex(random_bytes(8)) . '.' . $extensao;
        if (!move_uploaded_file((string) $arquivo['tmp_name'], $dir . '/' . $emDisco)) {
            throw new RuntimeException("Não foi possível guardar o arquivo {$nome}.");
        }

        Db::executar(
            "INSERT INTO anexos ({$coluna}, nome, arquivo, mime, tamanho) VALUES (?,?,?,?,?)",
            [$donoId, mb_substr($nome, 0, 190),
             basename($dir) . '/' . $emDisco, $mime, $tamanho]
        );
        Auditoria::registrar(
            'anexo_adicionado',
            self::entidadeDe($coluna),
            (string) $donoId,
            $nome . ' (' . self::legivel($tamanho) . ')'
        );
    }

    private static function apagar(string $coluna, int $donoId, int $id): void
    {
        $anexo = Db::primeiro("SELECT * FROM anexos WHERE id = ? AND {$coluna} = ?", [$id, $donoId]);
        if (!$anexo) {
            return;
        }
        @unlink(self::caminho($anexo));
        Db::executar('DELETE FROM anexos WHERE id = ?', [$anexo['id']]);
        Auditoria::registrar('anexo_removido', self::entidadeDe($coluna), (string) $donoId, $anexo['nome']);
    }

    private static function apagarTodos(string $coluna, int $donoId): void
    {
        foreach (Db::todos("SELECT * FROM anexos WHERE {$coluna} = ?", [$donoId]) as $anexo) {
            @unlink(self::caminho($anexo));
        }
        @rmdir(self::dirDe($coluna, $donoId));
        Db::executar("DELETE FROM anexos WHERE {$coluna} = ?", [$donoId]);
    }

    /** Copia arquivos e registros para outro dono. @return int quantos copiou */
    private static function copiar(array $anexos, string $colunaDestino, int $donoId): int
    {
        $copiados = 0;
        foreach ($anexos as $anexo) {
            $origem = self::caminho($anexo);
            if (!is_file($origem)) {
                continue;
            }
            $dir = self::dirDe($colunaDestino, $donoId);
            if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                throw new RuntimeException('Não foi possível criar o diretório de anexos.');
            }
            $extensao = pathinfo($anexo['arquivo'], PATHINFO_EXTENSION);
            $emDisco  = bin2hex(random_bytes(8)) . '.' . $extensao;
            if (!copy($origem, $dir . '/' . $emDisco)) {
                throw new RuntimeException('Não foi possível copiar o anexo ' . $anexo['nome'] . '.');
            }
            Db::executar(
                "INSERT INTO anexos ({$colunaDestino}, nome, arquivo, mime, tamanho) VALUES (?,?,?,?,?)",
                [$donoId, $anexo['nome'], basename($dir) . '/' . $emDisco, $anexo['mime'], $anexo['tamanho']]
            );
            $copiados++;
        }
        return $copiados;
    }
}
