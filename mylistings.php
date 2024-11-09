<?php include_once("header.php") ?>
<?php require("utilities.php") ?>

    <div class="container">

    <h2 class="my-3">My listings</h2>

<?php
// This page is for showing a user the auction listings they've made.
// It will be pretty similar to browse.php, except there is no search bar.
// This can be started after browse.php is working with a database.
// Feel free to extract out useful functions from browse.php and put them in
// the shared "utilities.php" where they can be shared by multiple files.

// Get the logged-in user's ID
$user_id = $_SESSION['user_id'];

// Connect to the database (assuming utilities.php has a function getDB())
$conn = getDB();

// Query to retrieve the listings created by the logged-in user
$sql = "SELECT auction_item_id, item_name, description, starting_price, end_date 
        FROM auction_item 
        WHERE user_id = ? 
        ORDER BY end_date DESC";

 if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if any listings were found
        if ($result->num_rows > 0) {
            // Display the listings
            while ($row = $result->fetch_assoc()) {
                $item_id = $row['auction_item_id'];
                $item_name = htmlspecialchars($row['item_name']);
                $description = htmlspecialchars($row['description']);
                $starting_price = number_format($row['starting_price'], 2);
                $end_date = date("F j, Y, g:i a", strtotime($row['end_date']));

                echo "<div class='listing-item my-3 p-3 border'>";
                echo "<h3><a href='listing.php?id=$item_id'>$item_name</a></h3>";
                echo "<p>" . (strlen($description) > 150 ? substr($description, 0, 150) . "..." : $description) . "</p>"; // Limit description length
                echo "<p><strong>Starting Price:</strong> $$starting_price</p>";
                echo "<p><strong>End Date:</strong> $end_date</p>";
                echo "<a href='edit_listing.php?id=$item_id' class='btn btn-warning btn-sm'>Edit</a> ";
                echo "<a href='delete_listing.php?id=$item_id' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this listing?\");'>Delete</a>";
                echo "</div>";
            }
        } else {
            echo "<p class='text-muted'>You have no active listings at the moment. <a href='create_listing.php'>Create a new listing</a> to get started!</p>";
        }

        // Close the statement and connection
        $stmt->close();
    } else {
        echo "<p class='text-danger'>An error occurred while fetching your listings. Please try again later.</p>";

    $conn->close();
}
?>

    </div>

// TODO: Check user's credentials (cookie/session).

// TODO: Perform a query to pull up their auctions.

// TODO: Loop through results and print them out as list items.

?>

<?php include_once("footer.php") ?>