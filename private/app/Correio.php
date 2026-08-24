<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Camada de transporte. Mantém uma única conexão SMTP autenticada aberta
 * durante todo o ciclo do worker, em vez de reconectar a cada mensagem.
 */
class Correio
{
    private PHPMailer $mailer;
    private bool $conexaoPersistente;

    public function __construct(bool $conexaoPersistente = true)
    {
        $this->conexaoPersistente = $conexaoPersistente;
        $this->mailer = new PHPMailer(true);
        $this->configurar();
    }

    private function configurar(): void
    {
        $m = $this->mailer;
        $m->isSMTP();
        // O host é ajustável pela interface (parâmetro smtp_host); vazio
        // significa usar o do config.php. Os demais dados ficam no arquivo.
        $m->Host        = parametro('smtp_host', '') ?: (string) config('smtp.host');
        $m->Port        = (int) config('smtp.porta', 587);
        $m->SMTPAuth    = true;
        $m->Username    = (string) config('smtp.usuario');
        $m->Password    = (string) config('smtp.senha');
        $m->CharSet     = PHPMailer::CHARSET_UTF8;
        $m->Encoding    = PHPMailer::ENCODING_BASE64;
        $m->Timeout     = 30;
        $m->SMTPKeepAlive = $this->conexaoPersistente;

        $seguranca = (string) config('smtp.seguranca', 'tls');
        if ($seguranca === 'tls') {
            $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($seguranca === 'ssl') {
            $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $m->SMTPSecure  = '';
            $m->SMTPAutoTLS = false;
        }

        if (!config('smtp.verificar_certificado', true)) {
            $m->SMTPOptions = ['ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]];
        }

        $m->setFrom((string) config('smtp.remetente_email'), (string) config('smtp.remetente_nome'));

        $respostaEmail = (string) config('smtp.responder_para_email', '');
        if ($respostaEmail !== '') {
            $m->addReplyTo($respostaEmail, (string) config('smtp.responder_para_nome', ''));
        }
    }

    /**
     * Envia uma mensagem já renderizada.
     *
     * @param array $destinatario ['nome','email','bairro','contato_id']
     * @param array $anexos linhas da tabela anexos a incluir na mensagem
     * @return string Message-ID atribuído
     * @throws RuntimeException em caso de falha
     */
    public function enviar(array $destinatario, string $assunto, string $corpoHtml, array $anexos = []): string
    {
        $m = $this->mailer;
        $m->clearAddresses();
        $m->clearCustomHeaders();
        $m->clearAttachments();

        try {
            $m->addAddress($destinatario['email'], $destinatario['nome'] ?? '');
            foreach ($anexos as $anexo) {
                $m->addAttachment(Anexos::caminho($anexo), $anexo['nome']);
            }
        } catch (PHPMailerException $erro) {
            throw new RuntimeException('Endereço ou anexo recusado: ' . $erro->getMessage());
        }

        $assuntoFinal = Mensagem::preencher($assunto, $destinatario);
        $corpoFinal   = Mensagem::preencher($corpoHtml, $destinatario);
        $htmlCompleto = Mensagem::moldura($corpoFinal, $destinatario);

        $m->Subject = $assuntoFinal;
        $m->isHTML(true);
        $m->Body    = $htmlCompleto;
        $m->AltBody = Mensagem::texto($corpoFinal) . "\n\n--\n" . config('app.rodape');

        // Cabeçalhos que evitam que a mensagem seja tratada como spam
        // e que permitem descadastro pelo próprio cliente de e-mail.
        if (!empty($destinatario['contato_id'])) {
            $link = linkDescadastro((int) $destinatario['contato_id']);
            $m->addCustomHeader('List-Unsubscribe', '<' . $link . '>');
            $m->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
        $m->addCustomHeader('Auto-Submitted', 'auto-generated');
        $m->addCustomHeader('X-Origem', 'sendmail.santahelena.pr.gov.br');

        try {
            if (!$m->send()) {
                throw new RuntimeException($m->ErrorInfo ?: 'falha desconhecida no envio');
            }
        } catch (PHPMailerException $erro) {
            throw new RuntimeException($erro->getMessage());
        }

        return $m->getLastMessageID();
    }

    /** Confere credenciais e conectividade sem enviar mensagem. */
    public function testarConexao(): array
    {
        $smtp = new SMTP();
        $smtp->do_debug = SMTP::DEBUG_OFF;

        $host      = parametro('smtp_host', '') ?: (string) config('smtp.host');
        $porta     = (int) config('smtp.porta', 587);
        $seguranca = (string) config('smtp.seguranca', 'tls');
        $prefixo   = $seguranca === 'ssl' ? 'ssl://' : '';

        try {
            if (!$smtp->connect($prefixo . $host, $porta, 15)) {
                return [false, 'não foi possível abrir conexão com ' . $host . ':' . $porta];
            }
            if (!$smtp->hello(gethostname() ?: 'localhost')) {
                return [false, 'servidor recusou o EHLO'];
            }
            if ($seguranca === 'tls') {
                if (!$smtp->startTLS()) {
                    return [false, 'servidor não aceitou STARTTLS'];
                }
                $smtp->hello(gethostname() ?: 'localhost');
            }
            if (!$smtp->authenticate((string) config('smtp.usuario'), (string) config('smtp.senha'))) {
                return [false, 'autenticação recusada: ' . $smtp->getError()['error']];
            }
            $smtp->quit();
            return [true, 'conexão e autenticação confirmadas em ' . $host . ':' . $porta];
        } catch (Throwable $erro) {
            return [false, $erro->getMessage()];
        }
    }

    public function encerrar(): void
    {
        $this->mailer->smtpClose();
    }
}
