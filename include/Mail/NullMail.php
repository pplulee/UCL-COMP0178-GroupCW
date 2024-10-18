<?php
declare (strict_types=1);

namespace service\Mail;


final class NullMail extends Base
{
    public function __construct()
    {
    }

    public function send($to, $subject, $text): array
    {
        return [
            'msg' => "Email sent successfully",
            'ret' => 1
        ];
    }
}
