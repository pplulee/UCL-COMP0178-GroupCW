<?php
include_once("header.php");
$selectedID = isset($_GET['category']) ? (int) $_GET['category'] : 0;
?>
    <title></title>
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
                            <a class="list-group-item list-group-item-action d-flex align-items-center<?= $selectedID == 0 ? "active" : "" ?>">
                                All
                            </a>
                            <?php
                            global $conn;
                            $result = $conn->query("SELECT * FROM category");
                            $result = $result->fetchAll();
                            foreach ($result as $row) {
                                $selected = ($selectedID == $row['id']) ? "active" : "";
                                ?>
                                <a class="list-group-item list-group-item-action d-flex align-items-center <?php echo $selected ?>"
                                   href="browse.php?category=<?php echo $row['id'] ?>">
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </a>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-9">

                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
// Retrieve these from the URL
if (! isset($_GET['keyword'])) {
    // TODO: Define behavior if a keyword has not been specified.
} else {
    $keyword = $_GET['keyword'];
}

if (! isset($_GET['cat'])) {
    // TODO: Define behavior if a category has not been specified.
} else {
    $category = $_GET['cat'];
}

if (! isset($_GET['order_by'])) {
    // TODO: Define behavior if an order_by value has not been specified.
} else {
    $ordering = $_GET['order_by'];
}

if (! isset($_GET['page'])) {
    $curr_page = 1;
} else {
    $curr_page = $_GET['page'];
}

/* TODO: Use above values to construct a query. Use this query to
   retrieve data from the database. (If there is no form data entered,
   decide on appropriate default value/default query to make. */

/* For the purposes of pagination, it would also be helpful to know the
   total number of results that satisfy the above query */
$num_results = 96; // TODO: Calculate me for real
$results_per_page = 10;
$max_page = ceil($num_results / $results_per_page);
?>

    <div class="container mt-5">

        <!-- TODO: If result set is empty, print an informative message. Otherwise... -->

        <ul class="list-group">

            <!-- TODO: Use a while loop to print a list item for each auction listing
                 retrieved from the query -->

            <?php
            // Demonstration of what listings will look like using dummy data.
            $item_id = "87021";
            $title = "Dummy title";
            $description = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum eget rutrum ipsum. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Phasellus feugiat, ipsum vel egestas elementum, sem mi vestibulum eros, et facilisis dui nisi eget metus. In non elit felis. Ut lacus sem, pulvinar ultricies pretium sed, viverra ac sapien. Vivamus condimentum aliquam rutrum. Phasellus iaculis faucibus pellentesque. Sed sem urna, maximus vitae cursus id, malesuada nec lectus. Vestibulum scelerisque vulputate elit ut laoreet. Praesent vitae orci sed metus varius posuere sagittis non mi.";
            $current_price = 30;
            $num_bids = 1;
            $end_date = new DateTime('2020-09-16T11:00:00');

            // This uses a function defined in utilities.php
            print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_date);

            $item_id = "516";
            $title = "Different title";
            $description = "Very short description.";
            $current_price = 13.50;
            $num_bids = 3;
            $end_date = new DateTime('2020-11-02T00:00:00');

            print_listing_li($item_id, $title, $description, $current_price, $num_bids, $end_date);
            ?>

        </ul>

        <!-- Pagination for results listings -->
        <nav aria-label="Search results pages" class="mt-5">
            <ul class="pagination justify-content-center">

                <?php

                // Copy any currently-set GET variables to the URL.
                $querystring = "";
                foreach ($_GET as $key => $value) {
                    if ($key != "page") {
                        $querystring .= "$key=$value&amp;";
                    }
                }

                $high_page_boost = max(3 - $curr_page, 0);
                $low_page_boost = max(2 - ($max_page - $curr_page), 0);
                $low_page = max(1, $curr_page - 2 - $low_page_boost);
                $high_page = min($max_page, $curr_page + 2 + $high_page_boost);

                if ($curr_page != 1) {
                    echo('
    <li class="page-item">
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page - 1) . '" aria-label="Previous">
        <span aria-hidden="true"><i class="fa fa-arrow-left"></i></span>
        <span class="sr-only">Previous</span>
      </a>
    </li>');
                }

                for ($i = $low_page; $i <= $high_page; $i++) {
                    if ($i == $curr_page) {
                        // Highlight the link
                        echo('
    <li class="page-item active">');
                    } else {
                        // Non-highlighted link
                        echo('
    <li class="page-item">');
                    }

                    // Do this in any case
                    echo('
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . $i . '">' . $i . '</a>
    </li>');
                }

                if ($curr_page != $max_page) {
                    echo('
    <li class="page-item">
      <a class="page-link" href="browse.php?' . $querystring . 'page=' . ($curr_page + 1) . '" aria-label="Next">
        <span aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
        <span class="sr-only">Next</span>
      </a>
    </li>');
                }
                ?>

            </ul>
        </nav>


    </div>


<?php include_once("footer.php") ?>