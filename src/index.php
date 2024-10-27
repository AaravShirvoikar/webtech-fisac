<?php
// Database connection
function connectDB() {
    $host = getenv('MYSQL_HOST');
    $user = getenv('MYSQL_USER');
    $pass = getenv('MYSQL_PASSWORD');
    $db = getenv('MYSQL_DATABASE');
    
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Get all categories
function getCategories($conn) {
    $result = $conn->query("SELECT * FROM Categories ORDER BY category_name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Display all products with filtering
function displayProducts($conn) {
    $category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    
    $query = "SELECT p.product_id, p.product_name, p.price, p.stock_quantity, c.category_name 
              FROM Products p 
              JOIN Categories c ON p.category_id = c.category_id";
              
    if ($category_filter > 0) {
        $query .= " WHERE p.category_id = " . $category_filter;
    }
    
    $result = $conn->query($query);
    
    // Get categories for filter dropdown
    $categories = getCategories($conn);
    
    echo "<div class='filter-section'>
            <form method='get' class='category-filter'>
                <select name='category' onchange='this.form.submit()'>
                    <option value='0'>All Categories</option>";
    foreach ($categories as $category) {
        $selected = ($category_filter == $category['category_id']) ? 'selected' : '';
        echo "<option value='{$category['category_id']}' {$selected}>{$category['category_name']}</option>";
    }
    echo "</select>
          </form>
          </div>";
    
    echo "<div class='product-grid'>";
    while($row = $result->fetch_assoc()) {
        echo "<div class='product-card'>
                <h3>{$row['product_name']}</h3>
                <p class='category'>{$row['category_name']}</p>
                <p class='price'>₹{$row['price']}</p>
                <p class='stock'>Stock: {$row['stock_quantity']}</p>
                <form method='post' class='order-form'>
                    <input type='hidden' name='product_id' value='{$row['product_id']}'>
                    <input type='number' name='quantity' min='1' max='{$row['stock_quantity']}' value='1' required>
                    <button type='submit' name='place_order'>Order Now</button>
                </form>
              </div>";
    }
    echo "</div>";
}

// Place new order
function placeOrder($conn, $product_id, $quantity) {
    try {
        $conn->begin_transaction();
        
        $stmt = $conn->prepare("SELECT product_name, stock_quantity FROM Products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        if ($product['stock_quantity'] < $quantity) {
            throw new Exception("Insufficient stock. Available: " . $product['stock_quantity']);
        }
        
        $stmt = $conn->prepare("UPDATE Products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
        $stmt->bind_param("ii", $quantity, $product_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("INSERT INTO Orders (product_id, order_quantity) VALUES (?, ?)");
        $stmt->bind_param("ii", $product_id, $quantity);
        $stmt->execute();
        
        $conn->commit();
        return ["success" => true, "message" => "Successfully ordered {$quantity} x {$product['product_name']}"];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ["success" => false, "message" => $e->getMessage()];
    }
}

// Display order history
function displayOrders($conn) {
    $result = $conn->query("SELECT o.order_id, p.product_name, o.order_quantity, o.order_date, 
                           (p.price * o.order_quantity) as total_price
                           FROM Orders o 
                           JOIN Products p ON o.product_id = p.product_id 
                           ORDER BY o.order_date DESC
                           LIMIT 10");
    
    if ($result->num_rows > 0) {
        echo "<div class='order-history'>
                <h2>Recent Orders</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>";
        
        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>#{$row['order_id']}</td>
                    <td>{$row['product_name']}</td>
                    <td>{$row['order_quantity']}</td>
                    <td>₹{$row['total_price']}</td>
                    <td>" . date('M d, Y H:i', strtotime($row['order_date'])) . "</td>
                  </tr>";
        }
        
        echo "</tbody></table></div>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce Store</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1, h2 {
            color: #333;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .filter-section {
            margin: 20px 0;
        }
        .category-filter select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .product-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .product-card h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .category {
            color: #666;
            font-size: 0.9em;
        }
        .price {
            font-size: 1.2em;
            color: #2c5282;
            font-weight: bold;
        }
        .stock {
            color: #666;
        }
        .order-form {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .order-form input[type="number"] {
            width: 60px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #2c5282;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2a4365;
        }
        .order-history {
            margin-top: 40px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #2c5282;
            color: white;
        }
        tr:hover {
            background-color: #f8f8f8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>E-commerce Store</h1>
        
        <?php
        $conn = connectDB();

        // Handle order submission
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
            $result = placeOrder($conn, $_POST['product_id'], $_POST['quantity']);
            if ($result['success']) {
                echo "<div class='message success'>{$result['message']}</div>";
            } else {
                echo "<div class='message error'>Error: {$result['message']}</div>";
            }
        }

        // Display products and orders
        displayProducts($conn);
        displayOrders($conn);

        $conn->close();
        ?>
    </div>
</body>
</html>
