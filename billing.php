<?php
// Database Connection
$host = 'localhost';$db   = 'apna_niwala_db';
$user = 'root';$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Auto-add columns if they don't exist yet
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS remarks TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1");
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS delivery_charge DECIMAL(10,2) DEFAULT 0.00");
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Fetch all existing clients for the dropdown selection
$existing_clients =$pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_selection =$_POST['client_selection'] ?? 'new';
    
    if ($client_selection === 'existing') {
        $client_id = intval($_POST['existing_client_id']);
        
        $stmt =$pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $client =$stmt->fetch();
        
        if (!$client) {
            die("Selected client not found.");
        }
    } else {
        $client_name  = trim($_POST['client_name']);
        $address      = trim($_POST['address']);
        $phone_no     = trim($_POST['phone_no']);

        $stmt =$pdo->prepare("SELECT client_id FROM clients WHERE phone_no = ?");
        $stmt->execute([$phone_no]);
        $client =$stmt->fetch();

        if ($client) {
            $client_id =$client['client_id'];
        } else {
            $stmt =$pdo->prepare("INSERT INTO clients (name, address, phone_no) VALUES (?, ?, ?)");
            $stmt->execute([$client_name, $address,$phone_no]);
            $client_id =$pdo->lastInsertId();
        }
    }

    $diet_type      =$_POST['diet_type'];
    $meal_name      =$_POST['meal_name'];
    $meal_date      =$_POST['meal_date'];
    $due_date       =$_POST['due_date'];
    $quantity       = intval($_POST['quantity']);
    $delivery_charge= floatval($_POST['delivery_charge']);
    $meal_amount    = floatval($_POST['meal_amount']); // Total subtotal (price * qty + delivery)
    $amount_rec     = floatval($_POST['amount_received']);
    $total_due      = floatval($_POST['total_due']);
    $payment_mode   =$_POST['payment_mode'];
    $remarks        = trim($_POST['remarks'] ?? '');

    // Insert Transaction Record into payments
    $stmt =$pdo->prepare("INSERT INTO payments (client_id, meal_name, diet_type, meal_date, due_date, quantity, delivery_charge, meal_amount, amount_received, total_due, payment_mode, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$client_id,$meal_name, $diet_type,$meal_date, $due_date,$quantity, $delivery_charge,$meal_amount, $amount_rec,$total_due, $payment_mode,$remarks]);

    echo "<script>alert('Order Billed & Saved Successfully!'); window.location.href='billing.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Dashboard - Apna Niwala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        const mealDatabase = {
            "Veg": [
                { name: "Mini Meal", price: 79 },
                { name: "Regular Meal", price: 99 },
                { name: "Hunger Meal", price: 119 },
                { name: "Rajma Chawal", price: 109 },
                { name: "Kadhi Chawal", price: 109 },
                { name: "Mix Veg Meal", price: 109 },
                { name: "Aloo Paneer Meal", price: 119 },
                { name: "Baingan Bharta Meal", price: 109 },
                { name: "Palak Paneer Meal", price: 119 },
                { name: "Idli Sambhar", price: 99 },
                { name: "Sattu Paratha Meal", price: 109 },
                { name: "Khichdi Chokha Meal", price: 109 },
                { name: "Sunday Veg Special", price: 109 },
                { name: "Mushroom Masala Meal", price: 129 },
                { name: "Mushroom Paneer Meal", price: 139 },
                { name: "Monthly Veg Plan", price: 2599 },
                { name: "Monthly Veg Plan ", price: 1850 },
            ],
            "Non-Veg": [
                { name: "Egg Meal ", price: 99 },
                { name: "Chicken Meal ", price: 149 },
                { name: "Monthly Non-Veg Plan", price: 2899 }
            ]
        };

        const existingClientsData = <?php echo json_encode($existing_clients); ?>;

        function toggleClientMode() {
            const mode = document.getElementById('clientSelectionMode').value;
            const existingSection = document.getElementById('existingClientSection');
            const newSection = document.getElementById('newClientSection');

            if (mode === 'existing') {
                existingSection.classList.remove('hidden');
                newSection.classList.add('hidden');
                document.getElementById('inputClientName').removeAttribute('required');
                document.getElementById('inputPhoneNo').removeAttribute('required');
                document.getElementById('inputAddress').removeAttribute('required');
            } else {
                existingSection.classList.add('hidden');
                newSection.classList.remove('hidden');
                document.getElementById('inputClientName').setAttribute('required', 'true');
                document.getElementById('inputPhoneNo').setAttribute('required', 'true');
                document.getElementById('inputAddress').setAttribute('required', 'true');
            }
        }

        function fillExistingClientDetails() {
            const select = document.getElementById('existingClientSelect');
            const clientId = select.value;
            const infoBox = document.getElementById('selectedClientInfo');

            if (!clientId) {
                infoBox.innerText = 'Select a client to view their contact and address info.';
                return;
            }

            const client = existingClientsData.find(c => c.client_id == clientId);
            if (client) {
                infoBox.innerHTML = `<b>📞 Phone:</b> ${client.phone_no} | <b>📍 Address:</b> ${client.address}`;
            }
        }

        function filterMeals() {
            const diet = document.getElementById('dietType').value;
            const mealSelect = document.getElementById('mealSelect');
            mealSelect.innerHTML = '';

            mealDatabase[diet].forEach(meal => {
                let option = document.createElement('option');
                option.value = meal.name;
                option.textContent = `${meal.name} — ₹${meal.price}`;
                option.setAttribute('data-price', meal.price);
                mealSelect.appendChild(option);
            });
            calculateTotals();
        }

        function calculateTotals() {
            const mealSelect = document.getElementById('mealSelect');
            const selectedOption = mealSelect.options[mealSelect.selectedIndex];
            const basePrice = selectedOption ? parseFloat(selectedOption.getAttribute('data-price')) : 0;
            
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            const deliveryCharge = parseFloat(document.getElementById('deliveryCharge').value) || 0;

            const totalMealPrice = (basePrice * quantity) + deliveryCharge;

            document.getElementById('mealAmount').value = totalMealPrice.toFixed(2);
            document.getElementById('displayMealAmount').innerText = '₹' + totalMealPrice.toFixed(2);

            const received = parseFloat(document.getElementById('amountReceived').value) || 0;
            const dues = totalMealPrice - received;

            document.getElementById('totalDue').value = dues >= 0 ? dues.toFixed(2) : '0.00';
            document.getElementById('displayTotalDue').innerText = '₹' + (dues >= 0 ? dues.toFixed(2) : '0.00');
        }

        function setQuickPayment(type) {
            const totalMealPrice = parseFloat(document.getElementById('mealAmount').value) || 0;
            const amountReceivedInput = document.getElementById('amountReceived');
            const paymentModeSelect = document.querySelector('select[name="payment_mode"]');

            if (type === 'full') {
                amountReceivedInput.value = totalMealPrice.toFixed(2);
                paymentModeSelect.value = 'Cash';
            } else if (type === 'credit') {
                amountReceivedInput.value = '0.00';
                paymentModeSelect.value = 'Credit';
            }
            calculateTotals();
        }

        window.onload = function() {
            filterMeals();
            toggleClientMode();
        };
    </script>
</head>
<body class="bg-amber-50/60 font-sans text-stone-800">
    <div class="flex h-screen overflow-hidden">
        <?php if(file_exists('sidebar.php')) include 'sidebar.php'; ?>

        <div class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-6xl mx-auto">
                <div class="mb-6 flex justify-between items-end">
                    <div>
                        <h1 class="text-3xl font-extrabold text-stone-900 tracking-tight">New Meal Billing</h1>
                        <p class="text-stone-500 text-sm mt-1">Select existing repeat clients or add new customers, choose meal plans, quantities, and track dues.</p>
                    </div>
                    <a href="ledger.php" class="text-xs font-bold bg-white border border-stone-200 text-stone-700 px-4 py-2 rounded-xl shadow-sm hover:bg-stone-50 transition">View Ledger & Dues &rarr;</a>
                </div>

                <form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Left 2 Columns: Inputs -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Card 1: Customer Information -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 space-y-4">
                            <div class="flex justify-between items-center">
                                <h2 class="text-sm font-bold uppercase tracking-wider text-stone-400 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Customer Information
                                </h2>
                                <div>
                                    <select id="clientSelectionMode" name="client_selection" onchange="toggleClientMode()" class="px-3 py-1.5 bg-stone-100 border border-stone-300 rounded-xl text-xs font-bold text-stone-700 focus:outline-none">
                                        <option value="existing">📂 Select Existing Client</option>
                                        <option value="new">➕ Add New Customer</option>
                                    </select>
                                </div>
                            </div>

                            <div id="existingClientSection" class="space-y-2">
                                <label class="block text-xs font-bold text-stone-600 mb-1">Select Customer from Database</label>
                                <select id="existingClientSelect" name="existing_client_id" onchange="fillExistingClientDetails()" class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-semibold">
                                    <option value="">-- Choose registered client --</option>
                                    <?php foreach($existing_clients as$ec): ?>
                                        <option value="<?= $ec['client_id'] ?>"><?= htmlspecialchars($ec['name']) ?> (<?= htmlspecialchars($ec['phone_no']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="selectedClientInfo" class="text-xs text-stone-500 bg-stone-50 p-3 rounded-xl border border-stone-200 mt-2">
                                    Select a client to view their contact and address info.
                                </div>
                            </div>

                            <div id="newClientSection" class="hidden space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-stone-600 mb-1">Customer Name</label>
                                        <input type="text" id="inputClientName" name="client_name" placeholder="e.g. Enter Name" class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-medium">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-stone-600 mb-1">Phone Number</label>
                                        <input type="text" id="inputPhoneNo" name="phone_no" placeholder="e.g. Enter Phone Number" class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-medium">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-stone-600 mb-1">Delivery Address</label>
                                    <textarea id="inputAddress" name="address" rows="2" placeholder="House/Flat no., Street, Landmark..." class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-medium"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Meal & Schedule -->
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 space-y-4">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-400 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span> Meal Plan & Timeline
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-stone-600 mb-1">Dietary Type</label>
                                    <select id="dietType" name="diet_type" onchange="filterMeals()" class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-semibold">
                                        <option value="Veg">Veg 🟢</option>
                                        <option value="Non-Veg">Non-Veg 🔴</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-stone-600 mb-1">Meal Confirmed Date</label>
                                    <input type="date" name="meal_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-medium">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-stone-600 mb-1">Payment Due Date</label>
                                    <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-medium">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-stone-600 mb-1">Select Meal / Plan Package</label>
                                    <select id="mealSelect" name="meal_name" onchange="calculateTotals()" class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-semibold">
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-stone-600 mb-1">Quantity</label>
                                    <input type="number" min="1" id="quantity" name="quantity" value="1" oninput="calculateTotals()" required class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-semibold">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-stone-600 mb-1">Delivery Charge (₹)</label>
                                <input type="number" step="0.01" min="0" id="deliveryCharge" name="delivery_charge" value="0.00" oninput="calculateTotals()" class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-semibold">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-stone-600 mb-1">Order Remarks / Special Instructions</label>
                                <textarea name="remarks" rows="2" placeholder="e.g. Less spicy, pack extra salad, deliver by 1:30 PM..." class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-medium"></textarea>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Sticky Summary & Payment Controls -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white p-6 rounded-2xl shadow-sm border border-stone-200/80 sticky top-6 space-y-5">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-400 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span> Payment Breakdown
                            </h2>

                            <!-- Quick Preset Actions -->
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" onclick="setQuickPayment('full')" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs py-2 rounded-xl font-bold transition">⚡ Full Paid Cash</button>
                                <button type="button" onclick="setQuickPayment('credit')" class="bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs py-2 rounded-xl font-bold transition">📋 Full Credit Due</button>
                            </div>

                            <hr class="border-stone-100">

                            <!-- Hidden raw inputs needed for POST submission -->
                            <input type="hidden" id="mealAmount" name="meal_amount">
                            <input type="hidden" id="totalDue" name="total_due">

                            <div class="space-y-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-stone-500 font-medium">Grand Total:</span>
                                    <span id="displayMealAmount" class="font-extrabold text-stone-900 text-base">₹0.00</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-stone-600 mb-1">Amount Received (₹)</label>
                                    <input type="number" step="0.01" id="amountReceived" name="amount_received" value="0" oninput="calculateTotals()" required class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition font-extrabold text-base text-emerald-700">
                                </div>

                                <div class="flex justify-between items-center bg-red-50/60 border border-red-100 p-3 rounded-xl">
                                    <span class="text-red-700 text-xs font-bold uppercase">Pending Dues:</span>
                                    <span id="displayTotalDue" class="font-black text-red-600 text-lg">₹0.00</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-stone-600 mb-1">Payment Mode</label>
                                <select name="payment_mode" class="w-full px-3.5 py-2.5 bg-stone-50/50 border border-stone-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition text-sm font-medium">
                                    <option value="Cash">💵 Cash</option>
                                    <option value="UPI">📱 UPI (PhonePe / GPay / Paytm)</option>
                                    <option value="Bank Transfer">🏦 Bank Transfer</option>
                                    <option value="Credit">📋 Credit (Added entirely to Dues)</option>
                                </select>
                            </div>

                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white py-3.5 rounded-xl font-bold transition shadow-md shadow-emerald-600/20 text-sm">
                                Save Bill & Update Ledger 🚀
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>