<?php

namespace model;

use Ramsey\Uuid\Uuid;
use voku\helper\AntiXSS;

class AuctionItem
{
    public int $id;
    public int $seller_id;
    public int $category_id;
    public string $name;
    public string $description;
    public float $start_price;
    public ?float $reserve_price;
    public float $current_price;
    public float $bid_increment;
    public string $start_date;
    public string $end_date;
    public string $status;

    public function __construct()
    {
        $this->id = 0;
        $this->seller_id = 0;
        $this->category_id = 0;
        $this->name = '';
        $this->description = '';
        $this->start_price = 0.0;
        $this->reserve_price = null;
        $this->current_price = 0.0;
        $this->bid_increment = 0.0;
        $this->start_date = '';
        $this->end_date = '';
        $this->status = '';
    }

    public function create($data): array
    {
        $antiXss = new AntiXSS();
        $data = $antiXss->xss_clean($data);
        $data['description'] = htmlspecialchars($data['description']);
        $data['end_date'] = str_replace('T', ' ', $data['end_date']);
        $data['images'] = $_FILES;
        $data['reserve_price'] = empty($data['reserve_price']) ? null : $data['reserve_price'];
        $categories = $this->getCategories();
        $categoryIds = implode(',', array_keys($categories));
        $result = validate($data, [
            'name' => 'required|max:255',
            'category_id' => 'required|in:' . $categoryIds,
            'start_price' => 'required|numeric|min:0',
            'reserve_price' => 'numeric|min:0',
            'bid_increment' => 'required|numeric|min:0',
            'description' => 'required',
            'end_date' => 'required|after:now',
//            'images' => 'required|uploaded_file|max:5M|mimes:jpg,jpeg,png'
        ], [
            'name:required' => 'Item name is required',
            'name:max' => 'Item name must not exceed 255 characters',
            'category_id:required' => 'Category is required',
            'category_id:in' => 'Invalid category',
            'start_price:required' => 'Starting price is required',
            'start_price:numeric' => 'Starting price must be a number',
            'start_price:min' => 'Starting price must be at least 0',
            'reserve_price:numeric' => 'Reserve price must be a number',
            'reserve_price:min' => 'Reserve price must be at least 0',
            'bid_increment:required' => 'Bid increment is required',
            'bid_increment:numeric' => 'Bid increment must be a number',
            'bid_increment:min' => 'Bid increment must be at least 0',
            'description:required' => 'Description is required',
            'end_date:required' => 'End date is required',
            'end_date:after' => 'End date must be in the future',
//            'images.required' => 'Images are required',
//            'images.uploaded_file' => 'Failed to upload images',
//            'images.max' => 'Images must not exceed 5MB',
//            'images.mimes' => 'Invalid image format'
        ]);
        if ($result['ret'] === 0) {
            return $result;
        }
        if ($data['reserve_price'] > 0 && $data['reserve_price'] <= $data['start_price']) {
            return ['ret' => 0, 'msg' => 'Reserve price must be greater than starting price'];
        }
        // Validate files
        $images = $_FILES['file'];
        if (empty($data['images'])) {
            return ['ret' => 0, 'msg' => 'Images are required'];
        }
        foreach ($images['error'] as $error) {
            if ($error !== UPLOAD_ERR_OK) {
                return ['ret' => 0, 'msg' => 'Failed to upload images'];
            }
        }
        $data['seller_id'] = $_SESSION['user_id'];
        $data['current_price'] = $data['start_price'];
        $data['status'] = 'active';
        global $conn;
        $stmt = $conn->prepare("INSERT INTO AuctionItem (seller_id, category_id, name, description, start_price, current_price, reserve_price, bid_increment, start_date, end_date, status) VALUES (:seller_id, :category_id, :name, :description, :start_price, :current_price, :reserve_price, :bid_increment, NOW(), :end_date, :status)");
        $result = $stmt->execute([
            'seller_id' => $data['seller_id'],
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'start_price' => $data['start_price'],
            'current_price' => $data['current_price'],
            'reserve_price' => $data['reserve_price'],
            'bid_increment' => $data['bid_increment'],
            'end_date' => $data['end_date'],
            'status' => $data['status']
        ]);
        if ($result) {
            $auctionItemId = $conn->lastInsertId();
            foreach ($images['name'] as $index => $name) {
                $uuid = Uuid::uuid4()->toString();
                $imageName = $uuid . '.' . pathinfo($name, PATHINFO_EXTENSION);
                $imagePath = 'data/' . $imageName;
                move_uploaded_file($images['tmp_name'][$index], $imagePath);
                $stmt = $conn->prepare("INSERT INTO images (auction_item_id, filename) VALUES (:auction_item_id, :filename)");
                $stmt->execute([
                    'auction_item_id' => $auctionItemId,
                    'filename' => $imageName
                ]);
            }
            $result = ['ret' => 1, 'msg' => 'Item posted successfully'];
        } else {
            $result = ['ret' => 0, 'msg' => 'Failed to post item'];
        }
        return $result;
    }

    public function getCategories(): array
    {
        global $conn;
        $stmt = $conn->prepare("SELECT id, name FROM category");
        $stmt->execute();
        $categories = $stmt->fetchAll();
        return array_column($categories, 'name', 'id');
    }
}