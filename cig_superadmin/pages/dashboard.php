<?php
/**
 * CIG Admin Dashboard - Modern Professional Dashboard
 * Displays comprehensive statistics, analytics and recent submissions
 */

session_start();
require_once '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();

// Get date range (default: last 30 days)
$date_range = isset($_GET['range']) ? $_GET['range'] : '30';
$start_date = match($date_range) {
    '7' => date('Y-m-d', strtotime('-7 days')),
    '12' => date('Y-m-d', strtotime('-12 months')),
    default => date('Y-m-d', strtotime('-30 days')),
};

try {
    // Get KPI Statistics
    $stats = $db->fetchRow("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            ROUND(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as approval_rate,
            ROUND(AVG(DATEDIFF(NOW(), submitted_at)), 0) as avg_processing_time
        FROM submissions
        WHERE submitted_at >= ?
    ", [$start_date]);

    // Get monthly submission trend (last 12 months)
    $monthly_trend = $db->fetchAll("
        SELECT DATE_FORMAT(submitted_at, '%Y-%m') as month, COUNT(*) as count
        FROM submissions
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
        ORDER BY month ASC
    ");

    // Get submission status distribution
    $status_distribution = $db->fetchAll("
        SELECT status, COUNT(*) as count
        FROM submissions
        WHERE submitted_at >= ?
        GROUP BY status
    ", [$start_date]);

    // Get submissions per organization
    $org_submissions = $db->fetchAll("
        SELECT COALESCE(u.org_name, o.org_name) as org_name, COUNT(s.submission_id) as count
        FROM submissions s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN users o ON s.org_id = o.user_id
        WHERE s.submitted_at >= ?
          AND COALESCE(u.org_name, o.org_name) IS NOT NULL
        GROUP BY COALESCE(u.org_name, o.org_name)
        ORDER BY count DESC
        LIMIT 10
    ", [$start_date]);

    // Get approved vs rejected trend
    $approval_trend = $db->fetchAll("
        SELECT 
            DATE_FORMAT(submitted_at, '%Y-%m') as month,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM submissions
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
        ORDER BY month ASC
    ");

    // Get smart insights
    $most_active_org = $db->fetchRow("
        SELECT COALESCE(u.org_name, o.org_name) as org_name, COUNT(*) as submission_count
        FROM submissions s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN users o ON s.org_id = o.user_id
        WHERE s.submitted_at >= ?
          AND COALESCE(u.org_name, o.org_name) IS NOT NULL
        GROUP BY COALESCE(u.org_name, o.org_name)
        ORDER BY submission_count DESC
        LIMIT 1
    ", [$start_date]);

    $oldest_pending = $db->fetchRow("
        SELECT DATEDIFF(NOW(), submitted_at) as days_pending
        FROM submissions
        WHERE status = 'pending'
        ORDER BY submitted_at ASC
        LIMIT 1
    ");

    $highest_month = $db->fetchRow("
        SELECT DATE_FORMAT(submitted_at, '%Y-%m') as month, COUNT(*) as count
        FROM submissions
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
        ORDER BY count DESC
        LIMIT 1
    ");

    $lowest_month = $db->fetchRow("
        SELECT DATE_FORMAT(submitted_at, '%Y-%m') as month, COUNT(*) as count
        FROM submissions
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
        ORDER BY count ASC
        LIMIT 1
    ");

    // Get recent submissions
    $recent_submissions = $db->fetchAll("
        SELECT s.*, u.full_name, COALESCE(u.org_name, o.org_name) as org_name
        FROM submissions s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN users o ON s.org_id = o.user_id
        ORDER BY s.submitted_at DESC
        LIMIT 15
    ");

    // Get recent notifications
    $notifications = $db->fetchAll("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = false
        ORDER BY created_at DESC
        LIMIT 5
    ", [1]);

    $unread_count = count($notifications);
} catch (Exception $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    $stats = null;
    $recent_submissions = [];
    $notifications = [];
    $monthly_trend = [];
    $status_distribution = [];
    $org_submissions = [];
    $approval_trend = [];
    $most_active_org = null;
    $oldest_pending = null;
    $highest_month = null;
    $lowest_month = null;
    $unread_count = 0;
}

$user = ['full_name' => $_SESSION['admin_email'] ?? 'Admin'];

// Helper function to format time ago
function timeAgo($date) {
    $time = strtotime($date);
    $current_time = time();
    $diff = $current_time - $time;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . " minute" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date('M d, Y', $time);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CIG Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/navbar.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<?php 
$current_page = 'dashboard';
$unread_count = $unread_count ?? 0;
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

<!-- DASHBOARD -->
<div class="main">
    <div style="padding: 30px;">
        <div class="page-header">
            <h2><i class="fas fa-chart-line"></i> Dashboard</h2>
        </div>

        <div class="filter-section">
            <div class="filter-buttons">
                <button class="filter-btn <?php echo $date_range == '7' ? 'active' : ''; ?>" onclick="filterByRange('7')">Last 7 Days</button>
                <button class="filter-btn <?php echo $date_range == '30' ? 'active' : ''; ?>" onclick="filterByRange('30')">Last 30 Days</button>
                <button class="filter-btn <?php echo $date_range == '12' ? 'active' : ''; ?>" onclick="filterByRange('12')">Last 12 Months</button>
            </div>
            <button class="export-btn" onclick="exportCharts()"><i class="fas fa-download"></i> Export PDF</button>
        </div>

        <div class="kpi-cards">
            <div class="kpi-card total" onclick="filterTableByStatus('all')">
                <div class="kpi-icon"><i class="fas fa-file-alt"></i></div>
                <h3 class="kpi-title">Total Submissions</h3>
                <p class="kpi-value"><?php echo $stats ? $stats['total'] : '0'; ?></p>
                <div class="kpi-change">All submissions</div>
            </div>
            <div class="kpi-card pending" onclick="filterTableByStatus('pending')">
                <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
                <h3 class="kpi-title">Pending</h3>
                <p class="kpi-value"><?php echo $stats ? $stats['pending'] : '0'; ?></p>
                <div class="kpi-change">Awaiting review</div>
            </div>
            <div class="kpi-card approved" onclick="filterTableByStatus('approved')">
                <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
                <h3 class="kpi-title">Approved</h3>
                <p class="kpi-value"><?php echo $stats ? $stats['approved'] : '0'; ?></p>
                <div class="kpi-change">Approved submissions</div>
            </div>
            <div class="kpi-card rejected" onclick="filterTableByStatus('rejected')">
                <div class="kpi-icon"><i class="fas fa-ban"></i></div>
                <h3 class="kpi-title">Rejected</h3>
                <p class="kpi-value"><?php echo $stats ? $stats['rejected'] : '0'; ?></p>
                <div class="kpi-change">Rejected items</div>
            </div>
        </div>

        <div class="charts-section">
            <div class="chart-card">
                <h4 class="chart-title"><i class="fas fa-chart-line"></i> Monthly Submission Trend</h4>
                <canvas id="monthlyTrendChart" style="max-height: 250px;"></canvas>
            </div>
            <div class="chart-card">
                <h4 class="chart-title"><i class="fas fa-chart-doughnut"></i> Status Distribution</h4>
                <canvas id="statusDistributionChart" style="max-height: 250px;"></canvas>
            </div>
        </div>

        <div class="full-width-chart">
            <div class="chart-card">
                <h4 class="chart-title"><i class="fas fa-bars"></i> Submissions per Organization</h4>
                <canvas id="orgSubmissionsChart" style="min-height:220px;max-height:320px;"></canvas>
            </div>
        </div>

        <div class="charts-section">
            <div class="chart-card">
                <h4 class="chart-title"><i class="fas fa-chart-line"></i> Approved vs Rejected Trend</h4>
                <canvas id="approvalTrendChart" style="max-height: 250px;"></canvas>
            </div>
            <div class="insights-panel">
                <h4 class="insights-title"><i class="fas fa-lightbulb"></i> Smart Insights</h4>
                <div class="insight-item">
                    <div class="insight-label">Most Active Organization</div>
                    <div class="insight-value"><?php echo $most_active_org ? htmlspecialchars($most_active_org['org_name']) : 'N/A'; ?></div>
                </div>
                <div class="insight-item">
                    <div class="insight-label">Oldest Pending Submission</div>
                    <div class="insight-value"><?php echo $oldest_pending ? $oldest_pending['days_pending'] . ' days' : 'N/A'; ?></div>
                </div>
                <div class="insight-item">
                    <div class="insight-label">Highest Submission Month</div>
                    <div class="insight-value"><?php echo $highest_month ? $highest_month['month'] : 'N/A'; ?></div>
                </div>
                <div class="insight-item">
                    <div class="insight-label">Lowest Submission Month</div>
                    <div class="insight-value"><?php echo $lowest_month ? $lowest_month['month'] : 'N/A'; ?></div>
                </div>
            </div>
        </div>

        <div class="recent-submissions">
            <div class="submissions-header"><i class="fas fa-history"></i> Recent Submissions</div>
            <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
            <table class="submissions-table" style="min-width:550px;">
                <thead>
                    <tr>
                        <th>Ref No</th><th>Organization</th><th>Title</th><th>Status</th><th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_submissions)): ?>
                        <?php foreach ($recent_submissions as $index => $submission): ?>
                            <tr>
                                <td><span class="ref-no"><?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></span></td>
                                <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($submission['title']); ?></td>
                                <td><span class="status <?php echo strtolower(str_replace('_', '', $submission['status'])); ?>"><?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #999; padding: 30px;"><i class="fas fa-inbox"></i> No submissions found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
  <?php include 'footer.php'; ?>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="../js/navbar.js"></script>

<script>
const chartColors = {primary:'#2e7d32', secondary:'#1976d2', success:'#388e3c', danger:'#d32f2f', warning:'#f57c00', info:'#0288d1'};

// Monthly Trend Chart
const monthlyData = <?php echo json_encode($monthly_trend); ?>;
if (monthlyData.length > 0) {
    new Chart(document.getElementById('monthlyTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: monthlyData.map(d => d.month),
            datasets: [{
                label: 'Submissions',
                data: monthlyData.map(d => d.count),
                borderColor: chartColors.primary,
                backgroundColor: 'rgba(46, 125, 50, 0.12)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointRadius: monthlyData.length === 1 ? 8 : 5,
                pointBackgroundColor: chartColors.primary,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                spanGaps: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { usePointStyle: true, font: { size: 13, weight: 'bold' } } },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { precision: 0 }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
}

// Status Distribution Chart  
const statusData = <?php echo json_encode($status_distribution); ?>;
if (statusData.length > 0) {
    // Map status to color
    const statusColorMap = {
        'approved': '#10b981',      // Green
        'in_review': '#3b82f6',     // Blue
        'pending': '#f59e0b',       // Orange
        'rejected': '#ef4444'       // Red
    };
    
    const statusColors = statusData.map(d => statusColorMap[d.status] || '#9ca3af');
    
    new Chart(document.getElementById('statusDistributionChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: statusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
            datasets: [{
                data: statusData.map(d => d.count),
                backgroundColor: statusColors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom', labels: {usePointStyle: true, font: {size: 13, weight: 'bold'}}}}}
    });
}

// Organization Submissions Chart
const orgData = <?php echo json_encode($org_submissions); ?>;
if (orgData.length > 0) {
    // Truncate long org names for display under bars
    const truncateLabel = (name, maxLen) => {
        if (name.length <= maxLen) return name;
        // Split into multiple lines if too long
        const words = name.split(' ');
        const lines = [];
        let line = '';
        words.forEach(word => {
            if ((line + ' ' + word).trim().length > maxLen) {
                if (line) lines.push(line.trim());
                line = word;
            } else {
                line = (line + ' ' + word).trim();
            }
        });
        if (line) lines.push(line.trim());
        return lines;
    };

    const isMobile = window.innerWidth <= 768;
    const maxLabelLen = isMobile ? 14 : 20;

    new Chart(document.getElementById('orgSubmissionsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: orgData.map(d => truncateLabel(d.org_name, maxLabelLen)),
            datasets: [{
                label: 'Submissions',
                data: orgData.map(d => d.count),
                backgroundColor: chartColors.primary,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 20, bottom: 5, left: 5, right: 5 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: (items) => orgData[items[0].dataIndex].org_name
                    }
                },
                // Show value on top of each bar
                datalabels: { display: false }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: isMobile ? 10 : 12, weight: '500' },
                        maxRotation: 0,
                        autoSkip: false,
                        color: '#374151'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
}

// Approval Trend Chart
const trendData = <?php echo json_encode($approval_trend); ?>;
if (trendData.length > 0) {
    new Chart(document.getElementById('approvalTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.month),
            datasets: [
                {
                    label: 'Approved',
                    data: trendData.map(d => d.approved),
                    borderColor: chartColors.success,
                    backgroundColor: 'rgba(56, 142, 60, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: trendData.length === 1 ? 8 : 5,
                    pointBackgroundColor: chartColors.success,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    spanGaps: true
                },
                {
                    label: 'Rejected',
                    data: trendData.map(d => d.rejected),
                    borderColor: chartColors.danger,
                    backgroundColor: 'rgba(211, 47, 47, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: trendData.length === 1 ? 8 : 5,
                    pointBackgroundColor: chartColors.danger,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { usePointStyle: true, font: { size: 13, weight: 'bold' } } },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { precision: 0 }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
}

// Utility Functions
function filterByRange(range) {
    window.location.href = `dashboard.php?range=${range}`;
}

function filterTableByStatus(status) {
    const rows = document.querySelectorAll('.submissions-table tbody tr');
    rows.forEach(row => {
        if (status === 'all') {
            row.style.display = '';
        } else {
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge && statusBadge.textContent.toLowerCase() === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

function exportCharts() {
    const element = document.querySelector('.main > div');
    const opt = {
        margin: 10,
        filename: 'CIG-Dashboard-Report.pdf',
        image: {type: 'jpeg', quality: 0.98},
        html2canvas: {scale: 2},
        jsPDF: {orientation: 'landscape', unit: 'mm', format: 'a4'}
    };
    html2pdf().set(opt).from(element).save();
}
</script>
</body>
</html>