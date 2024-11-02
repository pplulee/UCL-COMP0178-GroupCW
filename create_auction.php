<?php
include_once "include/common.php";
global $conn;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        header('Content-Type: application/json');
        $auctionItem = new model\AuctionItem();
        $result = $auctionItem->create($_POST);
        echo json_encode($result);
        exit();
    case 'GET':
        include_once("header.php");
        $categories = (new model\AuctionItem)->getCategories();
        break;
    default:
        http_response_code(405);
        exit();
}
?>
    <title><?= env('app_name') ?> - Post Item</title>
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
    <link href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" rel="stylesheet" type="text/css"/>
    <body>
    <div class="page">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Post Item</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="auction-form" action="">
                                <div class="mb-3">
                                    <label class="form-label required">Item Name</label>
                                    <input type="text" class="form-control" name="name" placeholder="Enter item title"
                                           required>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12 col-md">
                                        <label class="form-label required">Start Price (£)</label>
                                        <input type="number" class="form-control" name="start_price"
                                               placeholder="Enter start price" required>
                                    </div>
                                    <div class="col-12 col-md">
                                        <label class="form-label">Reserve Price (£)</label>
                                        <input type="number" class="form-control" name="reserve_price"
                                               placeholder="Enter reserve price" required>
                                    </div>
                                    <div class="col-12 col-md">
                                        <label class="form-label required">Bid Increment (£)</label>
                                        <input type="number" class="form-control" name="bid_increment"
                                               placeholder="Enter bid increment" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Category</label>
                                    <select class="form-control" name="category_id" required>
                                        <option value="0" disabled selected>Select a category</option>
                                        <?php foreach ($categories as $id => $name): ?>
                                            <option value="<?= $id ?>"><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Description</label>
                                    <textarea class="form-control" name="description" id="description"
                                              placeholder="Enter item description" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Auction End Date</label>
                                    <input type="datetime-local" class="form-control" name="end_date" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Upload Images</label>
                                    <div class="dropzone" id="myDropzone"></div>
                                </div>
                                <div class="form-footer">
                                    <button type="submit" class="btn btn-primary submit" id="submit-button">
                                        Submit
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script>
        var easyMDE = new EasyMDE({element: document.getElementById('description'), spellChecker: false});
    </script>
    <script>
        Dropzone.options.myDropzone = {
            url: 'create_auction.php',
            dictDefaultMessage: "Drop images here or click to upload",
            autoProcessQueue: false,
            uploadMultiple: true,
            parallelUploads: 5,
            maxFiles: 10,
            maxFilesize: 10,
            acceptedFiles: 'image/*',
            addRemoveLinks: true,
            resizeWidth: 1024,
            resizeMimeType: 'image/jpeg',
            init: function () {
                var dzClosure = this;
                document.querySelector('.submit').addEventListener("click", function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (dzClosure.getQueuedFiles().length === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Please upload at least one image'
                        });
                        return;
                    }
                    dzClosure.processQueue();
                });
                this.on("sendingmultiple", function (data, xhr, formData) {
                    formData.append("name", document.querySelector('input[name=name]').value);
                    formData.append("description", easyMDE.value());
                    formData.append("start_price", document.querySelector('input[name=start_price]').value);
                    formData.append("reserve_price", document.querySelector('input[name=reserve_price]').value);
                    formData.append("bid_increment", document.querySelector('input[name=bid_increment]').value);
                    formData.append("category_id", document.querySelector('select[name=category_id]').value);
                    formData.append("end_date", document.querySelector('input[name=end_date]').value);
                });
                this.on("successmultiple", function (files, response) {
                    if (response.ret === 1) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Item posted',
                            text: response.msg,
                            showConfirmButton: true,
                            timer: 2000,
                            timerProgressBar: true,
                            allowOutsideClick: false
                        }).then(function () {
                            window.location.href = 'mylistings.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.msg
                        });
                    }
                    dzClosure.removeAllFiles();
                    document.querySelector('.submit').disabled = false;
                });
                this.on("errormultiple", function (files, response) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response
                    });
                    dzClosure.removeAllFiles();
                    document.querySelector('.submit').disabled = false;
                });
                this.on("processing", function () {
                    document.querySelector('.submit').disabled = true;
                });
            }
        }
    </script>
    </body>
<?php include_once("footer.php") ?>