<?php
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

$lockFile = __DIR__ . '/.cronlock';

if (file_exists($lockFile)) {
    die('Cron job is already running.');
}


// Register a shutdown function to remove the lock file
register_shutdown_function(function () use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

if (file_exists($lockFile)) {
    $lockTime = (int) file_get_contents($lockFile);
    if (time() - $lockTime > 600) {
        // Update the lock file with the new timestamp
        file_put_contents($lockFile, time());
    } else {
        die('Cron job is already running.');
    }
} else {
    // Create the lock file with the current timestamp
    file_put_contents($lockFile, time());
}

include_once "include/common.php";

function email_queue_process(): void
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM email_queue WHERE status = 'pending' LIMIT 50");
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

function auction_end_update(): void
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM AuctionItem WHERE status = 'active' AND end_date < NOW()");
    $stmt->execute();
    $auctions = $stmt->fetchAll();
    foreach ($auctions as $auction) {
        $stmt = $conn->prepare("UPDATE AuctionItem SET status = 'closed' WHERE id = :id");
        $stmt->execute(['id' => $auction['id']]);
        $stmt = $conn->prepare("SELECT * FROM bid WHERE auction_item_id = :id AND status = 'pending' ORDER BY bid_price DESC LIMIT 1");
        $stmt->execute(['id' => $auction['id']]);
        $bid = $stmt->fetch();
        // Fetch item first image
        $stmt = $conn->prepare("SELECT `filename` FROM images WHERE auction_item_id = ? LIMIT 1");
        $stmt->execute([$auction['id']]);
        $image = $stmt->fetch();
        $image_url = env('app_url') . "/data/" . $image['filename'];
        if ($bid !== false) {
            $stmt = $conn->prepare("UPDATE bid SET status = 'won' WHERE id = :id");
            $stmt->execute(['id' => $bid['id']]);
            // Fetch seller
            $stmt = $conn->prepare("SELECT username, email FROM user WHERE id = :id");
            $stmt->execute(['id' => $auction['seller_id']]);
            $seller = $stmt->fetch();
            // Fetch winning buyer
            $stmt = $conn->prepare("SELECT username, email, address FROM user WHERE id = :id");
            $stmt->execute(['id' => $bid['user_id']]);
            $buyer = $stmt->fetch();
            // Send email to seller
            $msg = "Congratulations! Your auction for {$auction['name']} has ended with a winning bid of £{$bid['bid_price']} by {$buyer['username']}.";
            $stmt = $conn->prepare("INSERT INTO email_queue (recipient, subject, template, params) VALUES (:recipient, :subject, :template, :params)");
            $stmt->execute([
                'recipient' => $seller['email'],
                'subject' => 'Auction Ended',
                'template' => 'auction_confirm',
                'params' => json_encode([
                    'username' => $seller['username'],
                    'message' => $msg,
                    'product_name' => $auction['name'],
                    'price' => $bid['bid_price'],
                    'image_url' => $image_url
                ])
            ]);
            // Send email to buyer
            $msg = "Congratulations! You have won the auction for {$auction['name']} with a winning bid of £{$bid['bid_price']}.";
            $stmt = $conn->prepare("INSERT INTO email_queue (recipient, subject, template, params) VALUES (:recipient, :subject, :template, :params)");
            $stmt->execute([
                'recipient' => $buyer['email'],
                'subject' => 'Auction Won',
                'template' => 'auction_confirm',
                'params' => json_encode([
                    'username' => $buyer['username'],
                    'message' => $msg,
                    'product_name' => $auction['name'],
                    'price' => $bid['bid_price'],
                    'image_url' => $image_url
                ])
            ]);
            // Create transaction
            $stmt = $conn->prepare("INSERT INTO transaction (bid_id, buyer_id, seller_id, auction_item_id, price, payment_method, status, address) VALUES (:bid_id, :buyer_id, :seller_id, :auction_item_id, :price, :payment_method, :status, :address)");
            $stmt->execute([
                'bid_id' => $bid['id'],
                'buyer_id' => $bid['user_id'],
                'seller_id' => $auction['seller_id'],
                'auction_item_id' => $auction['id'],
                'price' => $bid['bid_price'],
                'payment_method' => 'Dummy Payment',
                'status' => 'pending_payment',
                'address' => $buyer['address']
            ]);
        } else {
            // No bids
            $stmt = $conn->prepare("SELECT username, email FROM user WHERE id = :id");
            $stmt->execute(['id' => $auction['seller_id']]);
            $seller = $stmt->fetch();
            $msg = "Unfortunately, your auction for {$auction['name']} has ended without any bids.";
            $stmt = $conn->prepare("INSERT INTO email_queue (recipient, subject, template, params) VALUES (:recipient, :subject, :template, :params)");
            $stmt->execute([
                'recipient' => $seller['email'],
                'subject' => 'Auction Ended',
                'template' => 'auction_confirm',
                'params' => json_encode([
                    'username' => $seller['username'],
                    'message' => $msg,
                    'product_name' => $auction['name'],
                    'price' => 0,
                    'image_url' => $image_url
                ])
            ]);
        }
    }
}

auction_end_update();
email_queue_process();
echo "Cron job completed successfully.\n";
