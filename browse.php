<?php

use voku\helper\AntiXSS;

include_once("header.php");
$antiXss = new AntiXSS();
$_GET = $antiXss->xss_clean($_GET);
$selectedID = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$startPrice = isset($_GET['startPrice']) ? (float) $_GET['startPrice'] : null;
$endPrice = isset($_GET['endPrice']) ? (float) $_GET['endPrice'] : null;
$keyword = $_GET['keyword'] ?? "";
$recommend = isset($_GET['recommend']) && $_GET['recommend'] === 'true';
$watchListEmpty = false;
global $conn;

$query = "SELECT id, name, current_price, end_date, views  FROM AuctionItem WHERE status = 'active'";
$params = [];

// Recommendation
if ($recommend === true) {
    $watchlistSQL = $conn->prepare("SELECT DISTINCT AuctionItem.category_id as id FROM watch JOIN AuctionItem ON watch.auction_item_id = AuctionItem.id WHERE watch.buyer_id = ?");
    $watchlistSQL->execute([$_SESSION['user_id']]);
    $watchlistItems = $watchlistSQL->fetchAll();
    $watchlistItems = array_column($watchlistItems, 'id');

    if ($selectedID !== 0 && in_array($selectedID, $watchlistItems)) {
        $query .= " AND category_id = :category_id";
        $params[':category_id'] = $selectedID;
    } elseif (! empty($watchlistItems) && $selectedID === 0) {
        $query .= " AND category_id IN (" . implode(',', array_fill(0, count($watchlistItems), '?')) . ")";
        $params = array_merge($params, $watchlistItems);
    } else {
        // empty watchlist
        $query .= " AND 1=0";
        $watchListEmpty = true;
    }
} elseif ($selectedID !== 0) {
    $query .= " AND category_id = :category_id";
    $params[':category_id'] = $selectedID;
}

if ($keyword !== "") {
    // explode the keyword into individual words
    $keywords = explode(' ', $keyword);
    $keywordConditions = [];
    foreach ($keywords as $index => $word) {
        $paramName = ':keyword' . $index;
        $keywordConditions[] = "name LIKE $paramName";
        $params[$paramName] = '%' . $word . '%';
    }
    $query .= " AND (" . implode(' OR ', $keywordConditions) . ")";
}

if ($startPrice !== null) {
    $query .= " AND current_price >= :start_price";
    $params[':start_price'] = $startPrice;
}

if ($endPrice !== null) {
    $query .= " AND current_price <= :end_price";
    $params[':end_price'] = $endPrice;
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

?>
    <style>
        .carousel-inner img {
            width: 100%;
            height: auto;
            max-height: 300px;
            object-fit: cover;
        }
    </style>
    <title><?= env('app_name') ?> - Browse</title>
    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <?php if ($recommend): ?>
                    <div class="alert alert-info" role="alert">
                        Recommendations are based on the auction items you have watched.
                    </div>
                <?php endif; ?>
                <?php if ($watchListEmpty): ?>
                    <div class="alert alert-warning" role="alert">
                        No items to recommend. Try adding more items to your watchlist.
                    </div>
                <?php endif; ?>
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <h2 class="page-title">
                            Browse Items
                        </h2>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary" id="view-recommendations">
                            View Recommendations
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="page-body">
            <div class="container-xl">
                <div class="row g-4">
                    <div class="col-3">
                        <div class="subheader mb-2">Category</div>
                        <div class="list-group list-group-transparent mb-3" id="category-list">
                            <a class="list-group-item list-group-item-action d-flex align-items-center<?= $selectedID == 0 ? " active" : "" ?>"
                               href="javascript:void(0);" data-category="0">
                                All
                            </a>
                            <?php
                            global $conn;
                            $result = $conn->query("SELECT * FROM category");
                            $result = $result->fetchAll();
                            foreach ($result as $row) {
                                $selected = ($selectedID == $row['id']) ? " active" : "";
                                ?>
                                <a class="list-group-item list-group-item-action d-flex align-items-center<?= $selected ?>"
                                   href="javascript:void(0);" data-category="<?= $row['id'] ?>">
                                    <?= htmlspecialchars($row['name']); ?>
                                </a>
                                <?php
                            }
                            ?>
                        </div>
                        <div class="subheader mb-2">Price</div>
                        <div class="row g-2 align-items-center mb-3">
                            <div class="col">
                                <div class="input-group">
                                <span class="input-group-text">
                                  £
                                </span>
                                    <input type="number" id="startPrice" class="form-control" placeholder="from" min="0"
                                           autocomplete="off" value="<?= $startPrice === null ? "" : $startPrice ?>">
                                </div>
                            </div>
                            <div class="col-auto">—</div>
                            <div class="col">
                                <div class="input-group">
                                <span class="input-group-text">
                                  £
                                </span>
                                    <input type="number" id="endPrice" class="form-control" placeholder="to" min="0"
                                           autocomplete="off" value="<?= $endPrice === null ? "" : $endPrice ?>">
                                </div>
                            </div>
                        </div>
                        <div class="subheader mb-2">Keywords</div>
                        <div class="input-group">
                            <input type="text" id="keyword" class="form-control" placeholder="Enter keyword"
                                   autocomplete="off"
                                   value="<?= $keyword ?>">
                        </div>
                        <button class="btn btn-secondary mt-3 w-100" id="reset-filters"><i class="fas fa-sync-alt"></i>
                            Reset
                        </button>
                    </div>
                    <div class="col-9">
                        <div class="row row-cards">
                            <?php
                            foreach ($items as $item) {
                                // Fetch item images
                                $stmt = $conn->prepare("SELECT `filename` FROM images WHERE auction_item_id = ?");
                                $stmt->execute([$item['id']]);
                                $images = $stmt->fetchAll();
                                // Fetch watchers count
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM watch WHERE auction_item_id = ?");
                                $stmt->execute([$item['id']]);
                                $watchers = $stmt->fetchColumn();
                                ?>
                                <div class="col-sm-12 col-lg-6">
                                    <a href="view_item.php?id=<?= $item['id'] ?>" class="card card-sm" target="_blank">
                                        <div id="carousel-captions-<?= $item['id'] ?>" class="carousel slide">
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
                                            <button class="carousel-control-prev" type="button"
                                                    data-bs-target="#carousel-captions-<?= $item['id'] ?>"
                                                    data-bs-slide="prev">
                                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                <span class="visually-hidden">Previous</span>
                                            </button>
                                            <button class="carousel-control-next" type="button"
                                                    data-bs-target="#carousel-captions-<?= $item['id'] ?>"
                                                    data-bs-slide="next">
                                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                <span class="visually-hidden">Next</span>
                                            </button>
                                        </div>
                                        <div class="card-header">
                                            <h5 class="card-title"><?= htmlspecialchars($item['name']) ?></h5>
                                            <div class="card-actions btn-actions d-flex align-items-center">
                                                <?= $item['views'] ?> <i class="ti ti-eye"></i> &nbsp<?= $watchers ?> <i
                                                        class="ti ti-heart"></i>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h1 class="fw-bold text-primary">Current Price:
                                                £<?= htmlspecialchars($item['current_price']) ?></h1>
                                        </div>
                                        <div class="card-footer">
                                            <div class="row align-items-center">
                                                <div class="col-auto">
                                                    <?php
                                                    // Fetch bid count in the last 24 hours
                                                    $stmt = $conn->prepare("SELECT COUNT(*) FROM bid WHERE auction_item_id = ? AND bid_time >= NOW() - INTERVAL 1 DAY");
                                                    $stmt->execute([$item['id']]);
                                                    $bidCount = $stmt->fetchColumn();
                                                    ?>
                                                    <span class="fw-bold"><?= $bidCount ?> Bids in Last 24 Hours</span>
                                                </div>
                                                <div class="col-auto ms-auto">
                                                    <span>Ends at: </span><span class="countdown"
                                                                                data-end="<?= htmlspecialchars($item['end_date']) ?>"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const viewRecommendationsButton = document.getElementById('view-recommendations');
            const urlParams = new URLSearchParams(window.location.search);
            const recommend = urlParams.get('recommend') === 'true';

            if (recommend) {
                viewRecommendationsButton.textContent = 'View All Items';
            }

            viewRecommendationsButton.addEventListener('click', function () {
                if (recommend) {
                    urlParams.delete('recommend');
                } else {
                    urlParams.set('recommend', 'true');
                }
                window.location.search = urlParams.toString();
            });
        });

        document.addEventListener("DOMContentLoaded", function () {
            const categoryList = document.getElementById('category-list');
            const startPriceInput = document.getElementById('startPrice');
            const endPriceInput = document.getElementById('endPrice');
            const keywordInput = document.getElementById('keyword');
            const resetButton = document.getElementById('reset-filters');

            categoryList.addEventListener('click', function (event) {
                if (event.target.tagName === 'A') {
                    const category = event.target.getAttribute('data-category');
                    updateURL({category});
                }
            });

            startPriceInput.addEventListener('input', debounce(updateURL, 1000));
            endPriceInput.addEventListener('input', debounce(updateURL, 1000));
            keywordInput.addEventListener('input', debounce(updateURL, 1000));

            resetButton.addEventListener('click', function () {
                startPriceInput.value = '';
                endPriceInput.value = '';
                keywordInput.value = '';
                updateURL({category: 0, startPrice: '', endPrice: '', keyword: '', recommend: ''});
            });

            function updateURL(params) {
                const url = new URL(window.location.href);
                Object.keys(params).forEach(key => {
                    if (params[key]) {
                        url.searchParams.set(key, params[key]);
                    } else {
                        url.searchParams.delete(key);
                    }
                });
                window.location.href = url.toString();
            }

            function debounce(func, wait) {
                let timeout;
                return function () {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, arguments), wait);
                };
            }
        });
    </script>

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
    </script>


<?php include_once("footer.php") ?>