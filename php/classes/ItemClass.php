<?php
// classes/ItemClass.php

class Item {
     
    private $conn; // PDO database connection instance

    public function __construct(PDO $dbConnection) {
        $this->conn = $dbConnection;
    }

    public function postItem(array $itemData, int $seller_id): array {
        try {
            // Category ID handling (already present)
            $categoryId = $itemData['category'];
            if (!is_numeric($categoryId) || (int)$categoryId <= 0) {
                return ['status' => 'error', 'message' => 'Invalid category ID provided.'];
            }
            $categoryId = (int)$categoryId;

            $query = "INSERT INTO items
                        (name, description, category_id, brand, item_condition, size, price, image_url, seller_id)
                        VALUES
                        (:name, :description, :category_id, :brand, :item_condition, :size, :price, :image_url, :seller_id)";

            $stmt = $this->conn->prepare($query);

            // Bind parameters (these keys should now definitely exist due to client-side data collection and server-side validation)
            $stmt->bindParam(':name', $itemData['name']);
            $stmt->bindParam(':description', $itemData['description']);
            $stmt->bindParam(':brand', $itemData['brand']);
            $stmt->bindParam(':item_condition', $itemData['condition']); // Make sure HTML input name is 'condition'
            $stmt->bindParam(':price', $itemData['price']);

            $stmt->bindParam(':image_url', $itemData['image_path']); // This is the image URL now

            $stmt->bindParam(':seller_id', $seller_id, PDO::PARAM_INT);
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);

            // Handle 'size' as an optional field. Bind as NULL if not provided or empty.
            // JavaScript now explicitly sends 'size: null' if not provided
            if (isset($itemData['size']) && $itemData['size'] !== null && $itemData['size'] !== '') {
                $stmt->bindParam(':size', $itemData['size']);
            } else {
                $nullSize = null;
                $stmt->bindParam(':size', $nullSize, PDO::PARAM_NULL);
            }

            $stmt->execute();

            return ['status' => 'success', 'message' => 'Item added successfully.', 'item_id' => $this->conn->lastInsertId()];

        } catch (PDOException $e) {
            error_log("Database error in postItem: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Database error: Could not add item.'];
        }
    }

    public function getAllItems() {
            try {
                $query = "SELECT
                            i.*,                 
                            u.username           
                        FROM
                            items i              
                        JOIN
                            users u ON i.seller_id = u.id
                        WHERE
                            i.is_sold = 0
                        ORDER BY
                            i.id DESC;           
                        ";

                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                return $items;

            } catch (PDOException $e) {

                error_log("Database error in getAllItems: " . $e->getMessage());
                
                return false;
            }
    }

    public function getItemById(int $id): ?array {
        try {
            $query = "SELECT
                          i.id,
                          i.name,
                          i.description,
                          i.price,
                          i.image_url,
                          i.item_condition,
                          i.category_id,
                          c.name AS category_name,
                          i.brand,
                          i.size,
                          i.seller_id,
                          u.username as seller_username
                      FROM
                          items i
                      JOIN
                          users u ON i.seller_id = u.id
                      JOIN
                          categories c ON i.category_id = c.id
                      WHERE
                          i.id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            return $item ?: null;

        } catch (PDOException $e) {
            error_log("Database error in getItemById: " . $e->getMessage());
            return null;
        }
    }

public function createOrder(int $buyerId, array $itemIds, float $totalPrice, array $shippingDetails): array
{
    if (empty($itemIds)) {
        error_log("Attempted to create an order with an empty item ID array for buyer ID: " . $buyerId);
        return ['status' => 'error', 'message' => 'Cannot create an order with no items.'];
    }

    $this->conn->beginTransaction();

    try {
        // Extract shipping details from the object
        $shippingName = $shippingDetails['name'];
        $shippingStreet = $shippingDetails['address']['street'];
        $shippingCity = $shippingDetails['address']['city'];
        $shippingState = $shippingDetails['address']['state'];
        $shippingZip = $shippingDetails['address']['zip'];
        
        // 1. Create the Order with shipping details
        $orderQuery = "INSERT INTO orders (buyer_id, total_price, shipping_name, shipping_street, shipping_city, shipping_state, shipping_zip) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $orderStmt = $this->conn->prepare($orderQuery);
        $orderStmt->bindValue(1, $buyerId, PDO::PARAM_INT);
        $orderStmt->bindValue(2, $totalPrice, PDO::PARAM_STR);
        $orderStmt->bindValue(3, $shippingName, PDO::PARAM_STR);
        $orderStmt->bindValue(4, $shippingStreet, PDO::PARAM_STR);
        $orderStmt->bindValue(5, $shippingCity, PDO::PARAM_STR);
        $orderStmt->bindValue(6, $shippingState, PDO::PARAM_STR);
        $orderStmt->bindValue(7, $shippingZip, PDO::PARAM_STR);
        $orderStmt->execute();
        $orderId = $this->conn->lastInsertId();

        if (!$orderId) {
            throw new Exception("Failed to retrieve new order ID after insertion.");
        }

        // 2. Insert into orderitems for each item
        $orderItemsQuery = "INSERT INTO orderitems (order_id, item_id) VALUES (?, ?)";
        $orderItemsStmt = $this->conn->prepare($orderItemsQuery);

        foreach ($itemIds as $itemId) {
            $orderItemsStmt->bindValue(1, $orderId, PDO::PARAM_INT);
            $orderItemsStmt->bindValue(2, $itemId, PDO::PARAM_INT);
            $orderItemsStmt->execute();
        }

        // 3. Mark the items as sold and remove them from all carts
        $placeholder = implode(',', array_fill(0, count($itemIds), '?'));

        $updateItemsQuery = "UPDATE items SET is_sold = 1 WHERE id IN ($placeholder)";
        $updateItemsStmt = $this->conn->prepare($updateItemsQuery);
        $updateItemsStmt->execute($itemIds);

        $deleteCartQuery = "DELETE FROM cart WHERE item_id IN ($placeholder)";
        $deleteCartStmt = $this->conn->prepare($deleteCartQuery);
        $deleteCartStmt->execute($itemIds);

        $this->conn->commit();
        return ['status' => 'success', 'message' => 'Order created successfully.', 'order_id' => (int)$orderId];

    } catch (Exception $e) {
        $this->conn->rollBack();
        error_log("Transaction failed for createOrder: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Failed to process your order. Please try again later.'];
    }
}
    
// In ItemClass.php

public function markItemsAsSold(array $itemIds): bool {
    if (empty($itemIds)) {
        error_log("markItemsAsSold: Attempted to mark items as sold with an empty ID array.");
        return false;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

        $query = "UPDATE items
                  SET is_sold = 1
                  WHERE id IN (" . $placeholders . ") AND is_sold = 0"; // ADDED CONDITION: Only update if not already sold

        $stmt = $this->conn->prepare($query);

        foreach ($itemIds as $index => $id) {
            $stmt->bindValue(($index + 1), $id, PDO::PARAM_INT);
        }

        $stmt->execute();

        $affectedRows = $stmt->rowCount();
        $expectedRows = count($itemIds);

        if ($affectedRows > 0) {
            if ($affectedRows < $expectedRows) {
                error_log("markItemsAsSold: Partial success. Expected to mark " . $expectedRows . " items sold, but only " . $affectedRows . " were affected. Some items may have been already sold or non-existent. Item IDs: " . implode(',', $itemIds));
                // Depending on your logic, you might still want to return true here if *some* were sold.
                // For now, let's keep it strict: all must be sold.
            }
            return true; // At least one item was marked sold, or all if affectedRows == expectedRows
        } else {
            error_log("markItemsAsSold: No items were marked as sold. This could be because they don't exist, are already sold, or a query issue. Item IDs: " . implode(',', $itemIds));
            return false; // No items were affected
        }

    } catch (PDOException $e) {
        error_log("Database error in markItemsAsSold: " . $e->getMessage() . " for Item IDs: " . implode(',', $itemIds));
        return false;
    }
}

public function putItemInCart($userId, $itemId) {
    try {
        // 1. Check if the item is already in the cart
        $checkQuery = "SELECT COUNT(*) FROM cart WHERE user_id = :user_id AND item_id = :item_id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $checkStmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        $checkStmt->execute();
        $itemCount = $checkStmt->fetchColumn();

        // If itemCount is greater than 0, the item already exists
        if ($itemCount > 0) {
            return ['status' => 'error', 'message' => 'Item is already in your cart.'];
        }

        // 2. If not, proceed with the INSERT query
        $insertQuery = "INSERT INTO cart (user_id, item_id) VALUES (:user_id, :item_id)";
        $insertStmt = $this->conn->prepare($insertQuery);
        $insertStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $insertStmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        $insertStmt->execute();

        return ['status' => 'success', 'message' => 'Item added to cart successfully.'];

    } catch (PDOException $e) {
        error_log("Database error in putItemInCart: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Could not add item to cart.'];
    }
}

public function getCartItems($userId) {
    try {
        $query = "SELECT i.*, c.id AS cart_id
                  FROM cart c
                  JOIN items i ON c.item_id = i.id
                  WHERE c.user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database error in getCartItems: " . $e->getMessage());
        return [];
    }
    }

    public function removeItemFromCart($userId, $itemId) {
        try {
            $query = "DELETE FROM cart WHERE user_id = :user_id AND item_id = :item_id";
            $stmt = $this->conn->prepare($query);

            // Bind parameters securely
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);

            $stmt->execute();
            $rowCount = $stmt->rowCount(); // Get the number of affected rows

            if ($rowCount > 0) {
                // An item was successfully removed
                return ['status' => 'success', 'message' => 'Item removed from cart.'];
            } else {
                // No rows were affected, meaning the item wasn't in the cart
                return ['status' => 'error', 'message' => 'Item not found in cart.'];
            }

        } catch (PDOException $e) {
            error_log("Database error in removeItemFromCart: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Could not remove item from cart.'];
        }
    }

    public function getUserProfileImageURL($userId){
        try {
            $query = "SELECT profile_pic_url FROM users WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && isset($result['profile_pic_url'])) {
                return $result['profile_pic_url'];
            } else {
                return null; // No profile image found
            }

        } catch (PDOException $e) {
            error_log("Database error in getUserProfileImageURL: " . $e->getMessage());
            return null; // Error occurred, return null
    }
    }
}
