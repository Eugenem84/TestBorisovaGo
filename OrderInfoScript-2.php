<?php
$host = '127.0.0.1';
$dbname = 'borisovatestdb';
$username = 'postgres';
$password = '1234';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Подключено к базе данных\n";
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

if ($argc < 2) {
    die("Необходимо указать номера заказов\n");
}

$args = $argv[1];

$order_ids = explode(',', $args);

$product_info = getProductInfoByOrderIds($pdo, $order_ids);

displayProductsByShelves($pdo, $product_info);

function getProductInfoByOrderIds($pdo, $order_ids)
{
    $product_info = [];
    foreach ($order_ids as $order_id) {
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM orders_products WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $product_info[$order_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $product_info;
}

function displayProductsByShelves($pdo, $product_info)
{
    $shelf_products = [];
    foreach ($product_info as $order_id => $products) {
        foreach ($products as $product) {
            $product_id = $product['product_id'];
            $quantity = $product['quantity'];

            $stmt = $pdo->prepare("SELECT shelf_name FROM shelves WHERE shelf_id = (SELECT main_shelf_id FROM products WHERE product_id = ?)");
            $stmt->execute([$product_id]);
            $shelf_name = $stmt->fetchColumn();

            $product_name_stmt = $pdo->prepare("SELECT name FROM products WHERE product_id = ?");
            $product_name_stmt->execute([$product_id]);
            $product_name = $product_name_stmt->fetchColumn();

            $additional_shelves_stmt = $pdo->prepare("SELECT shelf_name FROM shelves WHERE shelf_id IN (SELECT shelf_id FROM additional_shelves_for_products WHERE product_id = ?)");
            $additional_shelves_stmt->execute([$product_id]);
            $additional_shelves = $additional_shelves_stmt->fetchAll(PDO::FETCH_COLUMN);

            $shelf_products[$shelf_name][] = [
                'product_name' => $product_name,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'order_id' => $order_id,
                'additional_shelves' => $additional_shelves,
            ];
        }
    }

    foreach ($shelf_products as $shelf_name => $products) {
        echo "===Стеллаж $shelf_name\n";
        foreach ($products as $product) {
            echo $product['product_name'] . " (id=" . $product['product_id'] . ")\n";
            echo "заказ " . $product['order_id'] . ", " . $product['quantity'] . " шт\n";
            if (!empty($product['additional_shelves'])) {
                echo "доп стеллаж: " . implode(',', $product['additional_shelves']) . "\n";
            }
            echo "\n";
        }
    }
}
