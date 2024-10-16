<?php

use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;
use voku\helper\AntiXSS;

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
        $antiXss = new AntiXSS();
        $data = $antiXss->xss_clean($data);
        // Data validation
        $result = validate($data, [
            'username' => 'required|min:5|max:255',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'confirm_password' => 'required|same:password',
            'role' => 'required|in:SELLER,BUYER'
        ], [
            'username:required' => 'Username is required',
            'username:min' => 'Username must be at least 6 characters',
            'username:max' => 'Username must not exceed 255 characters',
            'email:required' => 'Email is required',
            'email:email' => 'Email is invalid',
            'password:required' => 'Password is required',
            'password:min' => 'Password must be at least 6 characters',
            'confirm_password:required' => 'Confirm password is required',
            'confirm_password:same' => 'Passwords do not match',
            'role:required' => 'Role is required',
            'role:in' => 'Invalid role'
        ]);
        if ($result['ret'] === 0) {
            echo json_encode($result);
            exit();
        }
        $this->username = $data['username'];
        $this->email = $data['email'];
        $this->password = password_hash($data['password'], getPasswordMethod());
        $this->role = $data['role'];
        $this->uuid = Uuid::uuid4()->toString();
        if (! in_array($this->role, ['SELLER', 'BUYER'])) {
            return [
                'ret' => 0,
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
                'ret' => 0,
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
            'ret' => 1,
            'msg' => 'User registered successfully'
        ];
    }

    public function login(array $data): array
    {
        $antiXss = new AntiXSS();
        $antiXss->xss_clean($data);
        // Data validation
        $result = validate($data, [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ], [
            'email:required' => 'Email is required',
            'email:email' => 'Email is invalid',
            'password:required' => 'Password is required',
            'password:min' => 'Password must be at least 6 characters'
        ]);
        if ($result['ret'] === 0) {
            echo json_encode($result);
            exit();
        }
        $this->email = $data['email'];
        $this->password = $data['password'];
        global $conn;
        // Check user exist
        $stmt = $conn->prepare('SELECT * FROM user WHERE email = :email');
        $stmt->execute([
            'email' => $this->email
        ]);
        $user = $stmt->fetch();
        if (! $user) {
            return [
                'ret' => 0,
                'msg' => 'Invalid email or password'
            ];
        }
        if (! password_verify($this->password, $user['password'])) {
            return [
                'ret' => 0,
                'msg' => 'Invalid email or password'
            ];
        }
        if ($data['rememberMe'] === 'true') {
            // Set cookie using JWT
            $payload = [
                'exp' => time() + 2592000,
                'nbf' => time(),
                'iat' => time(),
                'uuid' => $user['uuid']
            ];
            $jwt = JWT::encode($payload, hash('sha256', $user['password']), 'HS256');
            setcookie('user', $jwt, time() + 2592000, '/');
        }
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        return [
            'ret' => 1,
            'msg' => 'Login successful'
        ];
    }
}