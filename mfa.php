<?php

include_once "include/common.php";
if ($_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}
global $cache, $conn;
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        header('Content-Type: application/json');
        exit();
    case 'GET':
        include_once("header.php");
        $userId = $cache->get('mfa_userid_' . session_id());
        if ($userId === null) {
            header('Location: login.php');
            exit();
        }
        $user = (new \model\User())->fetch($userId);
        $method = $user->checkMfaStatus();
        break;
    default:
        http_response_code(405);
        exit();
}
?>
    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>

    <title><?php echo env('app_name') ?> - Login</title>

    <body class="border-top-wide border-primary d-flex flex-column">
    <div class="page page-center">
        <div class="container-tight my-auto">
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Two-Factor Authentication</h2>
                    <p>For your account security, please complete the two-factor authentication.</p>
                    <?php if ($method['totp']): ?>
                        <div class="my-5">
                            <div class="row g-4">
                                <div class="col">
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="text" class="form-control form-control-lg text-center py-3"
                                                   maxlength="1" inputmode="numeric" pattern="[0-9]*"
                                                   data-code-input="">
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control form-control-lg text-center py-3"
                                                   maxlength="1" inputmode="numeric" pattern="[0-9]*"
                                                   data-code-input="">
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control form-control-lg text-center py-3"
                                                   maxlength="1" inputmode="numeric" pattern="[0-9]*"
                                                   data-code-input="">
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="text" class="form-control form-control-lg text-center py-3"
                                                   maxlength="1" inputmode="numeric" pattern="[0-9]*"
                                                   data-code-input="">
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control form-control-lg text-center py-3"
                                                   maxlength="1" inputmode="numeric" pattern="[0-9]*"
                                                   data-code-input="">
                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control form-control-lg text-center py-3"
                                                   maxlength="1" inputmode="numeric" pattern="[0-9]*"
                                                   data-code-input="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="form-footer">
                        <?php if ($method['totp']): ?>
                            <button class="btn btn-primary w-100 mb-3"
                                    hx-post="mfa/totp_handle.php" hx-swap="none" hx-vals="js:{
                code: code,
             }">
                                Submit
                            </button>
                        <?php endif; ?>
                        <?php if ($method['fido']): ?>
                            <button class="btn btn-primary w-100" id="webauthnLogin">
                                Use FIDO Device
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once("footer.php") ?>

    <?php if ($method['totp']): ?>
        <script>
            var code = '';
            document.addEventListener("DOMContentLoaded", function () {
                var inputs = document.querySelectorAll('[data-code-input]');

                for (let i = 0; i < inputs.length; i++) {
                    inputs[i].addEventListener('input', function (e) {
                        if (e.target.value.length === e.target.maxLength && i + 1 < inputs.length) {
                            inputs[i + 1].focus();
                        }
                        code = '';
                        inputs.forEach(input => {
                            code += input.value;
                        });
                    });
                    inputs[i].addEventListener('keydown', function (e) {
                        if (e.target.value.length === 0 && e.keyCode === 8 && i > 0) {
                            inputs[i - 1].focus();
                        }
                    });
                }
            });
        </script>
    <?php endif; ?>

    <?php if ($method['fido']): ?>
    <script>
        const {startAuthentication} = SimpleWebAuthnBrowser;
        document.getElementById('webauthnLogin').addEventListener('click', async () => {
            const resp = await fetch('mfa/fido_handle.php');
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
            const verificationResp = await fetch('mfa/fido_handle.php', {
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
                    location.reload();
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
<?php endif; ?>