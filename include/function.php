<?php

use Rakit\Validation\Validator;

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
