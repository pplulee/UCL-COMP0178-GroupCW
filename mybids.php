<?php
include_once "include/common.php";
global $conn;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        header('Content-Type: application/json');
        if (! isset($_POST['action'])) {
            http_response_code(400);
            echo json_encode([
                'ret' => 0,
                'msg' => 'Invalid request'
            ]);
            exit();
        }
        switch ($_POST['action']) {
            case 'fetchBids':
                if (! isset($_POST['id'])) {
                    http_response_code(400);
                    echo json_encode([
                        'ret' => 0,
                        'msg' => 'Invalid request'
                    ]);
                    exit();
                }
                $itemId = $_POST['id'];
                // TODO: Fetch bid_price, bid_time, status from bids table
                $stmt = $conn->prepare("");
                $stmt->execute([]);
                $bids = $stmt->fetchAll();
                echo json_encode([
                    'ret' => 1,
                    'bids' => $bids
                ]);
                exit();
            default:
                http_response_code(400);
                echo json_encode([
                    'ret' => 0,
                    'msg' => 'Invalid request'
                ]);
                exit();
        }
    case 'GET':
        include_once("header.php");
        break;
    default:
        http_response_code(405);
        exit();
}
// TODO: Fetch items id in bid table
$stmt = $conn->prepare("");
$stmt->execute([]);
$items = $stmt->fetchAll();
?>
    <title><?= env('app_name') ?> - My Bids</title>
    <body>
    <div class="page-wrapper">
        <div class="page-header">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <h2 class="page-title">
                            My Bids
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
                            <p class='text-muted'>Your account currently has no bid records. Go <a href='browse.php'>browse
                                    items</a> and place a bid.</p>
                        <?php else: ?>
                            <?php foreach ($items as $itemId): ?>
                                <?php
                                // TODO: Fetch item details
                                $stmt = $conn->prepare("");
                                $stmt->execute([]);
                                $item = $stmt->fetch();
                                // Count watchers
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM watch WHERE auction_item_id = ?");
                                $stmt->execute([$item['id']]);
                                $watchers = $stmt->fetchColumn();
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
                                                                Ends
                                                                at <?= date("F j, Y, g:i a", strtotime($item['end_date'])) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-auto">
                                                        <?php
                                                        $badgeClass = '';
                                                        $badgeText = '';
                                                        // TODO: check auction status, if closed, check if user won
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
                                                        <button class="btn btn-warning fetchBids"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#bidModal"
                                                                data-id="<?= $item['id'] ?>"
                                                        >
                                                            View Bids
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
    <div class="modal fade" id="bidModal" tabindex="-1" aria-labelledby="bidModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bidModalLabel">Bid Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table">
                        <thead>
                        <tr>
                            <th scope="col">Bid Amount</th>
                            <th scope="col">Bid Time</th>
                            <th scope="col">Bid Status</th>
                        </tr>
                        </thead>
                        <tbody id="bidDetails">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function () {
            $('.fetchBids').on('click', function () {
                var itemId = $(this).data('id');
                $.ajax({
                    url: 'mybids.php',
                    type: 'POST',
                    data: {id: itemId, action: 'fetchBids'},
                    dataType: 'json',
                    success: function (response) {
                        var bidDetails = $('#bidDetails');
                        bidDetails.empty();
                        if (response.bids && response.bids.length > 0) {
                            response.bids.forEach(function (bid) {
                                bidDetails.append('<tr><td>' + bid.bid_price + '</td><td>' + bid.bid_time + '</td><td>' + bid.status + '</td></tr>');
                            });
                        } else {
                            bidDetails.append('<tr><td colspan="3">No bids found</td></tr>');
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Failed to fetch bid details'
                        });
                    }
                });
            });
        });
    </script>
<?php include_once("footer.php") ?>