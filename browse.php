<?php

use voku\helper\AntiXSS;

include_once("header.php");
$antiXss = new AntiXSS();
$_GET = $antiXss->xss_clean($_GET);
$selectedID = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$startPrice = isset($_GET['startPrice']) ? (float) $_GET['startPrice'] : null;
$endPrice = isset($_GET['endPrice']) ? (float) $_GET['endPrice'] : null;
$keyword = $_GET['keyword'] ?? "";
global $conn;
$stmt = $conn->prepare("SELECT * FROM AuctionItem WHERE status = 'active'");
$stmt->execute();
$items = $stmt->fetchAll();
// TODO: Implement the search functionality

$sql = "SELECT * FROM AuctionItem WHERE status = 'active'";

// Add filters based on user input
$conditions = [];
$params = [];
$types = "";

// Category filter
if ($selectedID > 0) {
    $conditions[] = "category_id = ?";
    $params[] = $selectedID;
    $types .= "i";
}

// Price range filters
if ($startPrice !== null) {
    $conditions[] = "current_price >= ?";
    $params[] = $startPrice;
    $types .= "d"; // 'd' for float
}
if ($endPrice !== null) {
    $conditions[] = "current_price <= ?";
    $params[] = $endPrice;
    $types .= "d"; // 'd' for float
}

// Keyword filter
if (!empty($keyword)) {
    $conditions[] = "(name LIKE ? OR description LIKE ?)";
    $keywordParam = "%" . $keyword . "%";
    $params[] = $keywordParam;
    $params[] = $keywordParam;
    $types .= "ss"; // 's' for string
}

// Add conditions to the query if they exist
if (count($conditions) > 0) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$stmt = $conn->prepare($sql);

// Bind parameters dynamically
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result()->fetchAll();

// TODO: Recommendation based of watch list

// Get the logged-in user's watch list
$watchlistSQL = "SELECT auction_item_id FROM watchlist WHERE user_id = ?";
$watchlistStmt = $conn->prepare($watchlistSQL);
$watchlistStmt->bind_param("i", $_SESSION['user_id']);
$watchlistStmt->execute();
$watchlistItems = $watchlistStmt->get_result()->fetchAll(PDO::FETCH_COLUMN);

// If there are watchlist items, recommend similar ones
$recommendations = [];
if (count($watchlistItems) > 0) {
    // Fetch similar items based on categories of watched items
    $placeholders = implode(",", array_fill(0, count($watchlistItems), "?"));
    $recommendSQL = "SELECT * FROM AuctionItem WHERE status = 'active' AND id NOT IN ($placeholders) AND category_id IN (
                        SELECT DISTINCT category_id FROM AuctionItem WHERE id IN ($placeholders)
                    ) LIMIT 5";
    $recommendStmt = $conn->prepare($recommendSQL);
    $recommendParams = array_merge($watchlistItems, $watchlistItems);
    $recommendStmt->execute($recommendParams);
    $recommendations = $recommendStmt->fetchAll();
}

// Display recommended items
if (!empty($recommendations)) {
    echo "<div class='recommendations'>";
    echo "<h3>Recommended for You</h3>";
    foreach ($recommendations as $recItem) {
        // Display code similar to how you display listings
        echo "<div class='recommendation-item'>";
        echo "<h4><a href='view_item.php?id={$recItem['id']}'>{$recItem['name']}</a></h4>";
        echo "<p>" . htmlspecialchars($recItem['description']) . "</p>";
        echo "</div>";
    }
    echo "</div>";
}
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
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <h2 class="page-title">
                            Browse Items
                        </h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="page-body">
            <div class="container-xl">
                <div class="row g-4">
                    <div class="col-3">
                        <div class="subheader mb-2">Category</div>
                        <div class="list-group list-group-transparent mb-3">
                            <a class="list-group-item list-group-item-action d-flex align-items-center<?= $selectedID == 0 ? " active" : "" ?>"
                               href="browse.php?category=0">
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
                                   href="browse.php?category=<?= $row['id'] ?>">
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
                    </div>
                    <div class="col-9">
                        <div class="row row-cards">
                            <?php
                            foreach ($items as $item) {
                                // Fetch item images
                                $stmt = $conn->prepare("SELECT `filename` FROM images WHERE auction_item_id = ?");
                                $stmt->execute([$item['id']]);
                                $images = $stmt->fetchAll();
                                // TODO: how many people are watching this item
                                $watchers = 0;
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
                                                <?= $item['views'] ?> <i class="ti ti-eye"></i> &nbsp; <?= $watchers ?>
                                                <i class="ti ti-heart"></i>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text markdown"><?= htmlspecialchars($item['description']) ?></p>
                                        </div>
                                        <div class="card-footer">
                                            <div class="row align-items-center">
                                                <div class="col-auto">
                                                    <span>Current Price:
                                                        £<?= htmlspecialchars($item['current_price']) ?></span>
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
        let timeout = null;
        let redirectTimeout = 1000;

        function updateURL() {
            const startPrice = document.getElementById('startPrice').value;
            const endPrice = document.getElementById('endPrice').value;
            const keyword = document.getElementById('keyword').value;
            const url = new URL(window.location.href);
            if (startPrice) {
                url.searchParams.set('startPrice', startPrice);
            } else {
                url.searchParams.delete('startPrice');
            }
            if (endPrice) {
                url.searchParams.set('endPrice', endPrice);
            } else {
                url.searchParams.delete('endPrice');
            }
            if (keyword) {
                url.searchParams.set('keyword', keyword);
            } else {
                url.searchParams.delete('keyword');
            }
            window.location.href = url.toString();
        }

        document.getElementById('startPrice').addEventListener('input', function () {
            clearTimeout(timeout);
            timeout = setTimeout(updateURL, redirectTimeout);
        });

        document.getElementById('endPrice').addEventListener('input', function () {
            clearTimeout(timeout);
            timeout = setTimeout(updateURL, redirectTimeout);
        });

        document.getElementById('keyword').addEventListener('input', function () {
            clearTimeout(timeout);
            timeout = setTimeout(updateURL, redirectTimeout);
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