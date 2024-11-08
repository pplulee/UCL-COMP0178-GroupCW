<?php
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}
include_once "include/common.php";

function email_queue_process(): void
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM email_queue WHERE status = 'pending' LIMIT 10");
    $stmt->execute();
    $emails = $stmt->fetchAll();
    // Set all fetched email to locked status
    $ids = array_column($emails, 'id');
    if (! empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $updateStmt = $conn->prepare("UPDATE email_queue SET status = 'locked' WHERE id IN ($placeholders)");
        $updateStmt->execute($ids);
    }

    foreach ($emails as $email) {
        $result = sendmail($email['recipient'], $email['subject'], $email['template'], json_decode($email['params'], true));
        if ($result['ret'] === 1) {
            $stmt = $conn->prepare("UPDATE email_queue SET status = 'sent' WHERE id = :id");
            $stmt->execute(['id' => $email['id']]);
        } else {
            $stmt = $conn->prepare("UPDATE email_queue SET status = 'failed', error = :msg WHERE id = :id");
            $stmt->execute(['id' => $email['id'], 'msg' => $result['msg']]);
        }
    }
}

email_queue_process();
