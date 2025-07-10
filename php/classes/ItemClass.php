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
    
    public function markItemsAsSold(array $itemIds): bool {
        if (empty($itemIds)) {
            // Log this as a debug message if helpful, but not necessarily an error
            error_log("Attempted to mark items as sold with an empty ID array.");
            return false;
        }

        try {
            // Create a string of question mark placeholders for the IN clause (?, ?, ?)
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

            $query = "UPDATE items
                      SET is_sold = 1
                      WHERE id IN (" . $placeholders . ")";

            $stmt = $this->conn->prepare($query);

            // Bind each ID to its positional placeholder
            // PDO bindValue and bindParam are 1-indexed for positional parameters
            foreach ($itemIds as $index => $id) {
                // Using bindValue for safety with foreach loop to ensure current value is bound
                $stmt->bindValue(($index + 1), $id, PDO::PARAM_INT);
            }

            $stmt->execute();

            // Return true if one or more rows were affected, indicating success
            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log("Database error in markItemsAsSold: " . $e->getMessage());
            return false;
        }
    }
}
