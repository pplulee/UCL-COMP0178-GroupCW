<?php

use Rakit\Validation\Validator;
use service\Mail\NullMail;
use service\Mail\Postal;
use service\Mail\Smtp;

function php_self(): string
{
    return substr($_SERVER['PHP_SELF'], strrpos($_SERVER['PHP_SELF'], '/') + 1);
}

function getPasswordMethod(): string
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

function validate(array $data, array $rules, array $messages): array
{
    $validator = new Validator();
    $validation = $validator->make($data, $rules);
    $validation->setMessages($messages);
    $validation->validate();
    if ($validation->fails()) {
        $errors = $validation->errors();
        return [
            'ret' => 0,
            'msg' => array_values($errors->firstOfAll())[0]
        ];
    } else {
        return [
            'ret' => 1,
            'msg' => 'Success'
        ];
    }
}

function getBaseUrl(): string
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
}

function getMailDriver(): NullMail|Postal|Smtp
{
    $mailDriver = env('email_driver');
    return match ($mailDriver) {
        'smtp' => new Smtp(),
        'postal' => new Postal(),
        default => new NullMail(),
    };
}

function sendmail(string $to, string $subject, string $template, array $variables): array
{
    if (empty($to)) {
        return [
            'msg' => 'Email address is empty',
            'ret' => 0
        ];
    }
    if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [
            'msg' => 'Email address is invalid',
            'ret' => 0
        ];
    }

    $templatePath = __DIR__ . '/../templates/emails/' . $template . '.html';
    if (! file_exists($templatePath)) {
        return [
            'msg' => 'Email template not found',
            'ret' => 0
        ];
    }

    $message = file_get_contents($templatePath);
    foreach ($variables as $key => $value) {
        $message = str_replace("{%$key%}", $value, $message);
    }

    $mailDriver = getMailDriver();
    return $mailDriver->send($to, $subject, $message);
}
