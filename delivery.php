<?php
$host = 'localhost';
$db   = 'apna_niwala_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Auto-create delivery_tasks table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_tasks (
        task_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        delivery_boy_id INT(11) NOT NULL,
        client_id INT(11) NOT NULL,
        meal_name VARCHAR(150) NOT NULL,
        delivery_charge DECIMAL(10,2) NOT NULL,
        status ENUM('Pending', 'Received', 'Not Received') DEFAULT 'Pending',
        task_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Handle Form POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_delivery_person'])) {
        $name        = trim($_POST['name']);
        $phone_no    = trim($_POST['phone_no']);
        $address     = trim($_POST['address']);
        $salary_type = $_POST['salary_type'];
        $payout      = floatval($_POST['payout_amount']);

        $stmt = $pdo->prepare("INSERT INTO delivery_persons (name, phone_no, address, salary_type, payout_amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone_no, $address, $salary_type, $payout]);
        
        echo "<script>alert('Delivery Boy Profile Created Successfully!'); window.location.href='delivery.php';</script>";
        exit;
    } 
    elseif (isset($_POST['update_delivery_person'])) {
        $delivery_boy_id = intval($_POST['delivery_boy_id']);
        $name            = trim($_POST['name']);
        $phone_no        = trim($_POST['phone_no']);
        $address         = trim($_POST['address']);
        $salary_type     = $_POST['salary_type'];
        $payout          = floatval($_POST['payout_amount']);

        $stmt = $pdo->prepare("UPDATE delivery_persons SET name = ?, phone_no = ?, address = ?, salary_type = ?, payout_amount = ? WHERE delivery_boy_id = ?");
        $stmt->execute([$name, $phone_no, $address, $salary_type, $payout, $delivery_boy_id]);

        echo "<script>alert('Profile Updated Successfully!'); window.location.href='delivery.php?profile_id=" . $delivery_boy_id . "';</script>";
        exit;
    }
    elseif (isset($_POST['delete_delivery_person'])) {
        $delivery_boy_id = intval($_POST['delivery_boy_id']);
        $pdo->prepare("UPDATE clients SET delivery_boy_id = NULL WHERE delivery_boy_id = ?")->execute([$delivery_boy_id]);
        $pdo->prepare("DELETE FROM delivery_persons WHERE delivery_boy_id = ?")->execute([$delivery_boy_id]);

        echo "<script>alert('Delivery Boy Deleted Successfully!'); window.location.href='delivery.php';</script>";
        exit;
    }
    elseif (isset($_POST['assign_client'])) {
        $delivery_boy_id = intval($_POST['delivery_boy_id']);
        $client_id       = intval($_POST['client_id']);

        $stmt = $pdo->prepare("UPDATE clients SET delivery_boy_id = ? WHERE client_id = ?");
        $stmt->execute([$delivery_boy_id, $client_id]);

        echo "<script>alert('Client Assigned Successfully!'); window.location.href='delivery.php?profile_id=" . $delivery_boy_id . "';</script>";
        exit;
    }
    elseif (isset($_POST['remove_client'])) {
        $client_id       = intval($_POST['client_id']);
        $delivery_boy_id = intval($_POST['delivery_boy_id']);
        
        $stmt = $pdo->prepare("UPDATE clients SET delivery_boy_id = NULL WHERE client_id = ?");
        $stmt->execute([$client_id]);

        echo "<script>alert('Client Unassigned Successfully!'); window.location.href='delivery.php?profile_id=" . $delivery_boy_id . "';</script>";
        exit;
    }
    elseif (isset($_POST['add_task'])) {
        $delivery_boy_id = intval($_POST['delivery_boy_id']);
        $client_id       = intval($_POST['client_id']);
        $meal_name       = trim($_POST['meal_name']);
        $delivery_charge = floatval($_POST['delivery_charge']);
        $task_date       = $_POST['task_date'];

        $stmt = $pdo->prepare("INSERT INTO delivery_tasks (delivery_boy_id, client_id, meal_name, delivery_charge, task_date, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$delivery_boy_id, $client_id, $meal_name, $delivery_charge, $task_date]);

        echo "<script>alert('Delivery Task Added Successfully!'); window.location.href='delivery.php?profile_id=" . $delivery_boy_id . "';</script>";
        exit;
    }
    elseif (isset($_POST['update_task_status'])) {
        $task_id   = intval($_POST['task_id']);
        $status    = $_POST['status'];
        $profile_id = intval($_POST['profile_id']);

        $stmt = $pdo->prepare("UPDATE delivery_tasks SET status = ? WHERE task_id = ?");
        $stmt->execute([$status, $task_id]);

        echo "<script>window.location.href='delivery.php?profile_id=" . $profile_id . "';</script>";
        exit;
    }
}

// Fetch all delivery agents
$delivery_persons = $pdo->query("SELECT * FROM delivery_persons ORDER BY delivery_boy_id DESC")->fetchAll();

// Fetch all clients from database safely for assignment dropdown
try {
    $all_clients = $pdo->query("SELECT client_id, name, phone_no, address, COALESCE(delivery_charge, 50) as delivery_charge FROM clients ORDER BY name ASC")->fetchAll();
} catch (\PDOException $e) {
    // Fallback if delivery_charge column hasn't been added to the clients table yet
    $all_clients = $pdo->query("SELECT client_id, name, phone_no, address, 50 as delivery_charge FROM clients ORDER BY name ASC")->fetchAll();
}
// Active Profile View
$profile_id = $_GET['profile_id'] ?? null;
$profile_agent = null;
$assigned_clients = [];
$delivery_tasks = [];

if ($profile_id) {
    $agent_stmt = $pdo->prepare("SELECT * FROM delivery_persons WHERE delivery_boy_id = ?");
    $agent_stmt->execute([$profile_id]);
    $profile_agent = $agent_stmt->fetch();

    if ($profile_agent) {
        try {
            $clients_stmt = $pdo->prepare("SELECT client_id, name, phone_no, address, COALESCE(delivery_charge, 50) as delivery_charge FROM clients WHERE delivery_boy_id = ?");
            $clients_stmt->execute([$profile_id]);
            $assigned_clients = $clients_stmt->fetchAll();
        } catch (\PDOException $e) {
            $clients_stmt = $pdo->prepare("SELECT client_id, name, phone_no, address, 50 as delivery_charge FROM clients WHERE delivery_boy_id = ?");
            $clients_stmt->execute([$profile_id]);
            $assigned_clients = $clients_stmt->fetchAll();
        }

        // Fetch tasks for this agent
        $tasks_stmt = $pdo->prepare("SELECT dt.*, c.name as client_name, c.address FROM delivery_tasks dt JOIN clients c ON dt.client_id = c.client_id WHERE dt.delivery_boy_id = ? ORDER BY dt.task_date DESC, dt.task_id DESC");
        $tasks_stmt->execute([$profile_id]);
        $delivery_tasks = $tasks_stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Apna Niwala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function toggleModal(id) { document.getElementById(id).classList.toggle('hidden'); }
        
        function openTaskModal(clientId, clientName, defaultCharge) {
            document.getElementById('task_client_id').value = clientId;
            document.getElementById('task_client_name').innerText = clientName;
            document.getElementById('task_delivery_charge').value = defaultCharge;
            document.getElementById('task_item_name').value = '';
            document.getElementById('task_date').value = new Date().toISOString().split('T')[0];
            toggleModal('taskModal');
        }

        function openEditProfileModal() {
            toggleModal('editProfileModal');
        }
    </script>
</head>
<body class="bg-stone-50 font-sans text-stone-800">
    <div class="flex h-screen overflow-hidden">
        <?php if(file_exists('sidebar.php')) include 'sidebar.php'; ?>

        <div class="flex-1 p-8 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-black text-stone-900 tracking-tight">Delivery Management & Task Tracking</h1>
                    <p class="text-stone-500 text-xs mt-0.5">Manage delivery boys (Daily/Weekly/Monthly payout profiles), assign clients with place-based delivery charges, and track statuses.</p>
                </div>
                <button onclick="toggleModal('addDeliveryModal')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold shadow-sm transition">
                    ➕ Add Delivery Boy
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column: Delivery Boys Directory -->
                <div class="lg:col-span-1 space-y-3">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-stone-400 px-1">Delivery Personnel</h2>
                    <?php if(empty($delivery_persons)): ?>
                        <div class="bg-white rounded-2xl p-6 text-center border border-stone-200 text-stone-400 text-xs">No delivery boys found. Add one to begin.</div>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach($delivery_persons as $dp): ?>
                                <a href="delivery.php?profile_id=<?= $dp['delivery_boy_id'] ?>" class="block bg-white border rounded-xl p-4 transition shadow-sm <?= ($profile_id == $dp['delivery_boy_id']) ? 'border-emerald-500 bg-emerald-50/50 ring-2 ring-emerald-500/20' : 'border-stone-200 hover:bg-stone-50' ?>">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-bold text-stone-900 text-sm"><?= htmlspecialchars($dp['name']) ?></h3>
                                            <p class="text-xs text-stone-500">📞 <?= htmlspecialchars($dp['phone_no']) ?></p>
                                        </div>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                                            <?= $dp['salary_type'] ?> (₹<?= number_format($dp['payout_amount'], 2) ?>)
                                        </span>
                                    </div>
                                    <div class="mt-2 text-xs text-stone-500 truncate">
                                        📍 Address/Origin: <?= htmlspecialchars($dp['address']) ?>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-stone-100 flex justify-between text-xs font-semibold text-stone-600">
                                        <span>Open Profile</span>
                                        <span class="text-emerald-600 font-bold">➔</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Profile & Assigned Database Clients -->
                <div class="lg:col-span-2 space-y-5">
                    <?php if($profile_agent): ?>
                        <!-- Agent Header Profile -->
                        <div class="bg-white rounded-2xl shadow-sm border border-stone-200 p-6 space-y-4">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-lg font-black text-stone-900"><?= htmlspecialchars($profile_agent['name']) ?></h2>
                                        <span class="bg-stone-900 text-white text-[10px] font-bold px-2.5 py-1 rounded-full">ID #<?= $profile_agent['delivery_boy_id'] ?></span>
                                    </div>
                                    <p class="text-xs text-stone-500 mt-1">📞 <?= htmlspecialchars($profile_agent['phone_no']) ?> | 📍 <?= htmlspecialchars($profile_agent['address']) ?></p>
                                    <p class="text-xs text-emerald-700 font-bold mt-0.5">Payout Structure: <?= $profile_agent['salary_type'] ?> — ₹<?= number_format($profile_agent['payout_amount'], 2) ?></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="openEditProfileModal()" class="bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs px-3 py-2 rounded-xl font-bold transition">
                                        ✏️ Edit Profile
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete this delivery profile?');">
                                        <input type="hidden" name="delete_delivery_person" value="1">
                                        <input type="hidden" name="delivery_boy_id" value="<?= $profile_agent['delivery_boy_id'] ?>">
                                        <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 text-xs px-3 py-2 rounded-xl font-bold transition">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <?php 
                                // Calculate earnings from tasks marked as Received
                                $total_earned = 0;
                                foreach($delivery_tasks as $dt) {
                                    if($dt['status'] === 'Received') {
                                        $total_earned += floatval($dt['delivery_charge']);
                                    }
                                }
                            ?>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex justify-between items-center">
                                <div>
                                    <div class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Calculated Task Earnings (From Received Deliveries)</div>
                                    <div class="text-xs text-emerald-600">Base Payout Type: <?= $profile_agent['salary_type'] ?></div>
                                </div>
                                <div class="text-xl font-black text-emerald-700">₹<?= number_format($total_earned, 2) ?></div>
                            </div>
                        </div>

                        <!-- Assign Client From Database -->
                        <div class="bg-white rounded-2xl shadow-sm border border-stone-200 p-5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-stone-400 mb-3">Assign Client from Database</h3>
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="assign_client" value="1">
                                <input type="hidden" name="delivery_boy_id" value="<?= $profile_agent['delivery_boy_id'] ?>">
                                
                                <select name="client_id" required class="flex-1 px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                    <option value="">-- Select client from database --</option>
                                    <?php foreach($all_clients as $c): ?>
                                        <option value="<?= $c['client_id'] ?>"><?= htmlspecialchars($c['name']) ?> (Charge: ₹<?= $c['delivery_charge'] ?>) — <?= htmlspecialchars($c['address']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-4 py-2 rounded-xl font-bold shadow-sm">
                                    Assign Client
                                </button>
                            </form>
                        </div>

                        <!-- Assigned Clients List & Task Action Trigger -->
                        <div class="bg-white rounded-2xl shadow-sm border border-stone-200 p-6 space-y-4">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-stone-400">Assigned Clients & Task Creation</h3>

                            <?php if(empty($assigned_clients)): ?>
                                <div class="text-center py-6 text-stone-400 bg-stone-50 rounded-xl border border-dashed border-stone-200 text-xs">
                                    No clients assigned to this agent yet.
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach($assigned_clients as $ac): ?>
                                        <div class="border border-stone-200 bg-stone-50/50 rounded-xl p-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                            <div>
                                                <div class="font-bold text-stone-900 text-sm"><?= htmlspecialchars($ac['name']) ?> <span class="text-emerald-700 font-semibold">(₹<?= $ac['delivery_charge'] ?> Delivery Charge)</span></div>
                                                <div class="text-xs text-stone-500 mt-0.5">📞 <?= htmlspecialchars($ac['phone_no']) ?> | 📍 <?= htmlspecialchars($ac['address']) ?></div>
                                            </div>
                                            <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                                                <button onclick="openTaskModal(<?= $ac['client_id'] ?>, '<?= htmlspecialchars($ac['name'], ENT_QUOTES) ?>', <?= $ac['delivery_charge'] ?>)" class="bg-stone-900 hover:bg-stone-800 text-white text-xs px-3 py-2 rounded-xl font-bold shadow-sm">
                                                    📦 Add Delivery Task
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Unassign client?');">
                                                    <input type="hidden" name="remove_client" value="1">
                                                    <input type="hidden" name="client_id" value="<?= $ac['client_id'] ?>">
                                                    <input type="hidden" name="delivery_boy_id" value="<?= $profile_agent['delivery_boy_id'] ?>">
                                                    <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 text-xs px-2.5 py-2 rounded-xl font-bold">✕</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Delivery Tasks & Status Table -->
                        <div class="bg-white rounded-2xl shadow-sm border border-stone-200 p-6 space-y-4">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-stone-400">Assigned Delivery Tasks & Status</h3>

                            <?php if(empty($delivery_tasks)): ?>
                                <div class="text-center py-6 text-stone-400 bg-stone-50 rounded-xl border border-dashed border-stone-200 text-xs">
                                    No delivery tasks added yet.
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach($delivery_tasks as $dt): 
                                        $statusClass = 'bg-amber-100 text-amber-800';
                                        if($dt['status'] === 'Received') $statusClass = 'bg-emerald-600 text-white';
                                        if($dt['status'] === 'Not Received') $statusClass = 'bg-rose-600 text-white';
                                    ?>
                                        <div class="border border-stone-200 rounded-xl p-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 <?= $dt['status'] === 'Received' ? 'bg-emerald-50/40 border-emerald-300' : ($dt['status'] === 'Not Received' ? 'bg-rose-50/40 border-rose-300' : 'bg-stone-50') ?>">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-extrabold text-stone-900 text-sm">📦 <?= htmlspecialchars($dt['meal_name']) ?></span>
                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $statusClass ?>"><?= $dt['status'] ?></span>
                                                </div>
                                                <div class="text-xs text-stone-600 mt-1">
                                                    Client: <b><?= htmlspecialchars($dt['client_name']) ?></b> | Place: <?= htmlspecialchars($dt['address']) ?> | Charge: ₹<?= $dt['delivery_charge'] ?> | Date: <?= $dt['task_date'] ?>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                                                <form method="POST">
                                                    <input type="hidden" name="update_task_status" value="1">
                                                    <input type="hidden" name="task_id" value="<?= $dt['task_id'] ?>">
                                                    <input type="hidden" name="profile_id" value="<?= $profile_agent['delivery_boy_id'] ?>">
                                                    <input type="hidden" name="status" value="Received">
                                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3 py-1.5 rounded-xl font-bold shadow-sm">✓ Received</button>
                                                </form>
                                                <form method="POST">
                                                    <input type="hidden" name="update_task_status" value="1">
                                                    <input type="hidden" name="task_id" value="<?= $dt['task_id'] ?>">
                                                    <input type="hidden" name="profile_id" value="<?= $profile_agent['delivery_boy_id'] ?>">
                                                    <input type="hidden" name="status" value="Not Received">
                                                    <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white text-xs px-3 py-1.5 rounded-xl font-bold shadow-sm">✕ Not Received</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-stone-200 p-12 text-center text-stone-400 flex flex-col items-center justify-center h-96">
                            <p class="text-xs font-semibold">Select a delivery boy from the left directory to view profile and task management.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ADD DELIVERY BOY MODAL -->
    <div id="addDeliveryModal" class="hidden fixed inset-0 bg-stone-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-stone-900 text-base">Add Delivery Boy Profile</h3>
                <button onclick="toggleModal('addDeliveryModal')" class="text-stone-400 hover:text-stone-700 font-bold">✕</button>
            </div>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="add_delivery_person" value="1">
                <div>
                    <label class="block text-xs font-bold text-stone-600 mb-1">Full Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 mb-1">Phone Number</label>
                    <input type="text" name="phone_no" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 mb-1">Address / Where he comes from</label>
                    <input type="text" name="address" placeholder="e.g., Kankarbagh, Patna" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-stone-600 mb-1">Payment Structure Type</label>
                        <select name="salary_type" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                            <option value="Daily">Daily</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Monthly" selected>Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-stone-600 mb-1">Base Payout Amount (₹)</label>
                        <input type="number" step="0.01" name="payout_amount" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-bold text-emerald-700">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="toggleModal('addDeliveryModal')" class="bg-stone-200 text-stone-700 px-4 py-2 rounded-xl text-xs font-bold">Cancel</button>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold">Save Profile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT PROFILE MODAL -->
    <?php if($profile_agent): ?>
    <div id="editProfileModal" class="hidden fixed inset-0 bg-stone-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-stone-900 text-base">Edit Delivery Profile</h3>
                <button onclick="toggleModal('editProfileModal')" class="text-stone-400 hover:text-stone-700 font-bold">✕</button>
            </div>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="update_delivery_person" value="1">
                <input type="hidden" name="delivery_boy_id" value="<?= $profile_agent['delivery_boy_id'] ?>">
                <div>
                    <label class="block text-xs font-bold text-stone-600 mb-1">Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($profile_agent['name']) ?>" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 mb-1">Phone Number</label>
                    <input type="text" name="phone_no" value="<?= htmlspecialchars($profile_agent['phone_no']) ?>" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-stone-600 mb-1">Address / Where he comes from</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($profile_agent['address']) ?>" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-stone-600 mb-1">Payment Structure Type</label>
                        <select name="salary_type" class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                            <option value="Daily" <?= $profile_agent['salary_type']=='Daily'?'selected':'' ?>>Daily</option>
                            <option value="Weekly" <?= $profile_agent['salary_type']=='Weekly'?'selected':'' ?>>Weekly</option>
                            <option value="Monthly" <?= $profile_agent['salary_type']=='Monthly'?'selected':'' ?>>Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-stone-600 mb-1">Base Payout Amount (₹)</label>
                        <input type="number" step="0.01" name="payout_amount" value="<?= $profile_agent['payout_amount'] ?>" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-bold text-emerald-700">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="toggleModal('editProfileModal')" class="bg-stone-200 text-stone-700 px-4 py-2 rounded-xl text-xs font-bold">Cancel</button>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ADD TASK MODAL -->
    <div id="taskModal" class="hidden fixed inset-0 bg-stone-900/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-stone-900 text-base">Add Delivery Task Item</h3>
                    <p class="text-xs text-stone-500">Client: <b id="task_client_name" class="text-emerald-700"></b></p>
                </div>
                <button onclick="toggleModal('taskModal')" class="text-stone-400 hover:text-stone-700 font-bold">✕</button>
            </div>
            
            <form method="POST" class="space-y-3">
                <input type="hidden" name="add_task" value="1">
                <input type="hidden" name="delivery_boy_id" value="<?= $profile_id ?>">
                <input type="hidden" name="client_id" id="task_client_id">

                <div>
                    <label class="block text-xs font-bold text-stone-600 mb-1">Meal / Item Name</label>
                    <input type="text" name="meal_name" id="task_item_name" placeholder="e.g. Lunch Thali" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-stone-600 mb-1">Delivery Charge (₹)</label>
                        <input type="number" step="0.01" name="delivery_charge" id="task_delivery_charge" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-bold text-emerald-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-stone-600 mb-1">Task Date</label>
                        <input type="date" name="task_date" id="task_date" required class="w-full px-3 py-2 bg-stone-50 border border-stone-300 rounded-xl text-xs font-semibold">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="toggleModal('taskModal')" class="bg-stone-200 text-stone-700 px-4 py-2 rounded-xl text-xs font-bold">Cancel</button>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold">Assign Task</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>