<?php
include_once("header.php");
$selectedID = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$startPrice = isset($_GET['startPrice']) ? (float) $_GET['startPrice'] : null;
$endPrice = isset($_GET['endPrice']) ? (float) $_GET['endPrice'] : null;
$keyword = $_GET['keyword'] ?? "";
?>
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


<?php include_once("footer.php") ?>