<?php
include_once "include/common.php";
//TODO - Cookie Login
if ((! $_SESSION['logged_in']) && (! in_array(php_self(), array("index.php", "login.php", "register.php", "mfa.php")))) {
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
                <?php if ($_SESSION['logged_in'] === true): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                        <span class="nav-link-title">
                            <i class="fas fa-user"></i> My Profile
                        </span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php if ($_SESSION['role'] === 'ADMIN'): ?>
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

<!--<nav class="navbar navbar-expand-lg navbar-dark bg-dark">-->
<!--    <ul class="navbar-nav align-middle">-->
<!--        <li class="nav-item mx-1">-->
<!--            <a class="nav-link" href="browse.php">Browse</a>-->
<!--        </li>-->
<!--        --><?php
//        if (isset($_SESSION['account_type']) && $_SESSION['account_type'] == 'buyer') {
//            echo('
//	<li class="nav-item mx-1">
//      <a class="nav-link" href="mybids.php">My Bids</a>
//    </li>
//	<li class="nav-item mx-1">
//      <a class="nav-link" href="recommendations.php">Recommended</a>
//    </li>');
//        }
//        if (isset($_SESSION['account_type']) && $_SESSION['account_type'] == 'seller') {
//            echo('
//	<li class="nav-item mx-1">
//      <a class="nav-link" href="mylistings.php">My Listings</a>
//    </li>
//	<li class="nav-item ml-3">
//      <a class="nav-link btn border-light" href="create_auction.php">+ Create auction</a>
//    </li>');
//        }
//        ?>
<!--    </ul>-->
<!--</nav>-->