<?php
$pageTitle = "Dashboard";
require_once 'includes/session_check.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$user_currency = $_SESSION['preferred_currency'] ?? 'USD';
$userId = $_SESSION['user_id'];
$totalIncome = 0.00;
$totalExpenses = 0.00;
$netBalance = 0.00;
$monthlySummary = ['labels' => [], 'income_data' => [], 'expense_data' => []];

// Handle date filter parameters
$defaultStartDate = date('Y-m-01', strtotime('-5 months'));
$defaultEndDate = date('Y-m-d');
$startDate = $_GET['start_date'] ?? $defaultStartDate;
$endDate = $_GET['end_date'] ?? $defaultEndDate;

// Validate dates
if (!strtotime($startDate)) $startDate = $defaultStartDate;
if (!strtotime($endDate)) $endDate = $defaultEndDate;

// Ensure dates aren't in the future
$today = date('Y-m-d');
if ($startDate > $today) $startDate = $defaultStartDate;
if ($endDate > $today) $endDate = $today;

// Fetch totals with date filter
$stmt_income = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?");
$stmt_expenses = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?");

if ($stmt_income) {
    $stmt_income->bind_param("iss", $userId, $startDate, $endDate);
    $stmt_income->execute();
    $result = $stmt_income->get_result();
    $totalIncome = $result->fetch_assoc()['total'] ?? 0.00;
    $stmt_income->close();
}

if ($stmt_expenses) {
    $stmt_expenses->bind_param("iss", $userId, $startDate, $endDate);
    $stmt_expenses->execute();
    $result = $stmt_expenses->get_result();
    $totalExpenses = $result->fetch_assoc()['total'] ?? 0.00;
    $stmt_expenses->close();
}

$netBalance = $totalIncome - $totalExpenses;

// Fetch chart data
$sql_chart = "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month_year,
              SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS monthly_income,
              SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS monthly_expense
              FROM transactions
              WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
              GROUP BY month_year
              ORDER BY month_year ASC";

$stmt_chart = $conn->prepare($sql_chart);
if ($stmt_chart) {
    $stmt_chart->bind_param("iss", $userId, $startDate, $endDate);
    $stmt_chart->execute();
    $result = $stmt_chart->get_result();
    while ($row = $result->fetch_assoc()) {
        $monthlySummary['labels'][] = date("M Y", strtotime($row['month_year'] . "-01"));
        $monthlySummary['income_data'][] = floatval($row['monthly_income']);
        $monthlySummary['expense_data'][] = floatval($row['monthly_expense']);
    }
    $stmt_chart->close();
}

$conn->close();
?>

<?php require_once 'includes/header.php'; ?>


<div class="row">
    <div class="col-md-12">
        <h2 class="mb-4">Dashboard - Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
    </div>
</div>

<div class="row g-3">
    <!-- Summary Cards -->
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card text-center h-100 shadow-sm">
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <i class="fas fa-arrow-up fa-3x income-icon mb-2"></i>
                <h5 class="card-title">Total Income</h5>
                <p class="card-text fs-4 fw-bold text-success"><?php echo format_currency(floatval($totalIncome), $user_currency); ?></p>
                <small class="text-muted"><?php echo date('M j, Y', strtotime($startDate)); ?> to <?php echo date('M j, Y', strtotime($endDate)); ?></small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card text-center h-100 shadow-sm">
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <i class="fas fa-arrow-down fa-3x expense-icon mb-2"></i>
                <h5 class="card-title">Total Expenses</h5>
                <p class="card-text fs-4 fw-bold text-danger"><?php echo format_currency(floatval($totalExpenses), $user_currency); ?></p>
                <small class="text-muted"><?php echo date('M j, Y', strtotime($startDate)); ?> to <?php echo date('M j, Y', strtotime($endDate)); ?></small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-12 col-lg-4">
        <div class="card text-center h-100 shadow-sm">
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <i class="fas fa-balance-scale fa-3x balance-icon mb-2"></i>
                <h5 class="card-title">Net Balance</h5>
                <p class="card-text fs-4 fw-bold text-primary"><?php echo format_currency(floatval($netBalance), $user_currency); ?></p>
                <small class="text-muted"><?php echo date('M j, Y', strtotime($startDate)); ?> to <?php echo date('M j, Y', strtotime($endDate)); ?></small>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-8 mb-4 mb-lg-0">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center flex-wrap py-2">
                <h5 class="mb-2 mb-md-0"><i class="fas fa-chart-line me-1"></i>Financial Summary</h5>
                <form method="get" class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-2 w-100 w-md-auto mt-2 mt-md-0">
                    <div class="d-flex flex-column flex-sm-row gap-2 w-100">
                        <div class="flex-grow-1">
                            <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($startDate); ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="flex-grow-1">
                            <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($endDate); ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm flex-grow-1">Reset</a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="monthlyFinancialChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light py-2">
                <h5 class="mb-0"><i class="fas fa-link me-1"></i>Quick Links</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="add_transaction.php" class="list-group-item list-group-item-action py-2">
                    <i class="fas fa-plus-circle me-2 text-success"></i>Add New Transaction
                </a>
                <a href="transactions.php" class="list-group-item list-group-item-action py-2">
                    <i class="fas fa-list-alt me-2 text-info"></i>Manage Transactions
                </a>
                <a href="categories.php" class="list-group-item list-group-item-action py-2">
                    <i class="fas fa-tags me-2 text-warning"></i>Manage Categories
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action py-2">
                    <i class="fas fa-user-edit me-2 text-secondary"></i>Edit Profile
                </a>
            </div>
        </div>
    </div>
</div>


<style>
    @media (max-width: 767.98px) {
        .card-header .form-control {
            font-size: 0.85rem;
            padding: 0.3rem 0.5rem;
        }
        .card-header .btn {
            padding: 0.3rem 0.5rem;
            font-size: 0.85rem;
        }
        #monthlyFinancialChart {
            height: 250px !important;
        }
    }
    @media (max-width: 575.98px) {
        .card-header .form-control {
            font-size: 0.8rem;
        }
        .card-header .btn {
            font-size: 0.8rem;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart Configuration
    const ctx = document.getElementById('monthlyFinancialChart');
    const userCurrency = <?php echo json_encode($user_currency); ?>;
    
    function formatCurrency(amount, currency) {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2
        }).format(amount);
    }

    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthlySummary['labels']); ?>,
                datasets: [{
                    label: 'Income',
                    data: <?php echo json_encode($monthlySummary['income_data']); ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1,
                    fill: true
                }, {
                    label: 'Expenses',
                    data: <?php echo json_encode($monthlySummary['expense_data']); ?>,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value, userCurrency);
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    label += formatCurrency(context.parsed.y, userCurrency);
                                }
                                return label;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }

    // Set max dates for date inputs
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('start_date').max = today;
    document.getElementById('end_date').max = today;
    
    // Validate date range on form submit
    document.querySelector('form').addEventListener('submit', function(e) {
        const start = new Date(document.getElementById('start_date').value);
        const end = new Date(document.getElementById('end_date').value);
        
        if (start > end) {
            alert('End date must be after start date');
            e.preventDefault();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>