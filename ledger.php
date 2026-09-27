<?php
// Database Connection
$host = 'localhost';
$db   = 'apna_niwala_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ensure remarks, quantity, and delivery_charge columns exist
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS remarks TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1");
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS delivery_charge DECIMAL(10,2) DEFAULT 0.00");
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Handle Dues Settlement or Full Completion / Remark Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['settle_payment_id'])) {
        $payment_id   = $_POST['settle_payment_id'];
        $pay_amount   = floatval($_POST['additional_received']);
        $new_remarks  = $_POST['remarks'] ?? '';

        // Fetch current record
        $stmt = $pdo->prepare("SELECT total_due, amount_received, meal_amount FROM payments WHERE payment_id = ?");
        $stmt->execute([$payment_id]);
        $record = $stmt->fetch();

        if ($record) {
            $new_received = $record['amount_received'] + $pay_amount;
            $new_due = max(0, $record['total_due'] - $pay_amount);

            $update_stmt = $pdo->prepare("UPDATE payments SET amount_received = ?, total_due = ?, remarks = ? WHERE payment_id = ?");
            $update_stmt->execute([$new_received, $new_due, $new_remarks, $payment_id]);

            echo "<script>alert('Payment & Remarks Updated Successfully!'); window.location.href='ledger.php?client_id=" . intval($_POST['client_id']) . "';</script>";
        }
    } elseif (isset($_POST['edit_payment_id'])) {
        // Edit entire transaction record including quantity & delivery charge
        $payment_id      = $_POST['edit_payment_id'];
        $quantity        = intval($_POST['quantity']);
        $delivery_charge = floatval($_POST['delivery_charge']);
        $meal_amount     = floatval($_POST['meal_amount']); // Total subtotal (should include base price * qty + delivery)
        $amt_received    = floatval($_POST['amount_received']);
        $total_due       = max(0, $meal_amount - $amt_received);
        $payment_mode    = $_POST['payment_mode'];
        $remarks         = $_POST['remarks'];

        $update_stmt = $pdo->prepare("UPDATE payments SET quantity = ?, delivery_charge = ?, meal_amount = ?, amount_received = ?, total_due = ?, payment_mode = ?, remarks = ? WHERE payment_id = ?");
        $update_stmt->execute([$quantity, $delivery_charge, $meal_amount, $amt_received, $total_due, $payment_mode, $remarks, $payment_id]);

        echo "<script>alert('Transaction Updated Successfully!'); window.location.href='ledger.php?client_id=" . intval($_POST['client_id']) . "';</script>";
    }
}

// Fetch all clients and their total pending dues
$search = $_GET['search'] ?? '';
if ($search) {
    $stmt = $pdo->prepare("
        SELECT c.client_id, c.name, c.phone_no, c.address, 
               SUM(p.total_due) as total_pending_due
        FROM clients c
        LEFT JOIN payments p ON c.client_id = p.client_id
        WHERE c.name LIKE ? OR c.phone_no LIKE ?
        GROUP BY c.client_id
        ORDER BY total_pending_due DESC
    ");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("
        SELECT c.client_id, c.name, c.phone_no, c.address, 
               SUM(p.total_due) as total_pending_due
        FROM clients c
        LEFT JOIN payments p ON c.client_id = p.client_id
        GROUP BY c.client_id
        ORDER BY total_pending_due DESC
    ");
}
$clients = $stmt->fetchAll();

// If viewing a specific client's history
$selected_client_id = $_GET['client_id'] ?? null;
$client_history = [];
$client_info = null;

if ($selected_client_id) {
    $client_stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $client_stmt->execute([$selected_client_id]);
    $client_info = $client_stmt->fetch();

    $hist_stmt = $pdo->prepare("SELECT * FROM payments WHERE client_id = ? ORDER BY meal_date DESC");
    $hist_stmt->execute([$selected_client_id]);
    $client_history = $hist_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Ledger & Dues - Apna Niwala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function toggleEdit(id) {
            const viewMode = document.getElementById('view-mode-' + id);
            const editMode = document.getElementById('edit-mode-' + id);
            if (viewMode.classList.contains('hidden')) {
                viewMode.classList.remove('hidden');
                editMode.classList.add('hidden');
            } else {
                viewMode.classList.add('hidden');
                editMode.classList.remove('hidden');
            }
        }
        
        function markAsComplete(id, amount) {
            document.getElementById('received-' + id).value = amount;
        }
    </script>
</head>
<body class="bg-amber-50/60 font-sans text-stone-800">
    <div class="flex h-screen overflow-hidden">
        <?php if(file_exists('sidebar.php')) include 'sidebar.php'; ?>

        <div class="flex-1 p-8 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-extrabold text-stone-900 tracking-tight">Client Ledger & Dues Tracker</h1>
                    <p class="text-stone-500 text-sm mt-1">Manage client records, inspect meal timelines, update quantities, delivery charges, remarks, and settle balances.</p>
                </div>
                
                <!-- Search Box -->
                <form method="GET" class="flex gap-2">
                    <input type="text" name="search" placeholder="Search name or phone..." value="<?= htmlspecialchars($search ?? '') ?>" class="px-3.5 py-2 bg-white border border-stone-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm">
                    <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-emerald-700 transition">Search</button>
                    <?php if($search): ?>
                        <a href="ledger.php" class="bg-stone-200 px-3 py-2 rounded-xl text-stone-700 text-sm font-semibold flex items-center hover:bg-stone-300 transition">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Client List with Dues -->
                <div class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-stone-200/80 p-4 overflow-y-auto max-h-[78vh]">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-stone-500 mb-3 px-1">Customers List</h2>
                    <div class="space-y-2">
                        <?php if(empty($clients)): ?>
                            <p class="text-stone-500 text-sm p-3">No clients found.</p>
                        <?php else: ?>
                            <?php foreach($clients as $c): ?>
                                <a href="ledger.php?client_id=<?= $c['client_id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="block p-3.5 rounded-xl border transition <?= ($selected_client_id == $c['client_id']) ? 'bg-emerald-50/80 border-emerald-500 shadow-sm' : 'hover:bg-stone-50 border-stone-200/80' ?>">
                                    <div class="flex justify-between font-bold text-stone-900 text-sm">
                                        <span><?= htmlspecialchars($c['name']) ?></span>
                                        <span class="<?= ($c['total_pending_due'] > 0) ? 'text-red-600 font-extrabold' : 'text-emerald-600' ?>">
                                            ₹<?= number_format($c['total_pending_due'] ?? 0, 2) ?>
                                        </span>
                                    </div>
                                    <div class="text-xs text-stone-500 mt-1 flex items-center gap-1">📞 <?= htmlspecialchars($c['phone_no']) ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column: Detailed Transaction & Dues History for Selected Client -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-stone-200/80 p-6 overflow-y-auto max-h-[78vh]">
                    <?php if($client_info): ?>
                        <div class="flex justify-between items-start border-b border-stone-100 pb-4 mb-5">
                            <div>
                                <h2 class="text-xl font-extrabold text-stone-900"><?= htmlspecialchars($client_info['name']) ?></h2>
                                <p class="text-xs text-stone-500 mt-1">📞 <?= htmlspecialchars($client_info['phone_no']) ?> &nbsp;|&nbsp; 🏠 <?= htmlspecialchars($client_info['address']) ?></p>
                            </div>
                            <span class="bg-emerald-100 text-emerald-800 text-xs px-3 py-1 rounded-full font-bold">ID #<?= $client_info['client_id'] ?></span>
                        </div>

                        <h3 class="text-xs font-bold uppercase tracking-wider text-stone-500 mb-3">Meal History & Dues Records</h3>
                        <div class="space-y-4">
                            <?php if(empty($client_history)): ?>
                                <p class="text-stone-500 text-sm">No meal history recorded for this client.</p>
                            <?php else: ?>
                                <?php foreach($client_history as $tx): ?>
                                    <div class="border border-stone-200/80 rounded-2xl p-4 bg-stone-50/50 space-y-3">
                                        
                                        <!-- VIEW MODE -->
                                        <div id="view-mode-<?= $tx['payment_id'] ?>">
                                            <div class="flex justify-between items-center font-bold text-stone-900 text-sm">
                                                <div class="flex items-center gap-2">
                                                    <span><?= htmlspecialchars($tx['meal_name']) ?></span>
                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $tx['diet_type'] == 'Veg' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' ?>"><?= $tx['diet_type'] ?></span>
                                                    <span class="text-xs bg-stone-200 text-stone-700 px-2 py-0.5 rounded-md">Qty: <?= $tx['quantity'] ?? 1 ?></span>
                                                </div>
                                                <span class="text-stone-900">₹<?= number_format($tx['meal_amount'], 2) ?></span>
                                            </div>

                                            <div class="grid grid-cols-2 md:grid-cols-4 text-xs text-stone-600 gap-2 mt-2 pt-2 border-t border-stone-200/50">
                                                <div>🗓️ Confirmed: <b class="text-stone-800"><?= $tx['meal_date'] ?></b></div>
                                                <div>⏳ Due Date: <b class="text-amber-700"><?= $tx['due_date'] ?></b></div>
                                                <div>🚚 Delivery: <b class="text-stone-800">₹<?= number_format($tx['delivery_charge'] ?? 0, 2) ?></b></div>
                                                <div>💳 Mode: <b class="text-stone-800"><?= $tx['payment_mode'] ?></b></div>
                                            </div>

                                            <div class="text-xs text-stone-600 mt-1">
                                                Paid Amount: <b class="text-emerald-700">₹<?= number_format($tx['amount_received'], 2) ?></b>
                                            </div>

                                            <?php if(!empty($tx['remarks'])): ?>
                                                <div class="text-xs text-stone-600 bg-amber-50/80 border border-amber-200/60 p-2 rounded-xl mt-2">
                                                    💬 <b>Remarks:</b> <?= htmlspecialchars($tx['remarks']) ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="flex justify-between items-center pt-3 border-t border-stone-200/60 mt-3">
                                                <span class="text-xs font-bold <?= ($tx['total_due'] > 0) ? 'text-red-600' : 'text-emerald-600' ?>">
                                                    Pending Due: ₹<?= number_format($tx['total_due'], 2) ?>
                                                </span>

                                                <div class="flex items-center gap-2">
                                                    <button onclick="toggleEdit(<?= $tx['payment_id'] ?>)" class="text-xs bg-stone-200 hover:bg-stone-300 text-stone-700 px-3 py-1.5 rounded-xl font-bold transition">✏️ Edit / Remarks</button>

                                                    <?php if($tx['total_due'] > 0): ?>
                                                        <form method="POST" class="flex gap-2 items-center">
                                                            <input type="hidden" name="settle_payment_id" value="<?= $tx['payment_id'] ?>">
                                                            <input type="hidden" name="client_id" value="<?= $selected_client_id ?>">
                                                            <input type="hidden" name="remarks" value="<?= htmlspecialchars($tx['remarks'] ?? '') ?>">
                                                            <input type="number" step="0.01" name="additional_received" placeholder="Amount" max="<?= $tx['total_due'] ?>" value="<?= $tx['total_due'] ?>" required class="px-2.5 py-1 text-xs bg-white border border-stone-300 rounded-xl w-20 font-bold">
                                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3 py-1.5 rounded-xl font-bold transition">Clear Due</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-xs bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-xl font-bold">Fully Paid</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- EDIT MODE (Hidden by default) -->
                                        <div id="edit-mode-<?= $tx['payment_id'] ?>" class="hidden space-y-3 pt-1">
                                            <form method="POST" class="space-y-3">
                                                <input type="hidden" name="edit_payment_id" value="<?= $tx['payment_id'] ?>">
                                                <input type="hidden" name="client_id" value="<?= $selected_client_id ?>">
                                                
                                                <div class="text-xs font-extrabold text-stone-700 uppercase">Editing Transaction #<?= $tx['payment_id'] ?></div>
                                                
                                                <div class="grid grid-cols-3 gap-2">
                                                    <div>
                                                        <label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">Quantity</label>
                                                        <input type="number" min="1" name="quantity" value="<?= $tx['quantity'] ?? 1 ?>" class="w-full px-2.5 py-1.5 bg-white border border-stone-300 rounded-xl text-xs font-bold">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">Delivery Charge (₹)</label>
                                                        <input type="number" step="0.01" min="0" name="delivery_charge" value="<?= $tx['delivery_charge'] ?? 0 ?>" class="w-full px-2.5 py-1.5 bg-white border border-stone-300 rounded-xl text-xs font-bold">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">Total Price (₹)</label>
                                                        <input type="number" step="0.01" name="meal_amount" value="<?= $tx['meal_amount'] ?>" class="w-full px-2.5 py-1.5 bg-white border border-stone-300 rounded-xl text-xs font-bold">
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">Amount Received (₹)</label>
                                                        <input type="number" step="0.01" id="received-<?= $tx['payment_id'] ?>" name="amount_received" value="<?= $tx['amount_received'] ?>" class="w-full px-2.5 py-1.5 bg-white border border-stone-300 rounded-xl text-xs font-bold text-emerald-700">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">Payment Mode</label>
                                                        <select name="payment_mode" class="w-full px-2.5 py-1.5 bg-white border border-stone-300 rounded-xl text-xs font-semibold">
                                                            <option value="Cash" <?= $tx['payment_mode']=='Cash'?'selected':'' ?>>Cash</option>
                                                            <option value="UPI" <?= $tx['payment_mode']=='UPI'?'selected':'' ?>>UPI</option>
                                                            <option value="Bank Transfer" <?= $tx['payment_mode']=='Bank Transfer'?'selected':'' ?>>Bank Transfer</option>
                                                            <option value="Credit" <?= $tx['payment_mode']=='Credit'?'selected':'' ?>>Credit</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-[10px] uppercase font-bold text-stone-500 mb-1">Remarks / Instructions</label>
                                                    <textarea name="remarks" rows="2" class="w-full px-2.5 py-1.5 bg-white border border-stone-300 rounded-xl text-xs font-medium"><?= htmlspecialchars($tx['remarks'] ?? '') ?></textarea>
                                                </div>

                                                <div class="flex justify-end gap-2 pt-1">
                                                    <button type="button" onclick="toggleEdit(<?= $tx['payment_id'] ?>)" class="bg-stone-200 hover:bg-stone-300 text-stone-700 text-xs px-3 py-1.5 rounded-xl font-bold transition">Cancel</button>
                                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3 py-1.5 rounded-xl font-bold transition">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>

                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-64 text-stone-400">
                            <span class="text-4xl mb-2">👈</span>
                            <p class="text-sm font-medium">Select a customer from the left list to view ledger details, edit entries, and manage dues.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>