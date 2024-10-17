<?php
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        include "include/common.php";
        header('Content-Type: application/json');
        $user = (new User())->fetch($_SESSION['user_id']);
        if ($user === null) {
            echo json_encode([
                'ret' => 0,
                'msg' => 'User not found'
            ]);
            exit();
        }
        $result = $user->update($_POST);
        if ($result['ret'] === 1) {
            header('HX-Refresh: true');
        }
        echo json_encode($result);
        exit();
    case 'GET':
        include_once("header.php");
        $user = (new User())->fetch($_SESSION['user_id']);
        if ($user === null) {
            header('HX-Redirect: login.php');
            exit();
        }
        break;
    default:
        http_response_code(405);
        exit();
}
?>

<title><?php echo env('app_name')?> - Profile</title>

<script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>

<div class="page">
    <div class="page-wrapper">
        <div class="container mt-5">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile"
                            type="button" role="tab" aria-controls="profile" aria-selected="true">User Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="webauthn-tab" data-bs-toggle="tab" data-bs-target="#webauthn"
                            type="button" role="tab" aria-controls="webauthn" aria-selected="false">WebAuthn
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="totp-tab" data-bs-toggle="tab" data-bs-target="#totp" type="button"
                            role="tab" aria-controls="totp" aria-selected="false">Two-Factor Authentication
                    </button>
                </li>
            </ul>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">User Profile</h3>
                        </div>
                        <div class="card-body">
                            <div class="input-group mb-3">
                                <span class="input-group-text">Username</span>
                                <input class="form-control" id="username" type="text" value="<?php echo $user->username ?>">
                            </div>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Email</span>
                                <input class="form-control" id="email" type="email" value="<?php echo $user->email ?>">
                            </div>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Password</span>
                                <input class="form-control" id="password" type="password" placeholder="Leave empty to keep the same">
                            </div>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Account Role</span>
                                <input class="form-control" type="text" disabled value="<?php echo $user->role ?>">
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-primary"
                                    hx-swap="none"
                                    hx-post="profile.php"
                                    hx-vals='js:{
                                email: document.getElementById("email").value,
                                username: document.getElementById("username").value,
                                password: document.getElementById("password").value,
                             }'>Update
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="webauthn" role="tabpanel" aria-labelledby="webauthn-tab">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">WebAuthn</h3>
                        </div>
                        <div class="card-body">
                            <!-- TODO WebAuthn -->
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="totp" role="tabpanel" aria-labelledby="totp-tab">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Two-Factor Authentication</h3>
                        </div>
                        <div class="card-body">
                            <!-- TODO TOTP&FIDO -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once("footer.php") ?>
