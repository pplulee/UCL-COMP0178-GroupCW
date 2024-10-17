<?php

use model\User;

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
        $webauthn_devices = $user->getMfaDevices('passkey');
        $totp_devices = $user->getMfaDevices('totp');
        $fido_devices = $user->getMfaDevices('fido');
        break;
    default:
        http_response_code(405);
        exit();
}
?>

<title><?php echo env('app_name') ?> - Profile</title>

<script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>
<script src="https://fastly.jsdelivr.net/npm/qrcode_js@latest/qrcode.min.js"></script>

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
                                <input class="form-control" id="username" type="text"
                                       value="<?php echo $user->username ?>">
                            </div>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Email</span>
                                <input class="form-control" id="email" type="email" value="<?php echo $user->email ?>">
                            </div>
                            <div class="input-group mb-3">
                                <span class="input-group-text">Password</span>
                                <input class="form-control" id="password" type="password"
                                       placeholder="Leave empty to keep the same">
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
                            <div class="col-sm-12 col-md-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h3 class="card-title">Passkey</h3>
                                        <p class="card-subtitle">Passkey is a secure and easy-to-use login
                                            credential.</p>
                                        <div class="row row-cols-1 row-cols-md-4 g-4">
                                            <?php foreach ($webauthn_devices as $device): ?>
                                                <div class="col">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title"><?php echo $device['name'] ?? 'Unnamed'; ?></h5>
                                                            <p class="card-text">Added
                                                                on: <?php echo $device['created_at']; ?></p>
                                                            <p class="card-text">Last
                                                                used: <?php echo $device['used_at'] ?? 'Never used'; ?></p>
                                                            <button class="btn btn-danger"
                                                                    hx-delete="mfa/webauthn_reg.php"
                                                                    hx-swap="none"
                                                                    hx-confirm="Are you sure you want to delete this device?"
                                                                    hx-vals='{"id": "<?php echo $device['id']; ?>"}'
                                                            >Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-flex">
                                            <button class="btn btn-primary ms-auto" id="webauthnReg">
                                                Register Passkey Device
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="totp" role="tabpanel" aria-labelledby="totp-tab">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Two-Factor Authentication</h3>
                        </div>
                        <div class="card-body">
                            <div class="col-sm-12 col-md-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h3 class="card-title">TOTP
                                            <?php if (sizeof($totp_devices) > 0): ?>
                                                <span class="badge bg-green text-green-fg">Enabled</span>
                                            <?php else: ?>
                                                <span class="badge bg-red text-red-fg">Disabled</span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="card-subtitle">TOTP is a time-based one-time password algorithm that
                                            can be verified using Google Authenticator or Authy clients.</p>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-flex">
                                            <?php if (sizeof($totp_devices) > 0): ?>
                                                <button class="btn btn-red ms-auto"
                                                        hx-delete="/user/totp_reg"
                                                        hx-confirm="Are you sure you want to disable TOTP?"
                                                        hx-swap="none">
                                                    Disable
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-primary ms-auto" id="enableTotp">
                                                    Enable
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-12 col-md-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h3 class="card-title">FIDO
                                            <?php if (sizeof($fido_devices) > 0): ?>
                                                <span class="badge bg-green text-green-fg">Enabled</span>
                                            <?php else: ?>
                                                <span class="badge bg-red text-red-fg">Disabled</span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="card-subtitle">FIDO2 is a public key cryptography-based authentication
                                            standard that provides a more secure login method. It supports hardware
                                            security keys like Yubikey.</p>
                                        <?php if (sizeof($fido_devices) > 0): ?>
                                            <div class="row row-cols-1 row-cols-md-4 g-4">
                                                <?php foreach ($fido_devices as $device): ?>
                                                    <div class="col">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h5 class="card-title"><?php echo $device['name'] ?? 'Unnamed'; ?></h5>
                                                                <p class="card-text">Added
                                                                    on: <?php echo $device['created_at']; ?></p>
                                                                <p class="card-text">Last
                                                                    used: <?php echo $device['used_at'] ?? 'Never used'; ?></p>
                                                                <button class="btn btn-danger"
                                                                    hx-delete="mfa/fido_reg.php"
                                                                    hx-swap="none"
                                                                    hx-confirm="Are you sure you want to delete this device?"
                                                                    hx-vals='{"id": "<?php echo $device['id']; ?>"}'
                                                            >Delete
                                                            </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-flex">
                                            <button class="btn btn-primary ms-auto" id="fidoReg">
                                                Register FIDO Device
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="totpModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">TOTP Setup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="row">
                    <div class="col-md-12">
                        <p>Please use Google Authenticator or Authy to scan the QR code below</p>
                    </div>
                    <div class="col-md-12 d-flex justify-content-center align-items-center">
                        <div id="qrcode"></div>
                    </div>
                    <div class="col-md-12">
                        <p>If you can't scan the QR code, you can manually enter the following secret</p>
                        <p id="totpSecret"></p>
                    </div>
                    <div class="col-md-12">
                        <input type="text" id="totpCode" placeholder="Please enter the code"
                               class="form-control mx-auto">
                    </div>
                </div>
                <div id="qrcode"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary"
                        hx-post="mfa/totp_reg.php"
                        hx-vals='js:{code: document.getElementById("totpCode").value}'
                        hx-swap="none"
                >
                    Submit
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const {startRegistration} = SimpleWebAuthnBrowser;
    document.getElementById('webauthnReg').addEventListener('click', async () => {
        const resp = await fetch('mfa/webauthn_reg.php');
        const options = await resp.json();
        let attResp;
        try {
            attResp = await startRegistration({optionsJSON: options});
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
            throw error;
        }
        attResp.name = prompt("Please set the device name:");
        const verificationResp = await fetch('mfa/webauthn_reg.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(attResp),
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
<?php if (sizeof($totp_devices) == 0): ?>
    <script>
        document.querySelector('#enableTotp').addEventListener('click', async () => {
            var modal = new bootstrap.Modal(document.getElementById('totpModal'), {
                backdrop: 'static',
                keyboard: false
            });
            const resp = await fetch('mfa/totp_reg.php');
            const data = await resp.json();
            if (data.ret === 1) {
                let qrcodeElement = document.getElementById('qrcode');
                qrcodeElement.innerHTML = '';
                let totpSecret = document.getElementById('totpSecret');
                totpSecret.innerHTML = data.token;
                let qrcode = new QRCode(qrcodeElement, {
                    text: data.url,
                    width: 256,
                    height: 256,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
                modal.show();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.msg
                });
            }
        });
    </script>
<?php endif; ?>
<script>
    document.getElementById('fidoReg').addEventListener('click', async () => {
        const resp = await fetch('mfa/fido_reg.php');
        let attResp;
        const options = await resp.json();
        try {
            attResp = await startRegistration({optionsJSON: options});
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
            throw error;
        }
        attResp.name = prompt("Please set the device name:");
        const verificationResp = await fetch('mfa/fido_reg.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(attResp),
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
<?php include_once("footer.php") ?>
