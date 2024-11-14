<?php

include_once "include/common.php";
global $conn;
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        include_once("header.php");
        break;
    case 'POST':
        $action = $_POST['action'];
        switch ($action) {
            case 'watch':
                $stmt = $conn->prepare("INSERT INTO watch (buyer_id, auction_item_id) VALUES (:buyer_id, :auction_item_id)");
                $stmt->execute(['buyer_id' => $_SESSION['user_id'], 'auction_item_id' => $_POST['item_id']]);
                header('HX-Refresh: true');
                echo json_encode([
                    'ret' => 1,
                    'msg' => 'Item added to watchlist'
                ]);
                break;
            case 'unwatch':
                $stmt = $conn->prepare("DELETE FROM watch WHERE buyer_id = :buyer_id AND auction_item_id = :auction_item_id");
                $stmt->execute(['buyer_id' => $_SESSION['user_id'], 'auction_item_id' => $_POST['item_id']]);
                header('HX-Refresh: true');
                echo json_encode([
                    'ret' => 1,
                    'msg' => 'Item removed from watchlist'
                ]);
                break;
            case 'bid':
                $result = validate($_POST, [
                    'item_id' => 'required|numeric',
                    'bid_amount' => 'required|numeric',
                ], [
                    'item_id:required' => 'Item ID is required',
                    'item_id:numeric' => 'Item ID must be a number',
                    'bid_amount:required' => 'Bid amount is required',
                    'bid_amount:numeric' => 'Bid amount must be a number'
                ]);
                if ($result['ret'] === 0) {
                    echo json_encode($result);
                    exit();
                }
                // Check item exists
                $stmt = $conn->prepare("SELECT * FROM AuctionItem WHERE id = :id");
                $stmt->execute(['id' => $_POST['item_id']]);
                $item = $stmt->fetch();
                if (! $item) {
                    echo json_encode([
                        'ret' => 0,
                        'msg' => 'Item not exist'
                    ]);
                    exit();
                }
                // Check if auction ended
                if (strtotime($item['end_date']) < time()) {
                    echo json_encode([
                        'ret' => 0,
                        'msg' => 'Auction ended'
                    ]);
                    exit();
                }
                // Check if bid amount is valid
                if ($_POST['bid_amount'] < $item['current_price'] + $item['bid_increment']) {
                    echo json_encode([
                        'ret' => 0,
                        'msg' => 'Bid amount is too low, minimum bid is £' . ($item['current_price'] + $item['bid_increment'])
                    ]);
                    exit();
                }
                // Seller self cannot bid
//                if ($item['seller_id'] === $_SESSION['user_id']) {
//                    echo json_encode([
//                        'ret' => 0,
//                        'msg' => 'You cannot bid on your own item'
//                    ]);
//                    exit();
//                }
                // Set watch if not already watching
                $stmt = $conn->prepare("SELECT * FROM watch WHERE buyer_id = :buyer_id AND auction_item_id = :auction_item_id");
                $stmt->execute(['buyer_id' => $_SESSION['user_id'], 'auction_item_id' => $_POST['item_id']]);
                $watching = $stmt->fetch() !== false;
                if (! $watching) {
                    $stmt = $conn->prepare("INSERT INTO watch (buyer_id, auction_item_id) VALUES (:buyer_id, :auction_item_id)");
                    $stmt->execute(['buyer_id' => $_SESSION['user_id'], 'auction_item_id' => $_POST['item_id']]);
                }

                // Set other bid to lost
                $stmt = $conn->prepare("UPDATE bid SET status = 'lost' WHERE auction_item_id = :auction_item_id AND status = 'pending'");
                $stmt->execute(['auction_item_id' => $_POST['item_id']]);
                // Record bid
                $stmt = $conn->prepare("INSERT INTO bid (user_id, auction_item_id, bid_price) VALUES (:user_id, :auction_item_id, :bid_price)");
                $stmt->execute(['user_id' => $_SESSION['user_id'], 'auction_item_id' => $_POST['item_id'], 'bid_price' => $_POST['bid_amount']]);
                // Update current price
                $stmt = $conn->prepare("UPDATE AuctionItem SET current_price = :current_price WHERE id = :id");
                $stmt->execute(['current_price' => $_POST['bid_amount'], 'id' => $_POST['item_id']]);

                // Notify watchers
                $stmt = $conn->prepare("SELECT buyer_id FROM watch WHERE auction_item_id = :auction_item_id AND buyer_id != :user_id");
                $stmt->execute(['auction_item_id' => $_POST['item_id'], 'user_id' => $_SESSION['user_id']]);
                $users = $stmt->fetchAll();
                $auction_url = env('app_url') . "/view_item.php?id=" . $_POST['item_id'];
                // Fetch item first image
                $stmt = $conn->prepare("SELECT `filename` FROM images WHERE auction_item_id = ? LIMIT 1");
                $stmt->execute([$item['id']]);
                $image = $stmt->fetch();
                $image_url = env('app_url') . "/data/" . $image['filename'];
                foreach ($users as $watcher) {
                    $stmt = $conn->prepare("SELECT username, email FROM user WHERE id = :id");
                    $stmt->execute(['id' => $watcher['buyer_id']]);
                    $watcher = $stmt->fetch();
                    // Insert into email queue
                    $stmt = $conn->prepare("INSERT INTO email_queue (recipient, subject, template, params) VALUES (:recipient, :subject, :template, :params)");
                    $stmt->execute([
                        'recipient' => $watcher['email'],
                        'subject' => 'New bid on ' . $item['name'],
                        'template' => 'watch_update',
                        'params' => json_encode([
                            'auction_url' => $auction_url,
                            'username' => $watcher['username'],
                            'item_name' => $item['name'],
                            'price' => $_POST['bid_amount'],
                            'image_url' => $image_url
                        ])
                    ]);
                }
                header('HX-Refresh: true');
                echo json_encode([
                    'ret' => 1,
                    'msg' => 'Bid placed successfully'
                ]);
                exit();
            default:
                http_response_code(400);
                exit();
        }
        exit();
    default:
        http_response_code(405);
        exit();
}
$item = null;
if (isset($_GET['id'])) {
    $item_id = $_GET['id'];
    // Check item exists with enhanced query
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            get_smart_price_suggestion(a.id) as suggested_price,
            mha.unique_bidders,
            mha.bid_frequency_per_hour,
            mha.avg_bid_increment,
            pta.avg_price as trend_avg_price,
            pta.price_volatility as price_volatility
        FROM AuctionItem a
        LEFT JOIN market_heat_analysis mha ON a.id = mha.item_id
        LEFT JOIN (
            SELECT 
                item_id,
                avg_price,
                price_volatility
            FROM price_trend_analysis 
            WHERE time_period = '24h'
            ORDER BY analysis_time DESC 
            LIMIT 1
        ) pta ON a.id = pta.item_id
        WHERE a.id = :id
    ");
    $stmt->execute(['id' => $item_id]);
    $item = $stmt->fetch();
    $bids = [];
    if ($item) {
        // Increment view count
        $stmt = $conn->prepare("UPDATE AuctionItem SET views = views + 1 WHERE id = :id");
        $stmt->execute(['id' => $item_id]);
        // Fetch seller's username
        $stmt = $conn->prepare("SELECT username FROM user WHERE id = :id");
        $stmt->execute(['id' => $item['seller_id']]);
        $seller = $stmt->fetch();
        $seller_username = $seller['username'];
        // Fetch seller's rating and total reviews
        $stmt = $conn->prepare("SELECT COUNT(*) as total, AVG(rating) as rating FROM review WHERE reviewee_id = :id");
        $stmt->execute(['id' => $item['seller_id']]);
        $seller_result = $stmt->fetch();
        $seller_rating = $seller_result['rating'] ?? 0;
        $seller_total = $seller_result['total'];
        // Fetch item images
        $stmt = $conn->prepare("SELECT `filename` FROM images WHERE auction_item_id = ?");
        $stmt->execute([$item['id']]);
        $images = $stmt->fetchAll();
        // Check if user is watching item
        $stmt = $conn->prepare("SELECT * FROM watch WHERE buyer_id = :user_id AND auction_item_id = :item_id");
        $stmt->execute(['user_id' => $_SESSION['user_id'], 'item_id' => $item_id]);
        $watching = $stmt->fetch() !== false;
        $auction_ended = strtotime($item['end_date']) < time() || in_array($item['status'], ['closed', 'cancelled']);
        // Fetch bids
        $stmt = $conn->prepare("SELECT b.bid_price, u.username, b.bid_time, b.status FROM bid b JOIN user u ON b.user_id = u.id WHERE b.auction_item_id = :item_id ORDER BY b.bid_time DESC");
        $stmt->execute(['item_id' => $item_id]);
        $bids = $stmt->fetchAll();
    }
}
?>

<div class="container mt-5">
    <?php if ($item !== null): ?>
        <title><?= env('app_name') ?> - <?= htmlspecialchars($item['name']) ?></title>
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">
                    <?= strlen($item['name']) > 150 ? substr($item['name'], 0, 150) . "..." : $item['name'] ?>
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
                </h2>
                <div class="card-actions ms-auto">
                    <?php if ($watching): ?>
                        <button class="btn btn-danger d-flex align-items-center"
                                hx-post="view_item.php" hx-vals='{"action": "unwatch", "item_id": <?= $item_id ?>}'>
                            <i class="fas fa-heart me-2"></i> Unwatch
                        </button>
                    <?php else: ?>
                        <button class="btn btn-outline-danger d-flex align-items-center"
                                hx-post="view_item.php" hx-vals='{"action": "watch", "item_id": <?= $item_id ?>}'>
                            <i class="far fa-heart me-2"></i> Watch
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body d-flex">
                <div class="col-6 me-3">
                    <div id="carousel-indicators-thumb-vertical" class="carousel slide carousel-fade"
                         data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php
                            foreach ($images as $index => $image) {
                                ?>
                                <div class="carousel-item<?= $index === 0 ? " active" : "" ?>">
                                    <img class="d-block w-100" alt=""
                                         src="./data/<?= htmlspecialchars($image['filename']) ?>">
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <div class="carousel-indicators carousel-indicators-vertical carousel-indicators-thumb">
                            <?php foreach ($images as $index => $image): ?>
                                <button type="button" data-bs-target="#carousel-indicators-thumb-vertical"
                                        data-bs-slide-to="<?= $index ?>"
                                        class="ratio ratio-4x3<?= $index === 0 ? ' active' : '' ?>"
                                        style="background-image: url(./data/<?= htmlspecialchars($image['filename']) ?>)"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="mb-3">
                        <label class="form-label h1">Current Price</label>
                        <div class="display-6 fw-bold my-1">£<?= htmlspecialchars($item['current_price']) ?></div>
                    </div>

                    // 在显示当前价格的div后添加
                    <div class="mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">Market Analysis</h4>

                                <!-- 智能建议价格 -->
                                <div class="price-suggestion mb-3">
                                    <label class="form-label">Smart Price Suggestion</label>
                                    <div class="h4 text-success">
                                        £<?= number_format($item['suggested_price'], 2) ?>
                                    </div>
                                </div>

                                <!-- 市场活跃度 -->
                                <div class="market-activity mb-3">
                                    <label class="form-label">Market Activity</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="card bg-light">
                                                <div class="card-body p-2">
                                                    <div class="text-muted small">Bids/Hour</div>
                                                    <div class="h5"><?= number_format($item['bid_frequency_per_hour'], 1) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light">
                                                <div class="card-body p-2">
                                                    <div class="text-muted small">Unique Bidders</div>
                                                    <div class="h5"><?= $item['unique_bidders'] ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 价格趋势 -->
                                <div class="price-trends mb-3">
                                    <label class="form-label">Price Trends</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="card bg-light">
                                                <div class="card-body p-2">
                                                    <div class="text-muted small">Avg. Price</div>
                                                    <div class="h5">£<?= number_format($item['trend_avg_price'], 2) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="card bg-light">
                                                <div class="card-body p-2">
                                                    <div class="text-muted small">Avg. Increment</div>
                                                    <div class="h5">£<?= number_format($item['avg_bid_increment'], 2) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 如果价格高于趋势，显示警告 -->
                                <?php if ($item['current_price'] > $item['trend_avg_price'] * 1.2): ?>
                                    <div class="alert alert-warning mb-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Current price is significantly higher than market average.
                                    </div>
                                <?php endif; ?>

                                <!-- 竞价建议按钮 -->
                                <?php if (!$auction_ended): ?>
                                    <button class="btn btn-outline-success w-100"
                                            onclick="document.querySelector('[name=bid_amount]').value = <?= $item['suggested_price'] ?>">
                                        Use Suggested Price
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php
                    if ($auction_ended) {

                        ?>
                        <div class="mb-3 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-check"
                                 width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="green" fill="none"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M5 12l5 5l10 -10"/>
                            </svg>
                            <div class="display-6 fw-bold my-1 text-success">Auction Ended</div>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="mb-3 d-flex justify-content-between me-3">
                            <div>
                                <label class="form-label h3">Reserve Price</label>
                                <div class="display-6 fw-bold my-1"><?= $item['reserve_price'] ? "£" . $item['reserve_price'] : "Not set" ?></div>
                            </div>
                            <div>
                                <label class="form-label h3">Minimum bid</label>
                                <div class="display-6 fw-bold my-1">
                                    £<?= htmlspecialchars($item['bid_increment'] + $item['current_price']) ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bid ends at</label>
                            <div class="display-6 fw-bold my-1 countdown text-danger"
                                 data-end="<?= htmlspecialchars($item['end_date']) ?>"></div>
                            <small class="text-muted">Hurry up! Place your bid before time runs out.</small>
                        </div>
                    <?php } ?>

                    <div class="mb-3">
                        <label class="form-label h3 text-muted">Seller</label>
                        <div class="d-flex align-items-center">
                            <div class="display-6 fw-bold my-1 text-primary me-2"><?= htmlspecialchars($seller_username) ?></div>
                            <div class="d-flex align-items-center">
                            <span class="gl-star-rating gl-star-rating--ltr">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= floor($seller_rating)) {
                                        echo '<i class="fas fa-star text-warning"></i>';
                                    } elseif ($i - 0.5 <= $seller_rating) {
                                        echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                    } else {
                                        echo '<i class="far fa-star text-warning"></i>';
                                    }
                                }
                                echo " ($seller_total)";
                                ?>
                            </span>
                            </div>
                        </div>
                    </div>

                    <?php
                    if (! $auction_ended) {
                        ?>
                        <div class="mb-3">
                            <label class="form-label">Your Bid</label>
                            <div class="input-group">
                                <span class="input-group-text">£</span>
                                <input type="number" class="form-control" name="bid_amount">
                            </div>
                            <button type="button" class="btn btn-purple w-100 mt-3"
                                    hx-post="view_item.php"
                                    hx-trigger="click"
                                    hx-swap="none"
                                    hx-disable-elt="button"
                                    hx-vals='js:{
                                        action: "bid",
                                        item_id: <?= $item_id ?>,
                                        bid_amount: document.querySelector("[name=bid_amount]").value,
                                    }'>
                                Place Bid
                            </button>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="card-footer">
                <p class="card-text markdown"><?= htmlspecialchars($item['description']) ?></p>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-header">
                <h2 class="mb-0">Bid List</h2>
            </div>
            <div class="card-body">
                <?php if (! empty($bids)): ?>
                    <table class="table">
                        <thead>
                        <tr>
                            <th scope="col">Bidder</th>
                            <th scope="col">Bid Amount</th>
                            <th scope="col">Bid Time</th>
                            <th scope="col">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bids as $bid): ?>
                            <tr>
                                <td><?= htmlspecialchars(substr($bid['username'], 0, 1) . str_repeat('*', strlen($bid['username']) - 2) . substr($bid['username'], -1)) ?></td>
                                <td>£<?= htmlspecialchars($bid['bid_price']) ?></td>
                                <td><?= htmlspecialchars($bid['bid_time']) ?></td>
                                <td>
                                    <?php if ($bid['status'] === 'pending'): ?>
                                        <i class="fas fa-clock text-warning"></i>
                                    <?php elseif ($bid['status'] === 'won'): ?>
                                        <i class="fas fa-check text-success"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times text-danger"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">No bids yet.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty">
            <div class="empty-header">404</div>
            <p class="empty-title">Item not exist</p>
            <p class="empty-subtitle text-secondary">
                That item does not exist. Please check the URL or try again later.
            </p>
            <div class="empty-action">
                <a href="./." class="btn btn-primary">
                    <!-- Download SVG icon from http://tabler-icons.io/i/arrow-left -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                         stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                         stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M5 12l14 0"/>
                        <path d="M5 12l6 6"/>
                        <path d="M5 12l6 -6"/>
                    </svg>
                    Take me home
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const markdownElements = document.querySelectorAll('.markdown');
        markdownElements.forEach(element => {
            element.innerHTML = marked.parse(element.textContent);
        });
    });

    document.addEventListener("DOMContentLoaded", function () {
        const countdownElements = document.querySelectorAll('.countdown');
        countdownElements.forEach(element => {
            const endDate = new Date(element.getAttribute('data-end'));
            countdown(endDate, ts => {
                element.textContent = `${ts.days}d ${ts.hours}h ${ts.minutes}m ${ts.seconds}s`.replace(/\b0[dhms]\b/g, '');
            });
        });
    });
    // 添加价格分析自动更新
    if (!<?= json_encode($auction_ended) ?>) {
        setInterval(function() {
            fetch(`view_item.php?id=<?= $item_id ?>&action=refresh`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 更新价格和分析数据
                        document.querySelector('.current-price').textContent =
                            '£' + parseFloat(data.current_price).toFixed(2);
                        document.querySelector('.suggested-price').textContent =
                            '£' + parseFloat(data.suggested_price).toFixed(2);
                        document.querySelector('.bid-frequency').textContent =
                            parseFloat(data.bid_frequency_per_hour).toFixed(1);
                        document.querySelector('.trend-avg-price').textContent =
                            '£' + parseFloat(data.trend_avg_price).toFixed(2);
                    }
                });
        }, 30000); // 每30秒更新一次
    }
    });
</script>
<?php include_once("footer.php") ?>
