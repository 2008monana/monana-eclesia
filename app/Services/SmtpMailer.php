<?php
namespace App\Services;

use App\Core\Config;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Transporte SMTP com PHPMailer, carregado pelo autoload do Composer. */
final class SmtpMailer implements MailerInterface
{
    /** @var array<string,mixed> */
    private $configuration;

    public function __construct(array $configuration = [])
    {
        $this->configuration = $configuration !== []
            ? $configuration
            : (array) Config::get('mail', []);
    }

    public function send(string $recipient, string $subject, string $html, string $text): void
    {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new \RuntimeException('PHPMailer não está instalado. Envia vendor/ por FTP antes de activar SMTP.');
        }

        $host = trim((string) ($this->configuration['host'] ?? ''));
        $fromAddress = trim((string) ($this->configuration['from_address'] ?? ''));
        $fromName = trim((string) ($this->configuration['from_name'] ?? 'MonanaEclésia'));
        $port = (int) ($this->configuration['port'] ?? 587);
        if ($host === '' || $fromAddress === '' || $port < 1 || $port > 65535
            || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new \RuntimeException('A configuração SMTP ou o destinatário não são válidos.');
        }

        $mailerClass = 'PHPMailer\\PHPMailer\\PHPMailer';
        $mailer = new $mailerClass(true);
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = $port;
        $mailer->SMTPAuth = trim((string) ($this->configuration['username'] ?? '')) !== '';
        if ($mailer->SMTPAuth) {
            $mailer->Username = (string) $this->configuration['username'];
            $mailer->Password = (string) $this->configuration['password'];
        }

        $encryption = strtolower(trim((string) ($this->configuration['encryption'] ?? 'tls')));
        if ($encryption === 'ssl') {
            $mailer->SMTPSecure = 'ssl';
        } elseif ($encryption === 'tls') {
            $mailer->SMTPSecure = 'tls';
        } else {
            throw new \RuntimeException('MAIL_ENCRYPTION aceita apenas tls ou ssl.');
        }

        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($fromAddress, $fromName);
        $mailer->addAddress($recipient);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $html;
        $mailer->AltBody = $text;

        try {
            if (!$mailer->send()) {
                throw new \RuntimeException('O servidor SMTP não confirmou o envio.');
            }
        } catch (\Throwable $exception) {
            // O detalhe do transporte não regressa ao browser nem inclui credenciais.
            $error = trim((string) ($mailer->ErrorInfo ?? ''));
            throw new \RuntimeException(
                $error !== '' ? 'Falha SMTP: ' . substr($error, 0, 500) : 'Falha ao enviar email por SMTP.',
                0,
                $exception
            );
        }
    }
}
