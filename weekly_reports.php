<?php
// Database Connection
$host = 'localhost';
$db   = 'apna_niwala_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Calendar Month/Year Navigation & Selected Date
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_date = $_GET['date'] ?? null;

// If a specific date is clicked, filter by that single day, otherwise default to the full month
if ($selected_date) {
    $start_date = $selected_date;
    $end_date   = $selected_date;
} else {
    $start_date = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
    $end_date   = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
}

// Fetch Payments & Orders for Range
$stmt = $pdo->prepare("
    SELECT p.*, c.name as client_name, c.phone_no 
    FROM payments p
    JOIN clients c ON p.client_id = c.client_id
    WHERE p.meal_date BETWEEN ? AND ?
    ORDER BY p.meal_date DESC
");
$stmt->execute([$start_date, $end_date]);
$reports = $stmt->fetchAll();

// Fetch all dates in this month that have orders (to highlight on calendar)
$highlight_stmt = $pdo->prepare("
    SELECT DISTINCT meal_date 
    FROM payments 
    WHERE meal_date BETWEEN ? AND ?
");
$highlight_stmt->execute([date('Y-m-01', mktime(0, 0, 0, $month, 1, $year)), date('Y-m-t', mktime(0, 0, 0, $month, 1, $year))]);
$active_dates = $highlight_stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate Summary Metrics
$total_billed = 0;
$total_received = 0;
$total_pending = 0;

foreach($reports as $r) {
    $total_billed += $r['meal_amount'];
    $total_received += $r['amount_received'];
    $total_pending += $r['total_due'];
}

// Calendar math
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_index = date('w', mktime(0, 0, 0, $month, 1, $year)); // 0 (Sun) to 6 (Sat)
$month_name = date('F Y', mktime(0, 0, 0, $month, 1, $year));

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month == 0) { $prev_month = 12; $prev_year--; }

$next_month = $month + 1;
$next_year = $year;
if ($next_month == 13) { $next_month = 1; $next_year++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Calendar - Apna Niwala</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-amber-50 font-sans">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 p-8 overflow-y-auto">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h1 class="text-3xl font-bold text-stone-800">Reports & Calendar Analytics</h1>
                
                <?php if($selected_date): ?>
                    <a href="?month=<?= $month ?>&year=<?= $year ?>" class="bg-stone-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-stone-700">Clear Date Filter (Show Full Month)</a>
                <?php endif; ?>
            </div>

            <!-- Calendar Widget Section -->
            <div class="bg-white p-6 rounded-xl shadow-md mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-stone-700"><?= $month_name ?></h2>
                    <div class="flex gap-2">
                        <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="px-3 py-1 bg-stone-100 hover:bg-stone-200 rounded-lg text-sm font-bold text-stone-600">&larr; Prev</a>
                        <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="px-3 py-1 bg-stone-100 hover:bg-stone-200 rounded-lg text-sm font-semibold text-stone-600">Today</a>
                        <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" class="px-3 py-1 bg-stone-100 hover:bg-stone-200 rounded-lg text-sm font-bold text-stone-600">Next &rarr;</a>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="grid grid-cols-7 gap-2 text-center">
                    <!-- Day Headers -->
                    <span class="text-xs font-bold text-stone-400 py-1">Sun</span>
                    <span class="text-xs font-bold text-stone-400 py-1">Mon</span>
                    <span class="text-xs font-bold text-stone-400 py-1">Tue</span>
                    <span class="text-xs font-bold text-stone-400 py-1">Wed</span>
                    <span class="text-xs font-bold text-stone-400 py-1">Thu</span>
                    <span class="text-xs font-bold text-stone-400 py-1">Fri</span>
                    <span class="text-xs font-bold text-stone-400 py-1">Sat</span>

                    <?php 
                    // Blank spaces for previous month offset
                    for ($i = 0; $i < $first_day_index; $i++): 
                    ?>
                        <div></div>
                    <?php endfor; ?>

                    <?php 
                    // Render days of the month
                    for ($day = 1; $day <= $days_in_month; $day++):
                        $current_loop_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $has_orders = in_array($current_loop_date, $active_dates);
                        $is_selected = ($selected_date == $current_loop_date);
                        $is_today = ($current_loop_date == date('Y-m-d'));

                        // Styling classes for calendar cells
                        $bg_class = "bg-stone-50 text-stone-700 hover:bg-stone-100";
                        if ($has_orders) {
                            $bg_class = "bg-emerald-50 text-emerald-900 border border-emerald-300 hover:bg-emerald-100 font-semibold";
                        }
                        if ($is_selected) {
                            $bg_class = "bg-emerald-600 text-white font-bold shadow-md";
                        }
                    ?>
                        <a href="?month=<?= $month ?>&year=<?= $year ?>&date=<?= $current_loop_date ?>" 
                           class="p-3 rounded-xl flex flex-col items-center justify-center transition <?= $bg_class ?>">
                            <span class="text-sm"><?= $day ?></span>
                            <?php if($has_orders && !$is_selected): ?>
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-1"></span>
                            <?php endif; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-5 rounded-xl shadow-md border-l-4 border-blue-500">
                    <p class="text-stone-500 text-sm font-semibold">Total Billed Amount</p>
                    <h3 class="text-2xl font-bold text-stone-800 mt-1">₹<?= number_format($total_billed, 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-md border-l-4 border-emerald-500">
                    <p class="text-stone-500 text-sm font-semibold">Amount Received</p>
                    <h3 class="text-2xl font-bold text-emerald-600 mt-1">₹<?= number_format($total_received, 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-md border-l-4 border-red-500">
                    <p class="text-stone-500 text-sm font-semibold">Total Pending Dues</p>
                    <h3 class="text-2xl font-bold text-red-600 mt-1">₹<?= number_format($total_pending, 2) ?></h3>
                </div>
            </div>

            <!-- Detailed Transactions Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="p-4 border-b font-bold text-stone-700 bg-stone-50 flex justify-between items-center">
                    <span>Transactions List (<?= $start_date ?><?= ($start_date != $end_date) ? ' to ' . $end_date : '' ?>)</span>
                    <span class="text-xs text-stone-500 font-normal"><?= count($reports) ?> records found</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b bg-stone-100 text-stone-600 text-sm">
                                <th class="p-3">Date</th>
                                <th class="p-3">Customer Name</th>
                                <th class="p-3">Meal / Plan</th>
                                <th class="p-3">Type</th>
                                <th class="p-3">Total (₹)</th>
                                <th class="p-3">Paid (₹)</th>
                                <th class="p-3">Due (₹)</th>
                                <th class="p-3">Mode</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y">
                            <?php if(empty($reports)): ?>
                                <tr>
                                    <td colspan="8" class="p-6 text-center text-stone-400">No records found for this selection.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($reports as $row): ?>
                                    <tr class="hover:bg-stone-50">
                                        <td class="p-3 text-stone-600"><?= $row['meal_date'] ?></td>
                                        <td class="p-3 font-semibold text-stone-800"><?= htmlspecialchars($row['client_name']) ?><br><span class="text-xs font-normal text-stone-500"><?= $row['phone_no'] ?></span></td>
                                        <td class="p-3 text-stone-800"><?= htmlspecialchars($row['meal_name']) ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 text-xs rounded font-bold <?= ($row['diet_type'] == 'Veg') ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $row['diet_type'] ?>
                                            </span>
                                        </td>
                                        <td class="p-3 font-bold text-stone-800">₹<?= number_format($row['meal_amount'], 2) ?></td>
                                        <td class="p-3 text-emerald-600 font-semibold">₹<?= number_format($row['amount_received'], 2) ?></td>
                                        <td class="p-3 font-semibold <?= ($row['total_due'] > 0) ? 'text-red-600' : 'text-stone-500' ?>">₹<?= number_format($row['total_due'], 2) ?></td>
                                        <td class="p-3 text-stone-600"><?= $row['payment_mode'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>