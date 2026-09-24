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

// Selected Year (Default to current year 2026)
$selected_year = $_GET['year'] ?? date('Y');

// Handle CSV Export Request
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=financial_report_' . $selected_year . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Month', 'Orders Count', 'Veg Revenue (INR)', 'Non-Veg Revenue (INR)', 'Total Billed (INR)', 'Amount Received (INR)']);
    
    // Fetch data for export
    $export_stmt = $pdo->prepare("
        SELECT 
            MONTH(meal_date) as month_num,
            SUM(meal_amount) as monthly_billed,
            SUM(amount_received) as monthly_received,
            SUM(CASE WHEN diet_type = 'Veg' THEN meal_amount ELSE 0 END) as veg_revenue,
            SUM(CASE WHEN diet_type = 'Non-Veg' THEN meal_amount ELSE 0 END) as nonveg_revenue,
            COUNT(*) as monthly_orders
        FROM payments
        WHERE YEAR(meal_date) = ?
        GROUP BY MONTH(meal_date)
        ORDER BY month_num ASC
    ");
    $export_stmt->execute([$selected_year]);
    $export_data = $export_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $months_map = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
    $indexed_exp = [];
    foreach($export_data as $ex) { $indexed_exp[$ex['month_num']] = $ex; }

    foreach($months_map as $num => $name) {
        $row = $indexed_exp[$num] ?? null;
        fputcsv($output, [
            $name,
            $row ? $row['monthly_orders'] : 0,
            $row ? $row['veg_revenue'] : 0,
            $row ? $row['nonveg_revenue'] : 0,
            $row ? $row['monthly_billed'] : 0,
            $row ? $row['monthly_received'] : 0
        ]);
    }
    fclose($output);
    exit;
}

// Fetch Yearly Summary Totals
$stmt = $pdo->prepare("
    SELECT 
        SUM(meal_amount) as total_billed, 
        SUM(amount_received) as total_received, 
        SUM(total_due) as total_pending,
        COUNT(*) as total_orders
    FROM payments 
    WHERE YEAR(meal_date) = ?
");
$stmt->execute([$selected_year]);
$summary = $stmt->fetch();

// Fetch Monthly Breakdown for the Selected Year
$monthly_stmt = $pdo->prepare("
    SELECT 
        MONTH(meal_date) as month_num,
        SUM(meal_amount) as monthly_billed,
        SUM(amount_received) as monthly_received,
        SUM(CASE WHEN diet_type = 'Veg' THEN meal_amount ELSE 0 END) as veg_revenue,
        SUM(CASE WHEN diet_type = 'Non-Veg' THEN meal_amount ELSE 0 END) as nonveg_revenue,
        COUNT(*) as monthly_orders
    FROM payments
    WHERE YEAR(meal_date) = ?
    GROUP BY MONTH(meal_date)
    ORDER BY month_num ASC
");
$monthly_stmt->execute([$selected_year]);
$monthly_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Prepare arrays for Chart.js
$indexed_monthly = [];
foreach($monthly_data as $m) {
    $indexed_monthly[$m['month_num']] = $m;
}

$chart_labels = [];
$chart_veg = [];
$chart_nonveg = [];
foreach($months as $num => $name) {
    $chart_labels[] = substr($name, 0, 3);
    $chart_veg[] = $indexed_monthly[$num]['veg_revenue'] ?? 0;
    $chart_nonveg[] = $indexed_monthly[$num]['nonveg_revenue'] ?? 0;
}

$collection_rate = ($summary['total_billed'] > 0) ? ($summary['total_received'] / $summary['total_billed']) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly & Yearly Stats - Apna Niwala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-amber-50/60 font-sans text-stone-800">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 p-8 overflow-y-auto">
            <!-- Header & Actions -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80">
                <div>
                    <h1 class="text-3xl font-extrabold text-stone-900 tracking-tight">Financial Analytics</h1>
                    <p class="text-sm text-stone-500 mt-1">Comprehensive breakdown of orders, revenue streams, and collection performance.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Export CSV Button -->
                    <a href="?year=<?= $selected_year ?>&export=csv" class="bg-stone-100 hover:bg-stone-200 text-stone-700 px-4 py-2.5 rounded-xl text-sm font-semibold transition flex items-center gap-2 border">
                        <svg class="w-4 h-4 text-stone-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export CSV
                    </a>

                    <!-- Year Selector Form -->
                    <form method="GET" class="flex items-center bg-stone-50 p-1.5 rounded-xl border">
                        <select name="year" onchange="this.form.submit()" class="bg-transparent px-3 py-1 text-sm font-bold text-stone-800 focus:outline-none cursor-pointer">
                            <?php for($y = 2025; $y <= date('Y'); $y++): ?>
                                <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Top Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-500"></div>
                    <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Total Orders (<?= $selected_year ?>)</p>
                    <h3 class="text-3xl font-black text-stone-900 mt-2"><?= number_format($summary['total_orders'] ?? 0) ?></h3>
                    <span class="text-xs text-emerald-600 font-semibold mt-1 inline-block">Active Volume</span>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-blue-500"></div>
                    <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Gross Billed Revenue</p>
                    <h3 class="text-3xl font-black text-stone-900 mt-2">₹<?= number_format($summary['total_billed'] ?? 0, 2) ?></h3>
                    <span class="text-xs text-blue-600 font-semibold mt-1 inline-block">Total Invoiced Amount</span>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-600"></div>
                    <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Amount Collected</p>
                    <h3 class="text-3xl font-black text-emerald-600 mt-2">₹<?= number_format($summary['total_received'] ?? 0, 2) ?></h3>
                    <span class="text-xs text-stone-500 font-semibold mt-1 inline-block"><?= number_format($collection_rate, 1) ?>% Collection Rate</span>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-rose-500"></div>
                    <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Total Pending Dues</p>
                    <h3 class="text-3xl font-black text-rose-600 mt-2">₹<?= number_format($summary['total_pending'] ?? 0, 2) ?></h3>
                    <span class="text-xs text-rose-500 font-semibold mt-1 inline-block">Outstanding Credit</span>
                </div>
            </div>

            <!-- Revenue Trend Chart -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-stone-800 text-lg">Monthly Revenue Trends (Veg vs Non-Veg)</h3>
                    <span class="text-xs text-stone-400 font-medium">In INR (₹)</span>
                </div>
                <div class="h-72 w-full">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Monthly Breakdown Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-stone-200/80 overflow-hidden mb-8">
                <div class="p-5 border-b font-bold text-stone-800 bg-stone-50/60 flex justify-between items-center">
                    <span>Month-by-Month Breakdown</span>
                    <span class="text-xs font-normal text-stone-500">Showing data for <?= $selected_year ?></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b bg-stone-100/70 text-stone-500 text-xs uppercase tracking-wider font-semibold">
                                <th class="p-4">Month</th>
                                <th class="p-4">Orders Count</th>
                                <th class="p-4">Veg Revenue</th>
                                <th class="p-4">Non-Veg Revenue</th>
                                <th class="p-4">Total Billed</th>
                                <th class="p-4">Amount Received</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-stone-100">
                            <?php foreach($months as $num => $name): 
                                $row = $indexed_monthly[$num] ?? null;
                                $is_current = ($num == date('n') && $selected_year == date('Y'));
                            ?>
                                <tr class="hover:bg-stone-50/80 transition <?= $is_current ? 'bg-amber-50/40' : '' ?>">
                                    <td class="p-4 font-bold text-stone-900 flex items-center gap-2">
                                        <?= $name ?>
                                        <?php if($is_current): ?>
                                            <span class="bg-amber-100 text-amber-800 text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider font-extrabold">Current</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-stone-600 font-medium"><?= $row ? number_format($row['monthly_orders']) : 0 ?></td>
                                    <td class="p-4 text-emerald-700 font-semibold">₹<?= number_format($row['veg_revenue'] ?? 0, 2) ?></td>
                                    <td class="p-4 text-rose-700 font-semibold">₹<?= number_format($row['nonveg_revenue'] ?? 0, 2) ?></td>
                                    <td class="p-4 font-bold text-stone-900">₹<?= number_format($row['monthly_billed'] ?? 0, 2) ?></td>
                                    <td class="p-4 text-emerald-600 font-semibold">₹<?= number_format($row['monthly_received'] ?? 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Script Initialization -->
    <script>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: 'Veg Revenue (₹)',
                        data: <?= json_encode($chart_veg) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                    },
                    {
                        label: 'Non-Veg Revenue (₹)',
                        data: <?= json_encode($chart_nonveg) ?>,
                        backgroundColor: '#f43f5e',
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'sans-serif', weight: '600', size: 12 },
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'sans-serif', size: 11 } }
                    },
                    y: {
                        grid: { color: '#f3f4f6' },
                        ticks: { font: { family: 'sans-serif', size: 11 } },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>