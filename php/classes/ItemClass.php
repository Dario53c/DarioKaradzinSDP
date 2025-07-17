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

        public function createOrder(int $buyerId, array $itemIds) {
        if (empty($itemIds)) {
            error_log("Attempted to create an order with an empty item ID array for buyer ID: " . $buyerId);
            return false;
        }

        // Start a transaction
        $this->conn->beginTransaction();

        try {
            // 1. Create the Order
            $orderQuery = "INSERT INTO orders (buyer_id) VALUES (?)";
            $orderStmt = $this->conn->prepare($orderQuery);
            $orderStmt->bindValue(1, $buyerId, PDO::PARAM_INT);
            $orderStmt->execute();

            $orderId = $this->conn->lastInsertId();

            if (!$orderId) {
                // This should ideally not happen if the INSERT was successful
                // but good to check.
                throw new Exception("Failed to retrieve new order ID after insertion.");
            }

            // 2. Insert into Order_Items for each item
            // Using a single prepared statement for multiple inserts for efficiency
            $orderItemsQuery = "INSERT INTO orderitems (order_id, item_id) VALUES (?, ?)";
            $orderItemsStmt = $this->conn->prepare($orderItemsQuery);

            foreach ($itemIds as $itemId) {
                $orderItemsStmt->bindValue(1, $orderId, PDO::PARAM_INT);
                $orderItemsStmt->bindValue(2, $itemId, PDO::PARAM_INT);
                // Execute for each item
                $orderItemsStmt->execute();
            }

            // 3. Mark the items as sold in the 'items' table
            // Reusing your existing method
            $itemsMarkedSold = $this->markItemsAsSold($itemIds);

            if (!$itemsMarkedSold) {
                throw new Exception("Failed to mark one or more items as sold.");
            }

            // If all operations succeed, commit the transaction
            $this->conn->commit();
            return (int)$orderId; // Return the new order ID

        } catch (Exception $e) { // Catch Exception to include custom exceptions
            // Something went wrong, rollback the transaction
            $this->conn->rollBack();
            error_log("Transaction failed for createOrder: " . $e->getMessage());
            return false; // Indicate failure
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
}
