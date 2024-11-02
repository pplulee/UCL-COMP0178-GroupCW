<?php

use model\User;

include_once "include/common.php";
if ($_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        include "include/common.php";
        header('Content-Type: application/json');
        $user = new User();
        $result = $user->register($_POST);
        if ($result['ret'] === 1) {
            header('HX-Redirect: login.php');
        }
        echo json_encode($result);
        exit();
    case 'GET':
        include_once("header.php");
        break;
    default:
        http_response_code(405);
        exit();
}
?>
    <title><?= env('app_name') ?> - Register</title>
    <body class=" d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-4">Register</h2>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user"></i> Username</label>
                        <input class="form-control" id="username" placeholder="Please enter the username" required
                               type="text">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                        <input class="form-control" id="email" placeholder="Please enter your email address" required
                               type="email">
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><i class="fa-solid fa-lock"></i> Password</label>
                        <div class="input-group input-group-flat">
                            <input class="form-control" id="password" placeholder="Please enter the password"
                                   required
                                   type="password">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">
                            Repeat password
                        </label>
                        <div class="input-group input-group-flat">
                            <input class="form-control" id="confirm_password"
                                   placeholder="Please repeat the password" required type="password">
                        </div>
                    </div>
                    <div class="form-footer">
                        <button class="btn btn-primary w-100"
                                hx-post="register.php"
                                hx-swap="none"
                                hx-disabled-elt="button"
                                hx-vals='js:{
                                username: document.getElementById("username").value,
                                email: document.getElementById("email").value,
                                password: document.getElementById("password").value,
                                confirm_password: document.getElementById("confirm_password").value,
                            }'>
                            Register
                        </button>
                    </div>
                </div>
            </div>
            <div class="text-center text-muted mt-3">
                Already registered? <a href="login.php" tabindex="-1">Login</a>
            </div>
        </div>
    </div>
    </body>
    <script>
        var passwordInput = $('input[id="password"]');
        var confirmPasswordInput = $('input[id="confirm_password"]');
        passwordInput.on('input', checkPasswordMatch);
        confirmPasswordInput.on('input', checkPasswordMatch);

        function checkPasswordMatch() {
            var password = passwordInput.val();
            var confirmPassword = confirmPasswordInput.val();

            if (password === confirmPassword && confirmPassword !== '') {
                confirmPasswordInput.removeClass('is-invalid').addClass('is-valid');
            } else {
                confirmPasswordInput.removeClass('is-valid').addClass('is-invalid');
            }
        }
    </script>
<?php include_once("footer.php") ?>