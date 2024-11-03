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

// TODO: Recommendation based of watch list
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
                                            <p class="card-text">Current Price:
                                                £<?= htmlspecialchars($item['current_price']) ?></p>
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
    </script>


<?php include_once("footer.php") ?>