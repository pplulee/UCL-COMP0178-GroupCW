<?php

use model\User;

include_once "include/common.php";
if ($_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        header('Content-Type: application/json');
        $user = new User();
        $result = $user->login($_POST);
        if ($result['ret'] === 1) {
            $redir = $result['redir'];
            header("HX-Redirect: $redir");
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
    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>

    <title><?php echo env('app_name') ?> - Login</title>
    <body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-4">User Login</h2>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                        <input autocomplete="off" class="form-control" id="email"
                               placeholder="Please enter your email address"
                               tabindex="1" type="text">
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><i class="fa-solid fa-lock"></i> Password
                            <span class="form-label-description">
                            <a href="/auth/reset">Forgot password</a>
                        </span>
                        </label>
                        <div class="input-group input-group-flat">
                            <input autocomplete="off" class="form-control" id="password"
                                   placeholder="Please enter your password"
                                   tabindex="2" type="password">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-check">
                            <input class="form-check-input" id="rememberMe" type="checkbox">
                            <span class="form-check-label">Remember me</span>
                        </label>
                    </div>
                    <div class="form-footer">
                        <button class="btn btn-primary w-100"
                                hx-post="login.php"
                                hx-swap="none"
                                hx-disabled-elt="button"
                                hx-vals='js:{
                                email: document.getElementById("email").value,
                                password: document.getElementById("password").value,
                                rememberMe: document.getElementById("rememberMe").checked,
                            }'>
                            Login
                        </button>
                        <button class="btn btn-primary w-100 mt-3" id="webauthnLogin">
                            Login with WebAuthn
                        </button>
                    </div>
                </div>
            </div>
            <div class="text-center text-muted mt-3">
                Not registered yet? <a href="register.php" tabindex="-1">Register</a>
            </div>
        </div>
    </div>
    </body>
    <script>
        const {startAuthentication} = SimpleWebAuthnBrowser;
        document.getElementById('webauthnLogin').addEventListener('click', async () => {
            const resp = await fetch('/mfa/webauthn_login.php');
            const options = await resp.json();
            let asseResp;
            try {
                asseResp = await startAuthentication({optionsJSON: options});
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
                throw error;
            }
            const verificationResp = await fetch('/mfa/webauthn_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(asseResp),
            });
            const verificationJSON = await verificationResp.json();
            if (verificationJSON.ret === 1) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: verificationJSON.msg
                }).then(() => {
                    window.location.href = verificationJSON.redir;
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: verificationJSON.msg
                });
            }
        });
    </script>
<?php include_once("footer.php") ?>