<?php
declare (strict_types=1);

namespace service\Mail;

use Exception;
use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;

final class Smtp extends Base
{
    public function __construct()
    {
    }

    public function send($to, $subject, $text): array
    {
        $mail = new Message();
        try {
            $mail->setFrom(env('email_smtp_from_address'), env('email_smtp_from_name'))
                ->addTo($to)
                ->setSubject($subject)
                ->setHtmlBody($text);
            $params = [
                'host' => env('email_smtp_host'),
                'username' => env('email_smtp_username'),
                'password' => env('email_smtp_password'),
                'port' => env('email_smtp_port'),
                'encryption' => env('email_smtp_encryption'),
                'timeout' => 30,
            ];

            $mailer = new SmtpMailer(...array_values($params));
            $mailer->send($mail);
            return [
                'msg' => "Email sent successfully",
                'ret' => 1
            ];
        } catch (Exception $e) {
            return [
                'msg' => "Email sending failed",
                'error' => $e->getMessage(),
                'ret' => 0
            ];
        }
    }
}