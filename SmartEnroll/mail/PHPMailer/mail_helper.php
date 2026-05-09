<?php

require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class SmartenrollMailer extends PHPMailer
{
    public function addEmbeddedImageWithoutName(
        string $path,
        string $cid,
        string $encoding = self::ENCODING_BASE64,
        string $type = '',
        string $disposition = 'inline'
    ): bool {
        $added = $this->addEmbeddedImage($path, $cid, ' ', $encoding, $type, $disposition);
        if ($added && !empty($this->attachment)) {
            $lastIndex = array_key_last($this->attachment);
            if ($lastIndex !== null) {
                $this->attachment[$lastIndex][2] = '';
            }
        }

        return $added;
    }
}

function get_email_config(): array
{
    $configPath = __DIR__ . '/email_config.php';
    if (!is_file($configPath)) {
        return [];
    }

    $config = require $configPath;
    return is_array($config) ? $config : [];
}

function smartenroll_mail_domain_from_email(string $email): string
{
    $parts = explode('@', $email, 2);
    return isset($parts[1]) ? strtolower(trim($parts[1])) : '';
}

function smartenroll_mail_resolve_path(string $path): string
{
    $trimmedPath = trim($path);
    if ($trimmedPath === '') {
        return '';
    }

    if (preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\)/', $trimmedPath) === 1) {
        return $trimmedPath;
    }

    return __DIR__ . DIRECTORY_SEPARATOR . ltrim(
        str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmedPath),
        DIRECTORY_SEPARATOR
    );
}

function smtp_send_mail(string $to, string $subject, string $htmlBody, string $textBody, ?string &$error = null, array $embeddedImages = []): bool
{
    $config = get_email_config();

    $host = trim((string)($config['host'] ?? ''));
    $port = (int)($config['port'] ?? 0);
    $encryption = strtolower(trim((string)($config['encryption'] ?? 'ssl')));
    $username = trim((string)($config['username'] ?? ''));
    $password = str_replace(' ', '', (string)($config['password'] ?? ''));
    $fromEmail = trim((string)($config['from_email'] ?? ''));
    $fromName = trim((string)($config['from_name'] ?? 'SMARTENROLL'));
    $replyToEmail = trim((string)($config['reply_to_email'] ?? $fromEmail));
    $replyToName = trim((string)($config['reply_to_name'] ?? $fromName));
    $hostname = trim((string)($config['hostname'] ?? ''));
    $senderDomain = smartenroll_mail_domain_from_email($fromEmail);
    $messageIdDomain = trim((string)($config['message_id_domain'] ?? $senderDomain));
    $dkimDomain = trim((string)($config['dkim_domain'] ?? $senderDomain));
    $dkimSelector = trim((string)($config['dkim_selector'] ?? ''));
    $dkimPrivateKeyPath = smartenroll_mail_resolve_path((string)($config['dkim_private_key_path'] ?? ''));
    $dkimPassphrase = (string)($config['dkim_passphrase'] ?? '');
    $dkimIdentity = trim((string)($config['dkim_identity'] ?? $fromEmail));

    if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
        $error = 'PHPMailer SMTP is not configured yet. Update email_config.php with your sender email and app password.';
        return false;
    }

    $mail = null;

    try {
        $mail = new SmartenrollMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = PHPMailer::ENCODING_BASE64;
        $mail->Timeout = 20;

        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        if ($hostname !== '') {
            $mail->Hostname = $hostname;
            $mail->Helo = $hostname;
        }

        if ($messageIdDomain !== '') {
            $mail->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $messageIdDomain);
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->Sender = $fromEmail;
        $mail->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $fromName);
        $mail->addAddress($to);
        $mail->XMailer = 'SMARTENROLL Mailer';
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;

        if ($dkimDomain !== '' && $dkimSelector !== '' && $dkimPrivateKeyPath !== '' && is_file($dkimPrivateKeyPath)) {
            $mail->DKIM_domain = $dkimDomain;
            $mail->DKIM_selector = $dkimSelector;
            $mail->DKIM_private = $dkimPrivateKeyPath;
            $mail->DKIM_passphrase = $dkimPassphrase;
            $mail->DKIM_identity = $dkimIdentity !== '' ? $dkimIdentity : $fromEmail;
        }

        foreach ($embeddedImages as $image) {
            $path = (string)($image['path'] ?? '');
            $cid = (string)($image['cid'] ?? '');
            $name = (string)($image['name'] ?? basename($path));

            if ($path !== '' && $cid !== '' && is_file($path)) {
                if ($name === '') {
                    $mail->addEmbeddedImageWithoutName($path, $cid);
                } else {
                    $mail->addEmbeddedImage($path, $cid, $name);
                }
            }
        }

        return $mail->send();
    } catch (Exception $e) {
        $errorMessage = $mail instanceof PHPMailer && $mail->ErrorInfo !== ''
            ? $mail->ErrorInfo
            : $e->getMessage();
        $error = 'PHPMailer error: ' . $errorMessage;
        return false;
    } catch (Throwable $e) {
        $error = 'Mail setup error: ' . $e->getMessage();
        return false;
    }
}
