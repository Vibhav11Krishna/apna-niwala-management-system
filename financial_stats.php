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

// Filters: Year, View Mode (Monthly vs Weekly)
$selected_year = $_GET['year'] ?? date('Y');
$view_mode = $_GET['view'] ?? 'monthly'; // 'monthly' or 'weekly'

// Handle New Expense Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $expense_date = $_POST['expense_date'];
    $category = $_POST['category'];
    $item_name = trim($_POST['item_name']);
    $amount = floatval($_POST['amount']);
    $notes = trim($_POST['notes']);

    if (!empty($expense_date) && !empty($category) && !empty($item_name) && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO expenses (expense_date, category, item_name, amount, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$expense_date, $category, $item_name, $amount, $notes]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?year=" . $selected_year . "&view=" . $view_mode);
        exit;
    }
}

// Fetch Yearly Revenue Summary Totals
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

// Fetch Yearly Expense Summary Totals & Categories
$exp_summary_stmt = $pdo->prepare("
    SELECT 
        SUM(amount) as total_expenses,
        SUM(CASE WHEN category = 'Vegetables' THEN amount ELSE 0 END) as total_veg_exp,
        SUM(CASE WHEN category = 'Grocery' THEN amount ELSE 0 END) as total_grocery_exp,
        SUM(CASE WHEN category = 'Essentials' THEN amount ELSE 0 END) as total_essentials_exp,
        SUM(CASE WHEN category = 'Other' THEN amount ELSE 0 END) as total_other_exp
    FROM expenses 
    WHERE YEAR(expense_date) = ?
");
$exp_summary_stmt->execute([$selected_year]);
$exp_summary = $exp_summary_stmt->fetch();

$total_revenue = $summary['total_received'] ?? 0;
$total_expenses = $exp_summary['total_expenses'] ?? 0;
$net_profit = $total_revenue - $total_expenses;

// Fetch Monthly Breakdown for Revenue & Orders
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

// Fetch Monthly Breakdown for Expenses by Category
$monthly_exp_stmt = $pdo->prepare("
    SELECT 
        MONTH(expense_date) as month_num,
        SUM(amount) as monthly_total,
        SUM(CASE WHEN category = 'Vegetables' THEN amount ELSE 0 END) as veg_exp,
        SUM(CASE WHEN category = 'Grocery' THEN amount ELSE 0 END) as grocery_exp,
        SUM(CASE WHEN category = 'Essentials' THEN amount ELSE 0 END) as essentials_exp,
        SUM(CASE WHEN category = 'Other' THEN amount ELSE 0 END) as other_exp
    FROM expenses
    WHERE YEAR(expense_date) = ?
    GROUP BY MONTH(expense_date)
    ORDER BY month_num ASC
");
$monthly_exp_stmt->execute([$selected_year]);
$monthly_exp_data = $monthly_exp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Weekly Breakdown for Expenses for Selected Year
$weekly_exp_stmt = $pdo->prepare("
    SELECT 
        YEARWEEK(expense_date, 1) as year_week,
        MIN(expense_date) as week_start,
        MAX(expense_date) as week_end,
        SUM(CASE WHEN category = 'Vegetables' THEN amount ELSE 0 END) as veg_exp,
        SUM(CASE WHEN category = 'Grocery' THEN amount ELSE 0 END) as grocery_exp,
        SUM(CASE WHEN category = 'Essentials' THEN amount ELSE 0 END) as essentials_exp,
        SUM(CASE WHEN category = 'Other' THEN amount ELSE 0 END) as other_exp,
        SUM(amount) as weekly_total
    FROM expenses
    WHERE YEAR(expense_date) = ?
    GROUP BY YEARWEEK(expense_date, 1)
    ORDER BY year_week DESC
");
$weekly_exp_stmt->execute([$selected_year]);
$weekly_exp_data = $weekly_exp_stmt->fetchAll(PDO::FETCH_ASSOC);

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$indexed_monthly = [];
foreach($monthly_data as $m) { $indexed_monthly[$m['month_num']] = $m; }

$indexed_monthly_exp = [];
foreach($monthly_exp_data as $e) { $indexed_monthly_exp[$e['month_num']] = $e; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial & Expense Analytics - Apna Niwala</title>
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
                    <h1 class="text-3xl font-extrabold text-stone-900 tracking-tight">Mess P&L & Financial Stats</h1>
                    <p class="text-sm text-stone-500 mt-1">Track yearly, monthly, and weekly revenue, expenses, and net profit.</p>
                </div>
                
                <div class="flex items-center gap-3 flex-wrap">
                    <!-- Add Expense Button Trigger -->
                    <button onclick="document.getElementById('expenseModal').classList.remove('hidden')" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition flex items-center gap-2 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Expense
                    </button>

                    <!-- Year Selector Form -->
                    <form method="GET" class="flex items-center bg-stone-50 p-1.5 rounded-xl border">
                        <input type="hidden" name="view" value="<?= $view_mode ?>">
                        <select name="year" onchange="this.form.submit()" class="bg-transparent px-3 py-1 text-sm font-bold text-stone-800 focus:outline-none cursor-pointer">
                            <?php for($y = 2025; $y <= date('Y'); $y++): ?>
                                <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Profit & Loss Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-emerald-600"></div>
                    <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Total Cash Collected (<?= $selected_year ?>)</p>
                    <h3 class="text-3xl font-black text-emerald-600 mt-2">₹<?= number_format($total_revenue, 2) ?></h3>
                    <span class="text-xs text-stone-500 font-semibold mt-1 inline-block">Billed: ₹<?= number_format($summary['total_billed'] ?? 0, 2) ?></span>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-rose-500"></div>
                    <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Total Expenses (<?= $selected_year ?>)</p>
                    <h3 class="text-3xl font-black text-rose-600 mt-2">₹<?= number_format($total_expenses, 2) ?></h3>
                    <span class="text-xs text-stone-500 font-semibold mt-1 inline-block">Veg, Grocery & Essentials</span>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-blue-600"></div>
                    <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Net Profit / (Loss)</p>
                    <h3 class="text-3xl font-black <?= $net_profit >= 0 ? 'text-blue-600' : 'text-rose-600' ?> mt-2">₹<?= number_format($net_profit, 2) ?></h3>
                    <span class="text-xs text-stone-500 font-semibold mt-1 inline-block">Collected Revenue - Expenses</span>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-amber-500"></div>
                    <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Pending Dues</p>
                    <h3 class="text-3xl font-black text-amber-600 mt-2">₹<?= number_format($summary['total_pending'] ?? 0, 2) ?></h3>
                    <span class="text-xs text-stone-500 font-semibold mt-1 inline-block">Uncollected customer balance</span>
                </div>
            </div>

            <!-- Expense Category Breakdown Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-stone-200/80 flex items-center justify-between">
                    <div>
                        <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Vegetables Spend</p>
                        <h4 class="text-xl font-extrabold text-stone-900 mt-1">₹<?= number_format($exp_summary['total_veg_exp'] ?? 0, 2) ?></h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 font-bold">🥦</div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-stone-200/80 flex items-center justify-between">
                    <div>
                        <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Grocery & Staples</p>
                        <h4 class="text-xl font-extrabold text-stone-900 mt-1">₹<?= number_format($exp_summary['total_grocery_exp'] ?? 0, 2) ?></h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 font-bold">🌾</div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-stone-200/80 flex items-center justify-between">
                    <div>
                        <p class="text-stone-400 text-xs font-bold uppercase tracking-wider">Essentials & Others</p>
                        <h4 class="text-xl font-extrabold text-stone-900 mt-1">₹<?= number_format(($exp_summary['total_essentials_exp'] ?? 0) + ($exp_summary['total_other_exp'] ?? 0), 2) ?></h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold">⚡</div>
                </div>
            </div>

            <!-- Expense Breakdown Chart -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-stone-800 text-lg">Expense Category Breakdown (Monthly Trend)</h3>
                    <span class="text-xs text-stone-400 font-medium">In INR (₹)</span>
                </div>
                <div class="h-72 w-full">
                    <canvas id="expenseCategoryChart"></canvas>
                </div>
            </div>

            <!-- View Switcher Tabs (Monthly vs Weekly) -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex gap-2 bg-white p-1 rounded-xl border border-stone-200 shadow-sm">
                    <a href="?year=<?= $selected_year ?>&view=monthly" class="px-4 py-2 rounded-lg text-xs font-bold transition <?= $view_mode == 'monthly' ? 'bg-amber-600 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-50' ?>">Monthly View</a>
                    <a href="?year=<?= $selected_year ?>&view=weekly" class="px-4 py-2 rounded-lg text-xs font-bold transition <?= $view_mode == 'weekly' ? 'bg-amber-600 text-white shadow-sm' : 'text-stone-600 hover:bg-stone-50' ?>">Weekly View</a>
                </div>
                <span class="text-xs text-stone-500 font-medium">Showing <?= ucfirst($view_mode) ?> Breakdown for <?= $selected_year ?></span>
            </div>

            <?php if($view_mode == 'monthly'): ?>
                <!-- Monthly Ledger Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-stone-200/80 overflow-hidden mb-8">
                    <div class="p-5 border-b font-bold text-stone-800 bg-stone-50/60">
                        Month-by-Month Profit, Revenue & Expense Ledger
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b bg-stone-100/70 text-stone-500 text-xs uppercase tracking-wider font-semibold">
                                    <th class="p-4">Month</th>
                                    <th class="p-4">Revenue Received</th>
                                    <th class="p-4">Vegetables</th>
                                    <th class="p-4">Grocery</th>
                                    <th class="p-4">Essentials/Other</th>
                                    <th class="p-4">Total Expenses</th>
                                    <th class="p-4">Net P&L</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-stone-100">
                                <?php foreach($months as $num => $name): 
                                    $rev = $indexed_monthly[$num] ?? null;
                                    $exp = $indexed_monthly_exp[$num] ?? null;
                                    
                                    $m_received = $rev['monthly_received'] ?? 0;
                                    $m_veg = $exp['veg_exp'] ?? 0;
                                    $m_gro = $exp['grocery_exp'] ?? 0;
                                    $m_ess = ($exp['essentials_exp'] ?? 0) + ($exp['other_exp'] ?? 0);
                                    $m_tot_exp = $exp['monthly_total'] ?? 0;
                                    $m_net = $m_received - $m_tot_exp;
                                    $is_current = ($num == date('n') && $selected_year == date('Y'));
                                ?>
                                    <tr class="hover:bg-stone-50/80 transition <?= $is_current ? 'bg-amber-50/40' : '' ?>">
                                        <td class="p-4 font-bold text-stone-900"><?= $name ?></td>
                                        <td class="p-4 text-emerald-600 font-semibold">₹<?= number_format($m_received, 2) ?></td>
                                        <td class="p-4 text-stone-600">₹<?= number_format($m_veg, 2) ?></td>
                                        <td class="p-4 text-stone-600">₹<?= number_format($m_gro, 2) ?></td>
                                        <td class="p-4 text-stone-600">₹<?= number_format($m_ess, 2) ?></td>
                                        <td class="p-4 text-rose-600 font-semibold">₹<?= number_format($m_tot_exp, 2) ?></td>
                                        <td class="p-4 font-extrabold <?= $m_net >= 0 ? 'text-blue-600' : 'text-rose-600' ?>">₹<?= number_format($m_net, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <!-- Weekly Ledger Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-stone-200/80 overflow-hidden mb-8">
                    <div class="p-5 border-b font-bold text-stone-800 bg-stone-50/60">
                        Week-by-Week Expense Breakdown
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b bg-stone-100/70 text-stone-500 text-xs uppercase tracking-wider font-semibold">
                                    <th class="p-4">Week Range</th>
                                    <th class="p-4">Vegetables</th>
                                    <th class="p-4">Grocery</th>
                                    <th class="p-4">Essentials/Other</th>
                                    <th class="p-4">Total Weekly Expense</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-stone-100">
                                <?php if(empty($weekly_exp_data)): ?>
                                    <tr><td colspan="5" class="p-6 text-center text-stone-400">No expense records found for <?= $selected_year ?>.</td></tr>
                                <?php else: foreach($weekly_exp_data as $w): ?>
                                    <tr class="hover:bg-stone-50/80 transition">
                                        <td class="p-4 font-bold text-stone-900"><?= date('d M', strtotime($w['week_start'])) ?> - <?= date('d M Y', strtotime($w['week_end'])) ?></td>
                                        <td class="p-4 text-stone-600">₹<?= number_format($w['veg_exp'], 2) ?></td>
                                        <td class="p-4 text-stone-600">₹<?= number_format($w['grocery_exp'], 2) ?></td>
                                        <td class="p-4 text-stone-600">₹<?= number_format($w['essentials_exp'] + $w['other_exp'], 2) ?></td>
                                        <td class="p-4 text-rose-600 font-bold">₹<?= number_format($w['weekly_total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="expenseModal" class="fixed inset-0 bg-stone-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-stone-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-stone-900">Record New Expense</h3>
                <button onclick="document.getElementById('expenseModal').classList.add('hidden')" class="text-stone-400 hover:text-stone-600 font-bold">&times;</button>
            </div>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Expense Date</label>
                    <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full p-2.5 rounded-xl border border-stone-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Category</label>
                    <select name="category" required class="w-full p-2.5 rounded-xl border border-stone-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="Vegetables">Vegetables (Sabzi, Fresh Produce)</option>
                        <option value="Grocery">Grocery (Rice, Dal, Flour, Spices)</option>
                        <option value="Essentials">Essentials (Gas Cylinder, Electricity, Packaging)</option>
                        <option value="Other">Other Operational Expenses</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Item / Vendor Name</label>
                    <input type="text" name="item_name" placeholder="e.g., Weekly Vegetable Market / 50kg Rice" required class="w-full p-2.5 rounded-xl border border-stone-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Amount (₹)</label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" required class="w-full p-2.5 rounded-xl border border-stone-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 uppercase mb-1">Notes (Optional)</label>
                    <textarea name="notes" placeholder="Additional details..." rows="2" class="w-full p-2.5 rounded-xl border border-stone-200 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('expenseModal').classList.add('hidden')" class="px-4 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 text-sm font-semibold rounded-xl transition">Cancel</button>
                    <button type="submit" name="add_expense" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl transition shadow-sm">Save Expense</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Chart Script Initialization -->
    <script>
        <?php
        $chart_labels = [];
        $chart_veg = [];
        $chart_grocery = [];
        $chart_essentials = [];
        foreach($months as $num => $name) {
            $chart_labels[] = substr($name, 0, 3);
            $exp_row = $indexed_monthly_exp[$num] ?? null;
            $chart_veg[] = $exp_row['veg_exp'] ?? 0;
            $chart_grocery[] = $exp_row['grocery_exp'] ?? 0;
            $chart_essentials[] = ($exp_row['essentials_exp'] ?? 0) + ($exp_row['other_exp'] ?? 0);
        }
        ?>
        const ctx = document.getElementById('expenseCategoryChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        label: 'Vegetables (₹)',
                        data: <?= json_encode($chart_veg) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4,
                    },
                    {
                        label: 'Grocery (₹)',
                        data: <?= json_encode($chart_grocery) ?>,
                        backgroundColor: '#f59e0b',
                        borderRadius: 4,
                    },
                    {
                        label: 'Essentials & Other (₹)',
                        data: <?= json_encode($chart_essentials) ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4,
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
                        stacked: true,
                        grid: { display: false },
                        ticks: { font: { family: 'sans-serif', size: 11 } }
                    },
                    y: {
                        stacked: true,
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