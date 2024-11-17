<?php
include_once "include/common.php";
global $conn;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        header('Content-Type: application/json');
        if (! isset($_POST['action'])) {
            http_response_code(400);
            echo json_encode(['ret' => 0, 'error' => 'Invalid request']);
            exit();
        }
        $action = $_POST['action'];
        switch ($action) {
            case 'pay':
                // Check if transaction belongs to user
                $stmt = $conn->prepare("SELECT * FROM transaction WHERE id = :id AND buyer_id = :buyer_id");
                $stmt->execute(['id' => $_POST['transaction_id'], 'buyer_id' => $_SESSION['user_id']]);
                if ($stmt->rowCount() == 0) {
                    http_response_code(403);
                    echo json_encode(['ret' => 0, 'error' => 'Transaction not found']);
                    exit();
                }
                $stmt = $conn->prepare("UPDATE transaction SET status = 'pending_ship', pay_at = NOW() WHERE id = :id");
                $stmt->execute(['id' => $_POST['transaction_id']]);
                echo json_encode(['ret' => 1, 'msg' => 'Payment successful']);
                header("HX-Refresh: true");
                break;
            case 'ship':
                // Check if transaction belongs to user
                $stmt = $conn->prepare("SELECT * FROM transaction WHERE id = :id AND seller_id = :seller_id");
                $stmt->execute(['id' => $_POST['transaction_id'], 'seller_id' => $_SESSION['user_id']]);
                if ($stmt->rowCount() == 0) {
                    http_response_code(403);
                    echo json_encode(['ret' => 0, 'error' => 'Transaction not found']);
                    exit();
                }
                $stmt = $conn->prepare("UPDATE transaction SET status = 'shipped', tracking_number = :tracking_number, ship_at = NOW() WHERE id = :id");
                $stmt->execute(['id' => $_POST['transaction_id'], 'tracking_number' => $_POST['tracking_number']]);
                echo json_encode(['ret' => 1, 'msg' => 'Shipment successful']);
                header("HX-Refresh: true");
                break;
            case 'confirm_received':
                // Check if transaction belongs to user
                $stmt = $conn->prepare("SELECT * FROM transaction WHERE id = :id AND buyer_id = :buyer_id");
                $stmt->execute(['id' => $_POST['transaction_id'], 'buyer_id' => $_SESSION['user_id']]);
                if ($stmt->rowCount() == 0) {
                    http_response_code(403);
                    echo json_encode(['ret' => 0, 'error' => 'Transaction not found']);
                    exit();
                }
                $stmt = $conn->prepare("UPDATE transaction SET status = 'finished', finished_at = NOW() WHERE id = :id");
                $stmt->execute(['id' => $_POST['transaction_id']]);
                echo json_encode(['ret' => 1, 'msg' => 'Transaction completed']);
                header("HX-Refresh: true");
                break;
            case 'review':
                // Check if transaction belongs to user and eligible for review
                $stmt = $conn->prepare("SELECT * FROM transaction WHERE id = :id AND (buyer_id = :buyer_id OR seller_id = :seller_id) AND status = 'finished'");
                $stmt->execute(['id' => $_POST['transaction_id'], 'buyer_id' => $_SESSION['user_id'], 'seller_id' => $_SESSION['user_id']]);
                $transaction = $stmt->fetch();
                if ($stmt->rowCount() == 0) {
                    http_response_code(403);
                    echo json_encode(['ret' => 0, 'error' => 'Transaction not found or not eligible for review']);
                    exit();
                }
                $stmt = $conn->prepare("SELECT * FROM review WHERE transaction_id = :transaction_id AND reviewer_id = :reviewer_id");
                $stmt->execute([
                    'transaction_id' => $_POST['transaction_id'],
                    'reviewer_id' => $_SESSION['user_id']
                ]);
                if ($stmt->rowCount() > 0) {
                    http_response_code(403);
                    echo json_encode(['ret' => 0, 'error' => 'Review already submitted']);
                    exit();
                }
                $antiXss = new voku\helper\AntiXSS();
                $_POST = $antiXss->xss_clean($_POST);
                // Validation on data
                $result = validate($_POST, [
                    'rating' => 'required|numeric|min:1|max:5',
                    'comment' => 'required'
                ], [
                    'rating:required' => 'Rating is required',
                    'rating:numeric' => 'Rating must be a number',
                    'rating:min' => 'Rating must be at least 1',
                    'rating:max' => 'Rating must be at most 5',
                    'comment:required' => 'Comment is required'
                ]);
                if ($result['ret'] === 0) {
                    echo json_encode($result);
                    exit();
                }
                // Fetch reviewee
                $reviewee = $transaction['buyer_id'] == $_SESSION['user_id'] ? $transaction['seller_id'] : $transaction['buyer_id'];
                $stmt = $conn->prepare("INSERT INTO review (transaction_id, reviewer_id, reviewee_id, rating, comment) VALUES (:transaction_id, :reviewer_id, :reviewee_id, :rating, :comment)");
                $stmt->execute([
                    'transaction_id' => $_POST['transaction_id'],
                    'reviewer_id' => $_SESSION['user_id'],
                    'reviewee_id' => $reviewee,
                    'rating' => $_POST['rating'],
                    'comment' => $_POST['comment']
                ]);
                echo json_encode(['ret' => 1, 'msg' => 'Review submitted successfully']);
                header("HX-Refresh: true");
                exit();
            default:
                http_response_code(400);
                echo json_encode(['ret' => 0, 'msg' => 'Invalid action']);
                exit();
        }
        exit();
    case 'GET':
        include_once("header.php");
        $type = $_GET['type'] ?? "all";
        switch ($type) {
            case 'buyer':
                $stmt = $conn->prepare("SELECT * FROM transaction WHERE buyer_id = :buyer_id");
                $stmt->execute(['buyer_id' => $_SESSION['user_id']]);
                break;
            case 'seller':
                $stmt = $conn->prepare("SELECT * FROM transaction WHERE seller_id = :seller_id");
                $stmt->execute(['seller_id' => $_SESSION['user_id']]);
                break;
            case 'all':
                $stmt = $conn->prepare("SELECT * FROM transaction WHERE buyer_id = :buyer_id OR seller_id = :seller_id");
                $stmt->execute(['buyer_id' => $_SESSION['user_id'], 'seller_id' => $_SESSION['user_id']]);
                break;
            default:
                http_response_code(400);
                exit();
        }
        $transactions = $stmt->fetchAll();
        break;
    default:
        http_response_code(405);
        exit();
}
?>
<title><?= env('app_name') ?> - My Transactions</title>
<body>
<div class="page-wrapper">
    <div class="page-header">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        My Transactions
                    </h2>
                    <div class="col-auto">
                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                Select Type
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?type=all">All</a></li>
                                <li><a class="dropdown-item" href="?type=buyer">Buyer</a></li>
                                <li><a class="dropdown-item" href="?type=seller">Seller</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">
                <?php foreach ($transactions as $transaction): ?>
                    <?php
                    // Fetch auction item details
                    $stmt = $conn->prepare("SELECT * FROM AuctionItem WHERE id = :id");
                    $stmt->execute(['id' => $transaction['auction_item_id']]);
                    $auction_item = $stmt->fetch();

                    // Fetch buyer and seller usernames
                    $stmt = $conn->prepare("SELECT username, email FROM user WHERE id = :id");
                    $stmt->execute(['id' => $transaction['buyer_id']]);
                    $buyer = $stmt->fetch();

                    $stmt->execute(['id' => $transaction['seller_id']]);
                    $seller = $stmt->fetch();

                    // Determine order type
                    $order_type = $transaction['buyer_id'] == $_SESSION['user_id'] ? 'Buyer' : 'Seller';
                    $order_type_class = $order_type == 'Buyer' ? 'bg-primary text-primary-fg' : 'bg-secondary text-secondary-fg';

                    // Check if the order is eligible for review
                    $reviewed = true;
                    if ($transaction['status'] == 'finished') {
                        $stmt = $conn->prepare("SELECT * FROM review WHERE transaction_id = :transaction_id AND reviewer_id = :reviewer_id");
                        $stmt->execute([
                            'transaction_id' => $transaction['id'],
                            'reviewer_id' => $_SESSION['user_id']
                        ]);
                        if ($stmt->rowCount() == 0) {
                            $reviewed = false;
                        }
                    }
                    ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col card-title">
                                    <h3 class="mb-0"><a href="view_item.php?id=<?= $auction_item['id'] ?>"
                                                        target="_blank"><?= $auction_item['name'] ?></a></h3>
                                </div>
                                <div class="col-md-auto ms-auto">
                                    <span class="badge <?= $order_type_class ?>"><?= $order_type ?></span>
                                    <?php
                                    $badgeClass = '';
                                    $badgeText = '';

                                    switch ($transaction['status']) {
                                        case 'pending_payment':
                                            $badgeClass = 'bg-yellow text-yellow-fg';
                                            $badgeText = 'Pending Payment';
                                            break;
                                        case 'pending_ship':
                                            $badgeClass = 'bg-blue text-blue-fg';
                                            $badgeText = 'Pending Shipment';
                                            break;
                                        case 'shipped':
                                            $badgeClass = 'bg-purple text-purple-fg';
                                            $badgeText = 'Shipped';
                                            break;
                                        case 'finished':
                                            $badgeClass = 'bg-green text-green-fg';
                                            $badgeText = 'Finished';
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
                        <div class="card-body">
                            <div class="datagrid">
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Price</div>
                                    <div class="datagrid-content">£<?= $transaction['price'] ?></div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Payment Method</div>
                                    <div class="datagrid-content"><?= $transaction['payment_method'] ?></div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Buyer</div>
                                    <div class="datagrid-content"><?= $buyer['username'] ?> (<a
                                                href="mailto:<?= $buyer['email'] ?>"><?= $buyer['email'] ?></a>)
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Seller</div>
                                    <div class="datagrid-content"><?= $seller['username'] ?> (<a
                                                href="mailto:<?= $seller['email'] ?>"><?= $seller['email'] ?></a>)
                                    </div>
                                </div>
                            </div>
                            <?php if ($order_type == 'Seller') : ?>
                                <hr>
                                <div class="datagrid">
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">Shipping Address</div>
                                        <div class="datagrid-content"><?= $transaction['address'] ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($transaction['status'] == 'shipped') : ?>
                                <hr>
                                <div class="datagrid">
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">Tracking Number</div>
                                        <div class="datagrid-content"><?= $transaction['tracking_number'] ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($transaction['status'] == 'finished'): ?>
                                <?php
                                $review = $conn->prepare("SELECT * FROM review WHERE transaction_id = :transaction_id AND reviewee_id = :user_id");
                                $review->execute(['transaction_id' => $transaction['id'], 'user_id' => $_SESSION['user_id']]);
                                if ($review->rowCount() == 0) {
                                    $review = null;
                                } else {
                                    $review = $review->fetch();
                                }
                                ?>
                                <hr>
                                <div class="review-section">
                                    <div class="review-title h1">Review</div>
                                    <?php if ($review == null): ?>
                                        <div class="no-review">No review yet</div>
                                    <?php else: ?>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= floor($review['rating'])): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                <?php elseif ($i - 0.5 <= $review['rating']): ?>
                                                    <i class="fas fa-star-half-alt text-warning"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-warning"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-comment"><?= htmlspecialchars($review['comment']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        </div>
                        <div class="card-footer">
                            <?php if ($order_type == 'Buyer' && $transaction['status'] == 'pending_payment'): ?>
                                <button class="btn btn-primary"
                                        hx-post="transactions.php"
                                        hx-trigger="click"
                                        hx-disable-elt="this"
                                        hx-vals="js:{transaction_id: <?= $transaction['id'] ?>, action: 'pay'}"
                                >
                                    Pay Now
                                </button>
                            <?php elseif ($order_type == 'Seller' && $transaction['status'] == 'pending_ship'): ?>
                                <button class="btn btn-yellow"
                                        hx-post="transactions.php"
                                        hx-trigger="click"
                                        hx-disable-elt="this"
                                        hx-vals="js:{transaction_id: <?= $transaction['id'] ?>, action: 'ship', tracking_number: prompt('Enter tracking number')}"
                                >Ship Now
                                </button>
                            <?php elseif ($order_type == 'Buyer' && $transaction['status'] == 'shipped'): ?>
                                <button class="btn btn-green"
                                        hx-post="transactions.php"
                                        hx-trigger="click"
                                        hx-disable-elt="this"
                                        hx-vals="js:{transaction_id: <?= $transaction['id'] ?>, action: 'confirm_received'}"
                                >Confirm Received
                                </button>
                            <?php endif; ?>
                            <?php
                            // Check if the order is eligible for review
                            $reviewed = true;
                            if ($transaction['status'] == 'finished') {
                                $stmt = $conn->prepare("SELECT * FROM review WHERE transaction_id = :transaction_id AND reviewer_id = :reviewer_id");
                                $stmt->execute([
                                    'transaction_id' => $transaction['id'],
                                    'reviewer_id' => $_SESSION['user_id']
                                ]);
                                if ($stmt->rowCount() == 0) {
                                    $reviewed = false;
                                }
                            }
                            ?>
                            <?php if (! $reviewed): ?>
                                <button class="btn btn-secondary review-button" data-id="<?= $transaction['id'] ?>">
                                    Leave a Review
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</body>

<!-- Modal -->
<div class="modal modal-blur fade" id="reviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reviewForm"
                      hx-post="transactions.php"
                      hx-trigger="submit"
                      hx-swap="none"
                      hx-vals="js:{transaction_id: document.getElementById('transaction_id').value, action: 'review'}">
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <div class="gl-star-rating gl-star-rating--ltr" id="star-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="far fa-star text-warning" data-value="<?= $i ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comment</label>
                        <textarea class="form-control" name="comment" rows="3" required></textarea>
                    </div>
                    <input type="hidden" name="rating" id="rating">
                    <input type="hidden" name="transaction_id" id="transaction_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" form="reviewForm">Submit Review</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reviewButtons = document.querySelectorAll('.review-button');
        const reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
        const transactionIdInput = document.getElementById('transaction_id');
        const ratingInput = document.getElementById('rating');
        const stars = document.querySelectorAll('#star-rating .fa-star');

        reviewButtons.forEach(button => {
            button.addEventListener('click', function () {
                transactionIdInput.value = this.getAttribute('data-id');
                reviewModal.show();
            });
        });

        stars.forEach(star => {
            star.addEventListener('click', function () {
                const value = this.getAttribute('data-value');
                ratingInput.value = value;
                stars.forEach(s => {
                    s.classList.remove('fas');
                    s.classList.add('far');
                });
                for (let i = 0; i < value; i++) {
                    stars[i].classList.remove('far');
                    stars[i].classList.add('fas');
                }
            });
        });
    });
</script>
<?php include_once("footer.php") ?>

