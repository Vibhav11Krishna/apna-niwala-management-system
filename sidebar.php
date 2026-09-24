<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="w-64 bg-emerald-900 text-white flex flex-col h-screen shrink-0">
    <div class="p-5 text-2xl font-bold border-b border-emerald-800 flex items-center gap-2">
         <span>Apna Niwala</span>
    </div>
    <nav class="flex-1 p-4 space-y-2">
        <a href="billing.php" class="block px-4 py-2.5 rounded font-semibold transition <?= ($current_page == 'billing.php') ? 'bg-emerald-800 text-white' : 'hover:bg-emerald-800 text-emerald-200' ?>">
            1. Billing Dashboard
        </a>
        <a href="ledger.php" class="block px-4 py-2.5 rounded font-semibold transition <?= ($current_page == 'ledger.php') ? 'bg-emerald-800 text-white' : 'hover:bg-emerald-800 text-emerald-200' ?>">
            2. Client Ledger & Dues
        </a>
        <a href="weekly_reports.php" class="block px-4 py-2.5 rounded font-semibold transition <?= ($current_page == 'weekly_reports.php') ? 'bg-emerald-800 text-white' : 'hover:bg-emerald-800 text-emerald-200' ?>">
            3. Weekly Reports
        </a>
        <a href="monthly_stats.php" class="block px-4 py-2.5 rounded font-semibold transition <?= ($current_page == 'monthly_stats.php') ? 'bg-emerald-800 text-white' : 'hover:bg-emerald-800 text-emerald-200' ?>">
            4. Monthly & Yearly Stats
        </a>
         <a href="financial_stats.php" class="block px-4 py-2.5 rounded font-semibold transition <?= ($current_page == 'financial_stats.php') ? 'bg-emerald-800 text-white' : 'hover:bg-emerald-800 text-emerald-200' ?>">
            5. Financial Stats
        </a>
         <a href="delivery.php" class="block px-4 py-2.5 rounded font-semibold transition <?= ($current_page == 'delivery.php') ? 'bg-emerald-800 text-white' : 'hover:bg-emerald-800 text-emerald-200' ?>">
            6. Delivery Management
        </a>
    </nav>
</div>