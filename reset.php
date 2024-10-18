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
        global $conn, $cache;
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'reset':
                    $email = $_POST['email'];
                    $code = $_POST['code'];
                    $new_password = $_POST['new_password'];
                    $confirm_password = $_POST['confirm_password'];
                    $result = validate(['email' => $email, 'code' => $code, 'new_password' => $new_password, 'confirm_password' => $confirm_password],
                        ['email' => 'required|email',
                            'code' => 'required',
                            'new_password' => 'required|min:6',
                            'confirm_password' => 'required|same:new_password'],
                        ['email:required' => 'Email is required',
                            'email:email' => 'Invalid email',
                            'code:required' => 'Verification code is required',
                            'new_password:required' => 'New password is required',
                            'new_password:min' => 'Password must be at least 8 characters',
                            'confirm_password:required' => 'Confirm password is required',
                            'confirm_password:same' => 'Passwords do not match']);
                    if ($result['ret'] === 0) {
                        echo json_encode($result);
                        exit();
                    }
                    $stmt = $conn->prepare("SELECT id FROM user WHERE email = :email");
                    $stmt->execute(['email' => $email]);
                    $user = $stmt->fetch();
                    if ($user === false) {
                        echo json_encode(['ret' => 0, 'msg' => 'User not found']);
                        exit();
                    }
                    $user_id = $cache->get('reset_' . $code);
                    if ($user_id !== $user['id']) {
                        echo json_encode(['ret' => 0, 'msg' => 'Invalid verification code']);
                        exit();
                    }
                    $stmt = $conn->prepare("UPDATE user SET password = :password WHERE id = :id");
                    $stmt->execute(['password' => password_hash($new_password, getPasswordMethod()), 'id' => $user['id']]);
                    $cache->delete('reset_' . $code);
                    header('HX-Redirect: login.php');
                    echo json_encode(['ret' => 1, 'msg' => 'Password reset successfully']);
                    exit();
                case 'send_code':
                    $email = $_POST['email'];
                    $result = validate(
                        ['email' => $email],
                        ['email' => 'required|email'],
                        ['email:required' => 'Email is required', 'email:email' => 'Invalid email']
                    );
                    if ($result['ret'] === 0) {
                        echo json_encode($result);
                        exit();
                    }
                    $stmt = $conn->prepare("SELECT id FROM user WHERE email = :email");
                    $stmt->execute(['email' => $email]);
                    $user = $stmt->fetch();
                    if ($user === false) {
                        echo json_encode(['ret' => 0, 'msg' => 'User not found']);
                        exit();
                    }
                    $token = bin2hex(random_bytes(8));
                    $cache->set('reset_' . $token, $user['id'], 3600);
                    $template = file_get_contents('templates/emails/code.html');
                    $output = str_replace('{%code%}', $token, $template);
                    echo json_encode(sendmail($email, 'Reset Password', $output));
                    exit();
                default:
                    echo json_encode(['ret' => 0, 'msg' => 'Invalid action']);
                    exit();
            }
        }
        echo json_encode(['ret' => 0, 'msg' => 'Invalid action']);
        exit();
    case 'GET':
        include_once("header.php");
        break;
    default:
        http_response_code(405);
        exit();
}
?>

    <title><?php echo env('app_name') ?> - Reset Password</title>
    <body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="card card-md">
                <div class="card-body">
                    <h2 class="h2 text-center mb-4">Reset Password</h2>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope"></i> Email</label>
                        <input autocomplete="off" class="form-control" id="email"
                               placeholder="Please enter your email address"
                               tabindex="1" type="text">
                    </div>
                    <div class="mb-3">
                        <button class="btn btn-secondary w-100" id="send-code-button" type="button"
                                hx-post="reset.php"
                                hx-trigger="click"
                                hx-target="#send-code-button"
                                hx-swap="none"
                                hx-vals='js:{
                            email: document.getElementById("email").value,
                            action: "send_code"}'
                        >
                            Send Verification Code
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-key"></i> Verification Code</label>
                        <input autocomplete="off" class="form-control" id="code"
                               placeholder="Please enter the verification code"
                               tabindex="2" type="text">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock"></i> New Password</label>
                        <input autocomplete="off" class="form-control" id="new_password"
                               placeholder="Please enter your new password"
                               tabindex="3" type="password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input autocomplete="off" class="form-control" id="confirm_password"
                               placeholder="Please confirm your new password"
                               tabindex="4" type="password">
                    </div>
                    <div class="form-footer">
                        <button class="btn btn-primary w-100 reset-button"
                                hx-post="reset.php"
                                hx-swap="none"
                                hx-disabled-elt="button"
                                hx-vals='js:{
                            email: document.getElementById("email").value,
                            code: document.getElementById("code").value,
                            new_password: document.getElementById("new_password").value,
                            confirm_password: document.getElementById("confirm_password").value,
                            action: "reset"
                            }' type="submit">
                            Reset Password
                        </button>
                    </div>
                </div>
            </div>
            <div class="text-center text-muted mt-3">
                <a href="login.php" tabindex="-1">Back to login</a>
            </div>
        </div>
    </div>
    </body>
    <script>
        var passwordInput = $('input[id="new_password"]');
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
    <script>
        document.addEventListener('htmx:beforeRequest', (event) => {
            if (event.detail.elt.classList.contains('manual-unblock')) {
                Swal.fire({
                    title: 'Processing',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    onBeforeOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
        });
    </script>
<?php include_once("footer.php") ?>