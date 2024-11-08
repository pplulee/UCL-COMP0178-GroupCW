<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include_once "include/common.php";
global $conn;

// Cookie login
if ($_SESSION['logged_in'] === false && isset($_COOKIE['user']) && isset($_COOKIE['uuid'])) {
    $cookie = $_COOKIE['user'];
    $uuid = $_COOKIE['uuid'];
    $stmt = $conn->prepare('SELECT id,password,admin FROM user WHERE uuid = :uuid');
    $stmt->execute([
        'uuid' => $uuid
    ]);
    $user = $stmt->fetch();
    if (! $user) {
        setcookie('user', '', time() - 3600, '/');
        setcookie('uuid', '', time() - 3600, '/');
    } else {
        // Decode JWT
        try {
            $decoded = JWT::decode($cookie, new Key(hash('sha256', $user['password']), 'HS256'));
            if ($decoded->uuid !== $uuid) {
                setcookie('user', '', time() - 3600, '/');
                setcookie('uuid', '', time() - 3600, '/');
            } else {
                $_SESSION['logged_in'] = true;
                $_SESSION['admin'] = $user['admin'];
                $_SESSION['user_id'] = $user['id'];
            }
        } catch (Exception $e) {
            setcookie('user', '', time() - 3600, '/');
            setcookie('uuid', '', time() - 3600, '/');
        }
    }
}

if ((! $_SESSION['logged_in']) && (! in_array(php_self(), array("index.php", "login.php", "register.php", "mfa.php", "reset.php")))) {
    echo "<script>window.location.href='login.php';</script>"; // Redirect to login page
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <script src="https://fastly.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
    <link rel="stylesheet" href="https://fastly.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
    <link href="https://fastly.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet"/>
    <script src="https://fastly.jsdelivr.net/npm/sweetalert2@11.10.7/dist/sweetalert2.all.min.js"></script>
    <link href="https://fastly.jsdelivr.net/npm/sweetalert2@11.10.7/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <script src="https://fastly.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script src="https://unpkg.com/htmx.org@1.9.12"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/countdown@2.6.0/countdown.min.js"></script>
    <script src="assets/js/star-rating.min.js"></script>
    <link rel="stylesheet" href="assets/css/star-rating.min.css">
    <link rel="stylesheet" href="assets/css/tabler-vendors.min.css">
</head>

<body>
<header class="navbar navbar-expand-md">
    <div class="container-xl">
        <button class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu"
                aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="/">Site Name</a>
        <div class="collapse navbar-collapse" id="navbar-menu">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="browse.php">
                        <span class="nav-link-title">
                            <i class="fas fa-search"></i> Browse Items
                        </span>
                    </a>
                </li>
                <?php if ($_SESSION['logged_in'] === true): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="nav-link-title">
                            <i class="fas fa-store"></i> Seller Centre
                        </span>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="create_auction.php">Post Item</a></li>
                            <li><a class="dropdown-item" href="mylistings.php">My Listings</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <span class="nav-link-title">
                                <i class="fas fa-user"></i> My Profile
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php if ($_SESSION['admin']): ?>
                            <a class="nav-link" href="/admin">
                                <span class="nav-link-title">
                                    <i class="fas fa-gear"></i> Admin Panel
                                </span>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
            <button class="btn btn-danger order-md-last" hx-post="logout.php" hx-trigger="click" hx-swap="none">Logout
            </button>
        <?php else: ?>
            <a class="btn btn-primary order-md-last" href="login.php">Login</a>
        <?php endif; ?>
    </div>
</header>
