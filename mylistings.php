<?php
include_once "include/common.php";
global $conn;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        header('Content-Type: application/json');
        $action = $_POST['action'] ?? null;
        $id = $_POST['id'] ?? null;
        if (! $action || ! $id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit();
        }
        switch ($action) {
            case 'cancel':
                // Check item exists and belones to user
                $stmt = $conn->prepare("SELECT COUNT(*) FROM AuctionItem WHERE id = ? AND seller_id = ?");
                $stmt->execute([$id, $_SESSION['user_id']]);
                if ($stmt->fetchColumn() == 0) {
                    http_response_code(403);
                    header('HX-Refresh: true');
                    echo json_encode(['ret' => 0, 'msg' => 'You do not have permission to cancel this listing']);
                    exit();
                }
                $stmt = $conn->prepare("UPDATE AuctionItem SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$id]);
                header('HX-Refresh: true');
                echo json_encode(['ret' => 1, 'msg' => 'Listing cancelled successfully']);
                break;
            default:
                http_response_code(400);
                echo json_encode(['ret' => 0, 'msg' => 'Invalid action']);
        }
        exit();
    case 'GET':
        include_once("header.php");
        break;
    default:
        http_response_code(405);
        exit();
}
$status = $_GET['status'] ?? "%";
$stmt = $conn->prepare("
        SELECT id, name, description, current_price, end_date, status, views, start_date
        FROM AuctionItem 
        WHERE seller_id = :seller_id AND status LIKE :status
        ORDER BY end_date DESC
        ");
$stmt->execute([
    'seller_id' => $_SESSION['user_id'],
    'status' => $status
]);
$items = $stmt->fetchAll();
?>
    <title><?= env('app_name') ?> - My Listings</title>
    <body>
    <div class="page-wrapper">
        <div class="page-header">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <h2 class="page-title">
                            My Listings
                        </h2>
                    </div>
                    <div class="col-auto ms-auto d-print-none">
                        <a href="create_auction.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Post an Item
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="page-body">
            <div class="container-xl">
                <div class="row row-cards">
                    <div class="space-y">
                        <?php if (empty($items)): ?>
                            <p class='text-muted'>You have no active listings at the moment. <a
                                        href='create_auction.php'>Create a new listing</a> to get started!</p>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <?php
                                // Count watchers
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM watch WHERE auction_item_id = ?");
                                $stmt->execute([$item['id']]);
                                $watchers = $stmt->fetchColumn();
                                // Count bids
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM bid WHERE auction_item_id = ?");
                                $stmt->execute([$item['id']]);
                                $bids = $stmt->fetchColumn();
                                // Fetch item first image
                                $stmt = $conn->prepare("SELECT filename FROM images WHERE auction_item_id = ? LIMIT 1");
                                $stmt->execute([$item['id']]);
                                $image = $stmt->fetchColumn();
                                ?>
                                <div class="card">
                                    <div class="row g-0">
                                        <div class="col-auto">
                                            <div class="card-body">
                                                <div class="avatar avatar-lg"
                                                     style="width: 150px; height: 150px; background-image: url(./data/<?= $image ?>)">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="card-body ps-0">
                                                <div class="row">
                                                    <div class="col">
                                                        <h3 class="mb-0">
                                                            <a href="view_item.php?id=<?= $item['id'] ?>"
                                                               target="_blank">
                                                                <?= strlen($item['name']) > 150 ? substr($item['name'], 0, 150) . "..." : $item['name'] ?>
                                                            </a>
                                                        </h3>
                                                    </div>
                                                    <div class="col-auto fs-2 text-green">
                                                        £<?= $item['current_price'] ?></div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md">
                                                        <div class="mt-3 list-inline list-inline-dots mb-0 text-secondary d-sm-block d-none">
                                                            <div class="list-inline-item">
                                                                <?= $item['views'] ?> <i class="ti ti-eye"></i>
                                                            </div>
                                                            <div class="list-inline-item">
                                                                <?= $watchers ?> <i class="ti ti-heart"></i>
                                                            </div>
                                                            <div class="list-inline-item">
                                                                <?= $bids ?> <i class="ti ti-gavel"></i>
                                                            </div>
                                                            <div class="list-inline-item">
                                                                Posted
                                                                at <?= date("F j, Y, g:i a", strtotime($item['start_date'])) ?>
                                                            </div>
                                                            <div class="list-inline-item">
                                                                Ends
                                                                at <?= date("F j, Y, g:i a", strtotime($item['end_date'])) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-auto">
                                                        <?php
                                                        $badgeClass = '';
                                                        $badgeText = '';

                                                        switch ($item['status']) {
                                                            case 'active':
                                                                $badgeClass = 'bg-green text-green-fg';
                                                                $badgeText = 'Active';
                                                                break;
                                                            case 'closed':
                                                                $badgeClass = 'bg-red text-red-fg';
                                                                $badgeText = 'Closed';
                                                                break;
                                                            case 'cancelled':
                                                                $badgeClass = 'bg-orange text-orange-fg';
                                                                $badgeText = 'Cancelled';
                                                                break;
                                                            default:
                                                                $badgeClass = 'bg-gray text-gray-fg';
                                                                $badgeText = 'Unknown';
                                                        }
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-flex">
                                            <?php if ($item['status'] == 'active'): ?>
                                                <button class="btn btn-danger ms-auto"
                                                        hx-post="mylistings.php"
                                                        hx-disable-elt="this"
                                                        hx-confirm="Are you sure you want to close this listing?"
                                                        hx-vals="js:{
                                                        action: 'cancel',
                                                        id: <?= $item['id'] ?>
                                                        }"
                                                >
                                                    Cancel Listing
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </body>
<?php include_once("footer.php") ?>