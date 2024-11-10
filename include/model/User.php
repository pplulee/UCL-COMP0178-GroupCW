<?php

namespace model;

use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;
use voku\helper\AntiXSS;

class User
{
    public int $id;
    public string $username;
    public string $email;
    public string $uuid;
    public bool $admin;
    private string $password;
    public string $address;

    public function __construct()
    {
        $this->id = 0;
        $this->username = '';
        $this->email = '';
        $this->password = '';
        $this->uuid = '';
        $this->admin = false;
        $this->address = '';
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
            'address' => 'required'
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
            'address:required' => 'Address is required'
        ]);
        if ($result['ret'] === 0) {
            echo json_encode($result);
            exit();
        }
        $this->username = $data['username'];
        $this->email = $data['email'];
        $this->password = password_hash($data['password'], getPasswordMethod());
        $this->uuid = Uuid::uuid4()->toString();
        $this->address = htmlspecialchars($data['address']);
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
        $stmt = $conn->prepare('INSERT INTO user (username, email, password, uuid, created_at, address) VALUES (:username, :email, :password, :uuid, :created_at, :address)');
        $stmt->execute([
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'uuid' => $this->uuid,
            'created_at' => date('Y-m-d H:i:s'),
            'address' => $this->address
        ]);
        return [
            'ret' => 1,
            'msg' => 'User registered successfully'
        ];
    }

    public function fetch(int $id): User|null
    {
        global $conn;
        $stmt = $conn->prepare('SELECT * FROM user WHERE id = :id');
        $stmt->execute([
            'id' => $id
        ]);
        $user = $stmt->fetch();
        if (! $user) {
            return null;
        }
        $this->id = $user['id'];
        $this->username = $user['username'];
        $this->email = $user['email'];
        $this->uuid = $user['uuid'];
        $this->admin = $user['admin'];
        return $this;
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
        global $conn, $cache;
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
        $this->id = $user['id'];
        if ($this->checkMfaStatus()['require']) {
            $cache->set('mfa_userid_' . session_id(), $user['id'], 300);
            $cache->set('mfa_rememberme_' . session_id(), $data['rememberMe'] === 'true', 300);
            return [
                'ret' => 1,
                'msg' => 'Please complete two-factor authentication',
                'redir' => 'mfa.php'
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
            setcookie('uuid', $user['uuid'], time() + 2592000, '/');
        }
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['admin'] = $user['admin'];
        return [
            'ret' => 1,
            'msg' => 'Login successful',
            'redir' => 'index.php'
        ];
    }

    public function checkMfaStatus(): array
    {
        global $conn;
        $stmt = $conn->prepare('SELECT * FROM mfa_credential WHERE userid = :userid AND type = "fido"');
        $stmt->execute([
            'userid' => $this->id
        ]);
        $fido = $stmt->fetch();
        $stmt = $conn->prepare('SELECT * FROM mfa_credential WHERE userid = :userid AND type = "totp"');
        $stmt->execute([
            'userid' => $this->id
        ]);
        $totp = $stmt->fetch();
        if (! $fido && ! $totp) {
            return ['require' => false];
        } else {
            return ['require' => true, 'fido' => (bool) $fido, 'totp' => (bool) $totp];
        }
    }

    public function update(array $data): array
    {
        $antiXss = new AntiXSS();
        $data = $antiXss->xss_clean($data);
        // Data validation
        $result = validate($data, [
            'username' => 'required|min:5|max:255',
            'email' => 'required|email',
            'password' => 'min:6',
        ], [
            'username:required' => 'Username is required',
            'username:min' => 'Username must be at least 6 characters',
            'username:max' => 'Username must not exceed 255 characters',
            'email:required' => 'Email is required',
            'email:email' => 'Email is invalid',
            'password:min' => 'Password must be at least 6 characters',
        ]);
        if ($result['ret'] === 0) {
            return $result;
        }
        // Check if the user with same username or email exists
        global $conn;
        $stmt = $conn->prepare('SELECT * FROM user WHERE (username = :username OR email = :email) AND id != :id');
        $stmt->execute([
            'username' => $data['username'],
            'email' => $data['email'],
            'id' => $this->id
        ]);
        $user = $stmt->fetch();
        if ($user) {
            return [
                'ret' => 0,
                'msg' => 'User already exists'
            ];
        }
        $this->username = $data['username'];
        $this->email = $data['email'];
        if (isset($data['password'])) {
            $this->password = password_hash($data['password'], getPasswordMethod());
        }
        $stmt = $conn->prepare('UPDATE user SET username = :username, email = :email, password = :password WHERE id = :id');
        $stmt->execute([
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'id' => $this->id
        ]);
        return [
            'ret' => 1,
            'msg' => 'User updated successfully'
        ];

    }

    public function getPasswordSha256(): string
    {
        return hash('sha256', $this->password);
    }

    public function getMfaDevices(string $type): array
    {
        global $conn;
        $stmt = $conn->prepare('SELECT * FROM mfa_credential WHERE userid = :userid AND type = :type');
        $stmt->execute([
            'userid' => $this->id,
            'type' => $type
        ]);
        return $stmt->fetchAll();
    }
}