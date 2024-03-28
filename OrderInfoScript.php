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

function getTest($pdo, $order_id){
    $stmt = $pdo->prepare("SELECT Products.product_id, 
       Products.name AS product_name, 
       Orders_Products.quantity AS quantity,
       Products.main_shelf_id AS main_shelf,
    CASE 
        WHEN array_agg(Additional_Shelves_For_Products.shelf_id) IS NULL THEN '{}'::text[]
        ELSE array_agg(Additional_Shelves_For_Products.shelf_id)
    END AS additional_shelves
FROM Orders_Products
JOIN Products ON Orders_Products.product_id = Products.product_id
LEFT JOIN Additional_Shelves_For_Products ON Orders_Products.product_id = Additional_Shelves_For_Products.product_id
WHERE Orders_Products.order_id = :order_id
GROUP BY Products.product_id, Products.name, Orders_Products.quantity, Products.main_shelf_id");
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($argc < 2) {
    die("Необходимо указать номера заказов\n");
}

$args = $argv[1];

$order_ids = explode(',', $args);

$order_info =[];

foreach ($order_ids as $order_id) {
    $products = getTest($pdo, $order_id);
    $order_info[$order_id] = $products;
}

function displayProductsByShelves($order_info) {
    $shelf_info = [];

    foreach ($order_info as $order_id => $products) {
        foreach ($products as $product) {
            $product_info = $product['product_name'] . " (id=" . $product['product_id'] . ")\n" .
                "заказ $order_id, " . $product['quantity'] . " шт";
            $additional_shelves = $product['additional_shelves'];
            if ($additional_shelves !== "{NULL}") {
                $additional_shelves = ($additional_shelves !== null) ? explode(',', trim($additional_shelves, '{}')) : [];
                $additional_shelves_str = implode(',', $additional_shelves);
                $product_info .= "\nдоп стеллаж: " . $additional_shelves_str;
            }
            $shelf_info[$product['main_shelf']][] = $product_info;
        }
    }

    foreach ($shelf_info as $shelf => $products_info) {
        echo "===Стеллаж $shelf\n";
        foreach ($products_info as $product_info) {
            echo $product_info . "\n\n";
        }
    }
}

echo "=+=+=+=\n";
echo 'Страница сборки заказов ' . $args . "\n";
displayProductsByShelves($order_info);