<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

// التحقق من تسجيل الدخول
checkAuth();

// التحقق من الصلاحيات
checkPermission('view_reports');

// تعريف مجموعات التقارير
$reportGroups = [
    'التقارير المالية' => [
        [
            'title' => 'تقرير الإيرادات والمصروفات',
            'description' => 'عرض تفصيلي للإيرادات والمصروفات مع رسوم بيانية',
            'icon' => 'bi bi-graph-up',
            'url' => 'financial_reports.php',
            'color' => 'primary'
        ],
        [
            'title' => 'تقرير الأرباح والخسائر',
            'description' => 'تحليل شامل للأرباح والخسائر مع النسب المئوية',
            'icon' => 'bi bi-pie-chart',
            'url' => 'profit_loss.php',
            'color' => 'success'
        ],
        [
            'title' => 'تقرير المدفوعات المتأخرة',
            'description' => 'عرض المدفوعات المتأخرة والمستحقة',
            'icon' => 'bi bi-clock-history',
            'url' => 'late_payments.php',
            'color' => 'danger'
        ]
    ],
    'تقارير العقود' => [
        [
            'title' => 'تقرير العقود النشطة',
            'description' => 'عرض جميع العقود النشطة وتفاصيلها',
            'icon' => 'bi bi-file-text',
            'url' => 'active_contracts.php',
            'color' => 'info'
        ],
        [
            'title' => 'تقرير العقود المنتهية',
            'description' => 'عرض العقود المنتهية وإحصائياتها',
            'icon' => 'bi bi-file-x',
            'url' => 'expired_contracts.php',
            'color' => 'warning'
        ]
    ],
    'تقارير الوحدات' => [
        [
            'title' => 'تقرير إشغال الوحدات',
            'description' => 'نسب الإشغال وحالة كل وحدة',
            'icon' => 'bi bi-building',
            'url' => 'units_occupancy.php',
            'color' => 'primary'
        ],
        [
            'title' => 'تقرير صيانة الوحدات',
            'description' => 'سجل الصيانة وتكاليفها لكل وحدة',
            'icon' => 'bi bi-tools',
            'url' => 'units_maintenance.php',
            'color' => 'secondary'
        ]
    ],
    'تقارير المستأجرين' => [
        [
            'title' => 'تقرير المستأجرين النشطين',
            'description' => 'قائمة المستأجرين النشطين وبياناتهم',
            'icon' => 'bi bi-people',
            'url' => 'active_tenants.php',
            'color' => 'success'
        ],
        [
            'title' => 'تقرير سجل المستأجرين',
            'description' => 'السجل التاريخي للمستأجرين ومدفوعاتهم',
            'icon' => 'bi bi-person-lines-fill',
            'url' => 'tenants_history.php',
            'color' => 'info'
        ]
    ]
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-card {
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-5px);
        }
        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .icon-circle i {
            font-size: 24px;
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>
   <div class="main-content"> 
        <div class="container mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>التقارير</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../index.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">التقارير</li>
                    </ol>
                </nav>
            </div>

            <?php foreach ($reportGroups as $groupName => $reports): ?>
            <div class="mb-5">
                <h3 class="mb-4"><?php echo $groupName; ?></h3>
                <div class="row g-4">
                    <?php foreach ($reports as $report): ?>
                    <div class="col-md-6 col-lg-4">
                        <a href="<?php echo $report['url']; ?>" class="text-decoration-none">
                            <div class="card h-100 report-card">
                                <div class="card-body">
                                    <div class="icon-circle bg-<?php echo $report['color']; ?> bg-opacity-10 text-<?php echo $report['color']; ?>">
                                        <i class="<?php echo $report['icon']; ?>"></i>
                                    </div>
                                    <h5 class="card-title text-dark"><?php echo $report['title']; ?></h5>
                                    <p class="card-text text-muted"><?php echo $report['description']; ?></p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
