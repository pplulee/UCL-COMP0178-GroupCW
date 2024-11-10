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
$stmt = $conn->prepare("SELECT a.id, a.name, a.current_price, a.end_date, a.status, a.views FROM AuctionItem a JOIN watch w ON a.id = w.auction_item_id WHERE w.buyer_id = :buyer_id ORDER BY a.end_date DESC");
$stmt->execute([
    'buyer_id' => $_SESSION['user_id']
]);
$items = $stmt->fetchAll();
?>
    <title><?= env('app_name') ?> - Watchlist</title>
    <body>
    <div class="page-wrapper">
        <div class="page-header">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <h2 class="page-title">
                            My Watchlist
                        </h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="page-body">
            <div class="container-xl">
                <div class="row row-cards">
                    <div class="space-y">
                        <?php if (empty($items)): ?>
                            <p class='text-muted'>You have no items in your watchlist. Visit the
                                <a href='browse.php'>browse</a>
                                page to find items to watch.
                            </p>
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
                                                <div class="row mt-3">
                                                    <div class="col">
                                                        <button class="btn btn-warning"
                                                                hx-post="view_item.php"
                                                                hx-disable-elt="this"
                                                                hx-confirm="Are you sure you want to unwatch this item?"
                                                                hx-vals="js:{
                                                                    action: 'unwatch',
                                                                    item_id: <?= $item['id'] ?>
                                                                }"
                                                        >
                                                            Unwatch
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
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