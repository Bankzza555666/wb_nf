<?php
// Server_price/products_all.php
// หน้าแสดงสินค้าทั้งหมด (VPN + SSH) - Modern Premium Design

require_once __DIR__ . '/../controller/config.php';

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// ดึงข้อมูล User
$user = array('username' => 'Guest', 'email' => '', 'credit' => 0);
if ($user_id > 0 && isset($conn) && $conn) {
    $stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $user = $row;
        }
        $stmt->close();
    }
}

// ===== VPN Products =====
$vpn_servers = array();
$vpn_products = array();

if (isset($conn) && $conn) {
    $vpn_query = "SELECT server_id, server_name, server_location FROM servers WHERE server_status = 'online' ORDER BY sort_order ASC";
    $vpn_result = $conn->query($vpn_query);
    if ($vpn_result) {
        while ($row = $vpn_result->fetch_assoc()) {
            $vpn_servers[$row['server_id']] = $row;
        }
    }
    
    $vpn_prod_query = "SELECT p.*, s.server_name, s.server_location 
                       FROM price_v2 p 
                       LEFT JOIN servers s ON p.server_id = s.server_id 
                       WHERE p.is_active = 1 ORDER BY p.is_popular DESC, p.sort_order ASC, p.id ASC";
    $vpn_prod_result = $conn->query($vpn_prod_query);
    if ($vpn_prod_result) {
        while ($row = $vpn_prod_result->fetch_assoc()) {
            $row['type'] = 'vpn';
            $vpn_products[] = $row;
        }
    }
}

// ===== SSH Products =====
$ssh_servers = array();
$ssh_products = array();

if (isset($conn) && $conn) {
    $ssh_query = "SELECT server_id, server_name, location FROM ssh_servers WHERE is_active = 1 ORDER BY server_name ASC";
    $ssh_result = $conn->query($ssh_query);
    if ($ssh_result) {
        while ($row = $ssh_result->fetch_assoc()) {
            $ssh_servers[$row['server_id']] = $row;
        }
    }
    
    $ssh_prod_query = "SELECT p.*, s.server_name, s.location as server_location, img.filename as product_image 
                       FROM ssh_products p 
                       LEFT JOIN ssh_servers s ON p.server_id = s.server_id 
                       LEFT JOIN product_images img ON p.image_id = img.id
                       WHERE p.is_active = 1 ORDER BY p.is_popular DESC, p.sort_order ASC, p.id ASC";
    $ssh_prod_result = $conn->query($ssh_prod_query);
    if ($ssh_prod_result) {
        while ($row = $ssh_prod_result->fetch_assoc()) {
            $row['type'] = 'ssh';
            $ssh_products[] = $row;
        }
    }
}

$all_products = array_merge($vpn_products, $ssh_products);
$total_products = count($all_products);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สินค้าทั้งหมด - <?php echo defined('SITE_NAME') ? SITE_NAME : 'NF~SHOP'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --primary: #E50914;
            --primary-light: #ff3d47;
            --primary-dark: #b20710;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --ssh-color: #8b5cf6;
            --dark-900: #0a0a0a;
            --dark-800: #121212;
            --dark-700: #1a1a1a;
            --dark-600: #242424;
            --text-primary: #ffffff;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --glass-bg: rgba(18, 18, 18, 0.95);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        * { box-sizing: border-box; }

        body { 
            background: var(--dark-900); 
            color: var(--text-primary); 
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse at 20% 20%, rgba(229, 9, 20, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(139, 92, 246, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            padding: 60px 0 40px;
            position: relative;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            letter-spacing: -1px;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        /* Stats Bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        @media (max-width: 768px) {
            .stats-bar { grid-template-columns: repeat(2, 1fr); }
        }

        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .stat-card:hover::before { opacity: 1; }
        .stat-card:hover { transform: translateY(-5px); }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.3rem;
        }

        .stat-icon.vpn { background: rgba(229, 9, 20, 0.15); color: var(--primary); }
        .stat-icon.ssh { background: rgba(139, 92, 246, 0.15); color: var(--ssh-color); }
        .stat-icon.server { background: rgba(59, 130, 246, 0.15); color: var(--info); }
        .stat-icon.total { background: rgba(16, 185, 129, 0.15); color: var(--success); }

        .stat-num {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Section */
        .filter-section {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 12px 28px;
            border: 1px solid var(--glass-border);
            background: var(--dark-700);
            color: var(--text-secondary);
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--text-primary);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-color: var(--primary);
            color: white;
            box-shadow: 0 4px 20px rgba(229, 9, 20, 0.3);
        }

        .filter-btn i { font-size: 1rem; }

        /* Search Bar */
        .search-wrapper {
            position: relative;
            max-width: 500px;
            margin: 0 auto 40px;
        }

        .search-input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            background: var(--dark-700);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.15);
        }

        .search-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
        }

        /* Product Card */
        .product-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .product-card.ssh::before {
            background: linear-gradient(90deg, var(--ssh-color), #a78bfa);
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            border-color: rgba(229, 9, 20, 0.3);
        }

        .product-card:hover::before { opacity: 1; }

        .card-header {
            height: 140px;
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.2), rgba(0, 0, 0, 0.4));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .product-card.ssh .card-header {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(0, 0, 0, 0.4));
        }

        .card-header::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            top: -50%;
            right: -30%;
        }

        .card-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-badge.vpn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .card-badge.ssh {
            background: linear-gradient(135deg, var(--ssh-color), #7c3aed);
            color: white;
        }

        .popular-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            background: linear-gradient(135deg, var(--warning), #d97706);
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .card-image {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .card-icon {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--primary);
        }

        .product-card.ssh .card-icon { color: var(--ssh-color); }

        .card-body {
            padding: 25px;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .card-server {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .card-server i { color: var(--text-muted); }

        .card-features {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .feature-tag {
            padding: 5px 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid var(--glass-border);
        }

        .price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .price span {
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--text-muted);
        }

        .btn-rent {
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-rent:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(229, 9, 20, 0.4);
            color: white;
        }

        .product-card.ssh .btn-rent {
            background: linear-gradient(135deg, var(--ssh-color), #7c3aed);
        }

        .product-card.ssh .btn-rent:hover {
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 30px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            grid-column: 1 / -1;
        }

        .empty-state-icon {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .empty-state-icon i {
            font-size: 3rem;
            color: var(--text-muted);
        }

        .empty-state h4 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-muted);
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .page-header { animation: fadeInUp 0.6s ease-out; }
        .stat-card { animation: fadeInUp 0.5s ease-out backwards; }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.15s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }
        .stat-card:nth-child(4) { animation-delay: 0.25s; }
        .product-card { animation: fadeInUp 0.5s ease-out backwards; }

        /* Responsive */
        @media (max-width: 576px) {
            .page-title { font-size: 2rem; }
            .filter-btn { padding: 10px 20px; font-size: 0.85rem; }
            .products-grid { grid-template-columns: 1fr; gap: 20px; }
            .card-body { padding: 20px; }
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>
<body>
    <?php 
    $navbar_file = __DIR__ . '/../home/navbar.php';
    if (file_exists($navbar_file)) { include $navbar_file; }
    ?>

    <div class="container py-4">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-store"></i> สินค้าทั้งหมด</h1>
            <p class="page-subtitle">เลือกดูแพ็กเกจ VPN และ SSH ที่เหมาะกับคุณ</p>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-icon vpn"><i class="fas fa-shield-alt"></i></div>
                <div class="stat-num"><?php echo count($vpn_products); ?></div>
                <div class="stat-label">แพ็กเกจ VPN</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon ssh"><i class="fas fa-terminal"></i></div>
                <div class="stat-num"><?php echo count($ssh_products); ?></div>
                <div class="stat-label">แพ็กเกจ SSH</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon server"><i class="fas fa-server"></i></div>
                <div class="stat-num"><?php echo count($vpn_servers) + count($ssh_servers); ?></div>
                <div class="stat-label">เซิร์ฟเวอร์</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon total"><i class="fas fa-box-open"></i></div>
                <div class="stat-num"><?php echo $total_products; ?></div>
                <div class="stat-label">สินค้าทั้งหมด</div>
            </div>
        </div>

        <!-- Search -->
        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="ค้นหาสินค้า...">
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <button class="filter-btn active" data-filter="all">
                <i class="fas fa-th-large"></i> ทั้งหมด
            </button>
            <button class="filter-btn" data-filter="vpn">
                <i class="fas fa-shield-alt"></i> VPN / V2Ray
            </button>
            <button class="filter-btn" data-filter="ssh">
                <i class="fas fa-terminal"></i> SSH / Netmod
            </button>
        </div>

        <!-- Products Grid -->
        <div class="products-grid" id="productsGrid">
            <?php if (empty($all_products)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h4>ไม่มีสินค้า</h4>
                    <p>ยังไม่มีสินค้าในระบบ</p>
                </div>
            <?php else: ?>
                <?php foreach ($all_products as $index => $product): ?>
                    <?php
                    $is_vpn = $product['type'] === 'vpn';
                    $type_class = $is_vpn ? 'vpn' : 'ssh';
                    $type_label = $is_vpn ? 'VPN' : 'SSH';
                    $icon = $is_vpn ? 'fa-shield-alt' : 'fa-terminal';
                    $name = isset($product['product_name']) ? $product['product_name'] : (isset($product['package_name']) ? $product['package_name'] : (isset($product['filename']) ? $product['filename'] : 'ไม่ระบุชื่อ'));
                    $server = isset($product['server_name']) ? $product['server_name'] : 'ไม่ระบุเซิร์ฟเวอร์';
                    $location = isset($product['server_location']) ? $product['server_location'] : '';
                    $price = isset($product['price_per_day']) ? $product['price_per_day'] : 0;
                    $link = $is_vpn ? '?p=rent_vpn' : '?p=rent_ssh';
                    $is_popular = isset($product['is_popular']) && $product['is_popular'];
                    $image = isset($product['product_image']) ? $product['product_image'] : null;
                    ?>
                    <div class="product-card <?php echo $type_class; ?>" data-type="<?php echo $type_class; ?>" data-name="<?php echo htmlspecialchars(strtolower($name . ' ' . $server)); ?>" style="animation-delay: <?php echo ($index * 0.05); ?>s">
                        <div class="card-header">
                            <span class="card-badge <?php echo $type_class; ?>"><?php echo $type_label; ?></span>
                            <?php if ($is_popular): ?>
                                <span class="popular-badge"><i class="fas fa-fire"></i> ยอดนิยม</span>
                            <?php endif; ?>
                            <?php if ($image): ?>
                                <img src="img/products/<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($name); ?>" class="card-image">
                            <?php else: ?>
                                <div class="card-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="card-title"><?php echo htmlspecialchars($name); ?></div>
                            <div class="card-server">
                                <i class="fas fa-server"></i>
                                <?php echo htmlspecialchars($server); ?>
                                <?php if ($location): ?>
                                    <span>•</span>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($location); ?>
                                <?php endif; ?>
                            </div>
                            <div class="card-features">
                                <?php if ($is_vpn): ?>
                                    <?php if (!empty($product['protocol'])): ?>
                                        <span class="feature-tag"><i class="fas fa-shield-alt me-1"></i><?php echo strtoupper($product['protocol']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($product['network'])): ?>
                                        <span class="feature-tag"><i class="fas fa-network-wired me-1"></i><?php echo strtoupper($product['network']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($product['max_devices'])): ?>
                                        <span class="feature-tag"><i class="fas fa-mobile-alt me-1"></i><?php echo $product['max_devices']; ?> อุปกรณ์</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php 
                                    // SSH uses features JSON from database
                                    $features = isset($product['features']) ? json_decode($product['features'], true) : array();
                                    if (is_array($features) && !empty($features)):
                                        foreach (array_slice($features, 0, 3) as $feature): 
                                    ?>
                                        <span class="feature-tag"><?php echo htmlspecialchars($feature); ?></span>
                                    <?php 
                                        endforeach;
                                    else: 
                                    ?>
                                        <span class="feature-tag"><i class="fas fa-wifi me-1"></i>เน็ตฟรี</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="price">฿<?php echo number_format($price, 0); ?> <span>/ วัน</span></div>
                                <a href="<?php echo $link; ?>" class="btn-rent">
                                    <i class="fas fa-shopping-cart"></i> เช่าเลย
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php 
    $footer_file = __DIR__ . '/../home/footer.php';
    if (file_exists($footer_file)) { include $footer_file; }
    ?>

    <script>
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.dataset.filter;
                
                document.querySelectorAll('.product-card').forEach(card => {
                    if (filter === 'all' || card.dataset.type === filter) {
                        card.style.display = 'block';
                        card.style.animation = 'fadeInUp 0.4s ease-out';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.dataset.name || '';
                if (name.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
