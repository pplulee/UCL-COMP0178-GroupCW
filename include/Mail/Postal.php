<?php
declare (strict_types=1);

namespace service\Mail;

use Exception;
use Postal\Client;
use Postal\Send\Message;

final class Postal extends Base
{
    public function __construct()
    {
    }

    public function send($to, $subject, $text): array
    {
        try {
            $client = new Client(env('email_postal_url'), env('email_postal_key'));
            $message = new Message();
            $message->to($to);
            $senderName = env('email_postal_from_name');
            $senderAddress = env('email_postal_from_address');
            $message->sender($senderAddress);
            $message->from("$senderName <$senderAddress>");
            $message->replyTo($senderAddress);
            $message->subject($subject);
            $message->htmlBody($text);
            $client->send->message($message);
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
