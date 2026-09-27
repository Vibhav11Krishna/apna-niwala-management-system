<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Define navigation items with clean labels
$nav_items = [
    ['file' => 'billing.php', 'label' => 'Billing Dashboard'],
    ['file' => 'ledger.php', 'label' => 'Client Ledger & Dues'],
    ['file' => 'weekly_reports.php', 'label' => 'Weekly Reports'],
    ['file' => 'monthly_stats.php', 'label' => 'Monthly & Yearly Stats'],
    ['file' => 'financial_stats.php', 'label' => 'Financial Stats'],
    ['file' => 'delivery.php', 'label' => 'Delivery Management'],
];
?>
<div class="w-72 bg-gradient-to-b from-emerald-950 via-emerald-900 to-stone-950 text-white flex flex-col h-screen shrink-0 shadow-2xl border-r border-emerald-800/50">
    <!-- Brand / Logo Header -->
    <div class="p-6 border-b border-emerald-800/60 flex flex-col items-center text-center space-y-3 bg-emerald-900/30">
        <!-- Logo Container with Increased Size -->
        <div class="relative group">
            <div class="absolute -inset-1 bg-gradient-to-r from-emerald-500 to-amber-400 rounded-3xl blur opacity-30 group-hover:opacity-60 transition duration-300"></div>
            <div class="relative w-24 h-24 bg-white rounded-2xl p-2.5 flex items-center justify-center shadow-lg">
                <!-- Replace 'logo.png' with your actual logo file name -->
                <img src="https://res.cloudinary.com/dqmkivr5i/image/upload/v1790272491/WhatsApp_Image_2026-09-24_at_11.03.41_PM_lqvrhi.jpg" alt="Apna Niwala" class="w-full h-full object-contain" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%23065f46\'><path d=\'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5\'/></svg>';">
            </div>
        </div>
        
        <div>
            <h2 class="text-xl font-black tracking-tight text-white flex items-center justify-center gap-1.5 mt-1">
                Apna Niwala
            </h2>
            <p class="text-[11px] text-emerald-300 font-medium italic mt-1 leading-snug tracking-wide opacity-90">
                &ldquo;Har Nivale Mein Ghar Ka Swaad, Apnapan Ke Saath.&rdquo;
            </p>
        </div>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto custom-scrollbar">
        <div class="text-[15px] font-bold uppercase tracking-widest text-emerald-400/70 px-3 mb-2">Main Navigation</div>
        
        <?php foreach ($nav_items as $index => $item): 
            $isActive = ($current_page == $item['file']);
        ?>
            <a href="<?= $item['file'] ?>" class="group relative flex items-center justify-between px-4 py-3 rounded-xl font-semibold text-xs transition-all duration-200 <?= $isActive ? 'bg-emerald-800 text-white shadow-lg shadow-emerald-950/50 ring-1 ring-emerald-500/30' : 'hover:bg-emerald-800/40 text-emerald-100/80 hover:text-white' ?>">
                <div class="flex items-center gap-3">
                    <span class="tracking-wide"><?= ($index + 1) . '. ' . $item['label'] ?></span>
                </div>
                <?php if ($isActive): ?>
                    <span class="w-1.5 h-5 bg-amber-400 rounded-full shadow-sm"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer Status / User Note -->
    <div class="p-4 border-t border-emerald-800/60 bg-emerald-950/40 text-center">
        <div class="flex items-center justify-center gap-2 text-[11px] text-emerald-300 font-medium">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> System Live & Operational
        </div>
    </div>
</div>