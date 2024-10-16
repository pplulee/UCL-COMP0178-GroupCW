<?php

class User
{
    private string $username;
    private string $email;
    private string $password;
    private string $role;
    private string $uuid;

    public function __construct()
    {
        $this->username = '';
        $this->email = '';
        $this->password = '';
        $this->role = '';
        $this->uuid = '';
    }

    public function register(array $data): array
    {
        $this->username = $data['username'];
        $this->email = $data['email'];
        $this->password = password_hash($data['password'], getPasswordMethod());
        $this->role = $data['role'];
        $this->uuid = uniqid();
        if (! in_array($this->role, ['SELLER', 'BUYER'])) {
            return [
                'ret' => '0',
                'msg' => 'Invalid role'
            ];
        }
        global $conn;
        // Check if the user already exists
        $stmt = $conn->prepare('SELECT * FROM user WHERE username = :username OR email = :email');
        $stmt->execute([
            'username' => $this->username,
            'email' => $this->email
        ]);
        $user = $stmt->fetch();
        if ($user) {
            return [
                'ret' => '0',
                'msg' => 'User already exists'
            ];
        }
        $stmt = $conn->prepare('INSERT INTO user (username, email, password, role, uuid, created_at) VALUES (:username, :email, :password, :role, :uuid, :created_at)');
        $stmt->execute([
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'role' => $this->role,
            'uuid' => $this->uuid,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        return [
            'ret' => '1',
            'msg' => 'User registered successfully'
        ];
    }
}