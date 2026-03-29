<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "Ltms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$typeFilter = isset($_GET['type']) ? $_GET['type'] : 'all';

$sql = "SELECT id, item_name, description, location, date, type
        FROM items";

if ($typeFilter !== 'all') {
    $sql .= " WHERE type = '" . $conn->real_escape_string($typeFilter) . "'";
}

$sql .= " ORDER BY date DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lost & Found Items</title>
    <style>
        body {
            font-family: Arial;
            margin: 40px;
            background: #f2f2f2;
        }
        h1 {
            text-align: center;
            margin-bottom: 5px;
        }
        .filters {
            text-align: center;
            margin-bottom: 20px;
        }
        .filters a {
            padding: 10px 15px;
            background: #ddd;
            margin: 0 5px;
            border-radius: 5px;
            text-decoration: none;
        }
        .filters a.active {
            background: #555;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ccc;
            text-align: left;
        }
        th {
            background: #eee;
        }
        .badge {
            padding: 5px 10px;
            color: white;
            border-radius: 4px;
        }
        .lost { background: #d9534f; }
        .found { background: #5cb85c; }
    </style>
</head>
<body>

<h1>Lost & Found Items</h1>

<div class="filters">
    <a href="?type=all" class="<?= $typeFilter=='all'?'active':'' ?>">All</a>
    <a href="?type=lost" class="<?= $typeFilter=='lost'?'active':'' ?>">Lost</a>
    <a href="?type=found" class="<?= $typeFilter=='found'?'active':'' ?>">Found</a>
</div>

<table>
    <tr>
        <th>Item</th>
        <th>Description</th>
        <th>Location</th>
        <th>Date</th>
        <th>Type</th>
    </tr>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['item_name']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td>
                    <span class="badge <?= $row['type'] === 'lost' ? 'lost' : 'found' ?>">
                        <?= ucfirst($row['type']) ?>
                    </span>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="5" style="text-align:center;">No items found</td></tr>
    <?php endif; ?>

</table>

</body>
</html>

<?php $conn->close(); ?>