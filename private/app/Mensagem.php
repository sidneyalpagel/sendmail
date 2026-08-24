<?php
declare(strict_types=1);

/**
 * Monta a mensagem final: substitui as variáveis, aplica a moldura
 * institucional e gera a versão em texto puro.
 */
class Mensagem
{
    /** Variáveis oferecidas ao operador na tela de redação. */
    public const VARIAVEIS = [
        '{{nome}}'              => 'Nome completo do destinatário',
        '{{primeiro_nome}}'     => 'Apenas o primeiro nome',
        '{{email}}'             => 'E-mail do destinatário',
        '{{bairro}}'            => 'Bairro cadastrado',
        '{{data}}'              => 'Data do envio, por extenso',
        '{{link_descadastro}}'  => 'Endereço que cancela o recebimento',
    ];

    /** Substitui as variáveis do corpo/assunto pelos dados do destinatário. */
    public static function preencher(string $texto, array $destinatario): string
    {
        $nome = trim((string) ($destinatario['nome'] ?? ''));
        $partes = preg_split('/\s+/u', $nome) ?: [''];

        $valores = [
            '{{nome}}'             => $nome,
            '{{primeiro_nome}}'    => $partes[0] ?? $nome,
            '{{email}}'            => (string) ($destinatario['email'] ?? ''),
            '{{bairro}}'           => (string) ($destinatario['bairro'] ?? ''),
            '{{data}}'             => self::dataPorExtenso(),
            '{{link_descadastro}}' => !empty($destinatario['contato_id'])
                ? linkDescadastro((int) $destinatario['contato_id'])
                : rtrim((string) config('app.url_base'), '/') . '/descadastro.php',
        ];

        return strtr($texto, $valores);
    }

    /** Envolve o corpo escrito pelo operador na moldura institucional. */
    public static function moldura(string $corpoHtml, array $destinatario): string
    {
        $orgao  = e((string) config('app.orgao'));
        $rodape = e((string) config('app.rodape'));
        $link   = !empty($destinatario['contato_id'])
            ? linkDescadastro((int) $destinatario['contato_id'])
            : '#';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$orgao}</title>
</head>
<body style="margin:0;padding:0;background:#eef1f0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f0;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
         style="max-width:600px;background:#ffffff;border:1px solid #d3dad8;border-radius:4px;">
    <tr>
      <td style="background:#14202b;padding:18px 24px;">
        <div style="font:600 11px/1.4 Arial,Helvetica,sans-serif;letter-spacing:.12em;
                    text-transform:uppercase;color:#8fb8ad;">Comunicado oficial</div>
        <div style="font:700 17px/1.4 Arial,Helvetica,sans-serif;color:#ffffff;margin-top:4px;">{$orgao}</div>
      </td>
    </tr>
    <tr>
      <td style="padding:28px 24px;font:400 15px/1.65 Arial,Helvetica,sans-serif;color:#22303a;">
        {$corpoHtml}
      </td>
    </tr>
    <tr>
      <td style="border-top:1px solid #e4e8e7;padding:18px 24px;
                 font:400 12px/1.6 Arial,Helvetica,sans-serif;color:#66757e;">
        {$rodape}
        <div style="margin-top:10px;">
          <a href="{$link}" style="color:#66757e;">Cancelar o recebimento destas mensagens</a>
        </div>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    /** Versão em texto puro, para clientes que não exibem HTML. */
    public static function texto(string $html): string
    {
        $texto = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $texto = preg_replace('/<br\s*\/?>/i', "\n", $texto) ?? $texto;
        $texto = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $texto) ?? $texto;
        $texto = preg_replace('/<li[^>]*>/i', '- ', $texto) ?? $texto;
        $texto = strip_tags($texto);
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace("/[ \t]+/", ' ', $texto) ?? $texto;
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto) ?? $texto;
        return trim($texto);
    }

    /** Remove marcação perigosa do que o operador digitou. */
    public static function limpar(string $html): string
    {
        $html = preg_replace('/<(script|style|iframe|object|embed|form)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
        return $html;
    }

    private static function dataPorExtenso(): string
    {
        $meses = ['janeiro','fevereiro','março','abril','maio','junho',
                  'julho','agosto','setembro','outubro','novembro','dezembro'];
        return date('j') . ' de ' . $meses[(int) date('n') - 1] . ' de ' . date('Y');
    }
}
