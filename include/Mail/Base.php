<?php
declare (strict_types=1);

namespace service\Mail;

abstract class Base
{
    abstract public function send($to, $subject, $text): array;
}
