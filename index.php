<?php
$pageTitle = "Dashboard";
require_once 'includes/session_check.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$user_currency = $_SESSION['preferred_currency'] ?? 'USD';
$userId = $_SESSION['user_id'];

// Variables for summary cards
$totalIncomeForPeriod = 0.00;
$totalExpensesForPeriod = 0.00;
$accountBalanceAtEndOfPeriod = 0.00; 

$dailyCashflow = [
    'labels' => [],
    'income_data' => [],
    'expense_data' => [],
    'cumulative_net_data' => []
];

// Handle date filter parameters
$defaultStartDate = date('Y-m-01');
$defaultEndDate = date('Y-m-d');
$startDate = $_GET['start_date'] ?? $defaultStartDate;
$endDate = $_GET['end_date'] ?? $defaultEndDate;

// Validate dates
if (!strtotime($startDate)) $startDate = $defaultStartDate;
if (!strtotime($endDate)) $endDate = $defaultEndDate;

$today = date('Y-m-d');
if ($startDate > $today) $startDate = $defaultStartDate;
if ($endDate > $today) $endDate = $today;
if ($startDate > $endDate) $startDate = $endDate;

// 1. Fetch totals for the selected period
$stmt_period_income = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income' AND transaction_date BETWEEN ? AND ?");
if ($stmt_period_income) {
    $stmt_period_income->bind_param("iss", $userId, $startDate, $endDate);
    $stmt_period_income->execute();
    $result_period_income = $stmt_period_income->get_result();
    $totalIncomeForPeriod = $result_period_income->fetch_assoc()['total'] ?? 0.00;
    $stmt_period_income->close();
}

$stmt_period_expenses = $conn->prepare("SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense' AND transaction_date BETWEEN ? AND ?");
if ($stmt_period_expenses) {
    $stmt_period_expenses->bind_param("iss", $userId, $startDate, $endDate);
    $stmt_period_expenses->execute();
    $result_period_expenses = $stmt_period_expenses->get_result();
    $totalExpensesForPeriod = $result_period_expenses->fetch_assoc()['total'] ?? 0.00;
    $stmt_period_expenses->close();
}

// 2. Calculate Starting Balance 
$startingBalance = 0.00;
if (strtotime($startDate) > 0) {
    $stmt_starting_balance = $conn->prepare(
        "SELECT 
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0.00) - 
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0.00) as balance
         FROM transactions 
         WHERE user_id = ? AND transaction_date < ?"
    );
    if ($stmt_starting_balance) {
        $stmt_starting_balance->bind_param("is", $userId, $startDate);
        $stmt_starting_balance->execute();
        $result_sb = $stmt_starting_balance->get_result();
        $row_sb = $result_sb->fetch_assoc();
        if ($row_sb) {
            $startingBalance = floatval($row_sb['balance']);
        }
        $stmt_starting_balance->close();
    }
}

// 3. Fetch daily transaction aggregates and calculate cumulative balance
$sql_cashflow = "SELECT DATE(transaction_date) as day,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS daily_income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS daily_expense
                FROM transactions
                WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
                GROUP BY day
                ORDER BY day ASC";

$stmt_cashflow = $conn->prepare($sql_cashflow);
if ($stmt_cashflow) {
    $stmt_cashflow->bind_param("iss", $userId, $startDate, $endDate);
    $stmt_cashflow->execute();
    $result_cashflow_data = $stmt_cashflow->get_result();
    
    $transactionsByDay = [];
    while ($row = $result_cashflow_data->fetch_assoc()) {
        $transactionsByDay[$row['day']] = [
            'income' => floatval($row['daily_income']),
            'expense' => floatval($row['daily_expense'])
        ];
    }
    $stmt_cashflow->close();

    $period = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        new DateTime($endDate . ' +1 day')
    );
    
    $runningNet = $startingBalance; 
    $accountBalanceAtEndOfPeriod = $startingBalance; 

    if (iterator_count($period) > 0) { 
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dailyIncome = 0.00;
            $dailyExpense = 0.00;

            if (isset($transactionsByDay[$dateStr])) {
                $dailyIncome = $transactionsByDay[$dateStr]['income'];
                $dailyExpense = $transactionsByDay[$dateStr]['expense'];
            }
            
            $dailyCashflow['labels'][] = date("M j", strtotime($dateStr));
            $dailyCashflow['income_data'][] = $dailyIncome;
            $dailyCashflow['expense_data'][] = $dailyExpense;
            
            $dailyNetChange = $dailyIncome - $dailyExpense;
            $runningNet += $dailyNetChange;
            $dailyCashflow['cumulative_net_data'][] = $runningNet;
        }
        if (!empty($dailyCashflow['cumulative_net_data'])) {
            $accountBalanceAtEndOfPeriod = end($dailyCashflow['cumulative_net_data']);
        }
    } else { 
        if (strtotime($startDate) <= strtotime($endDate)) {
            $dailyCashflow['labels'][] = date("M j", strtotime($startDate));
            $dailyCashflow['income_data'][] = 0;
            $dailyCashflow['expense_data'][] = 0;
            $dailyCashflow['cumulative_net_data'][] = $startingBalance;
            $accountBalanceAtEndOfPeriod = $startingBalance;
        }
    }
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
                <p class="card-text fs-4 fw-bold text-success"><?php echo format_currency(floatval($totalIncomeForPeriod), $user_currency); ?></p>
                <small class="text-muted"><?php echo date('M j, Y', strtotime($startDate)); ?> to <?php echo date('M j, Y', strtotime($endDate)); ?></small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card text-center h-100 shadow-sm">
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <i class="fas fa-arrow-down fa-3x expense-icon mb-2"></i>
                <h5 class="card-title">Total Expenses</h5> 
                <p class="card-text fs-4 fw-bold text-danger"><?php echo format_currency(floatval($totalExpensesForPeriod), $user_currency); ?></p>
                <small class="text-muted"><?php echo date('M j, Y', strtotime($startDate)); ?> to <?php echo date('M j, Y', strtotime($endDate)); ?></small>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-12 col-lg-4">
        <div class="card text-center h-100 shadow-sm">
            <div class="card-body d-flex flex-column justify-content-center align-items-center">
                <i class="fas fa-balance-scale fa-3x balance-icon mb-2"></i>
                <h5 class="card-title">Account Balance</h5>
                <p class="card-text fs-4 fw-bold <?php echo ($accountBalanceAtEndOfPeriod >= 0) ? 'text-success' : 'text-danger'; ?>">
                    <?php echo format_currency(floatval($accountBalanceAtEndOfPeriod), $user_currency); ?>
                </p>
                <small class="text-muted">As of <?php echo date('M j, Y', strtotime($endDate)); ?></small>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-8 mb-4 mb-lg-0">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center flex-wrap py-2">
                <h5 class="mb-2 mb-md-0"><i class="fas fa-chart-line me-1"></i>Daily Cashflow</h5> 
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
                <div style="height: 350px;">
                    <canvas id="dailyCashflowChart"></canvas>
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

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('dailyCashflowChart');
    const userCurrency = <?php echo json_encode($user_currency); ?>;
    
    function formatCurrency(amount, currency) {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2
        }).format(amount);
    }

    // Colors for Daily Income and Expenses lines
    const incomeLineColor = '#28a745';
    const incomeFillColor = 'rgba(40, 167, 69, 0.1)';
    const expenseLineColor = '#dc3545';
    const expenseFillColor = 'rgba(220, 53, 69, 0.1)';
    
    // Color for Account Balance Line
    const accountBalanceLineColor = 'rgb(0, 123, 255)'; // Bootstrap Primary Blue

    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dailyCashflow['labels']); ?>,
                datasets: [
                    {
                        label: 'Daily Income',
                        data: <?php echo json_encode($dailyCashflow['income_data']); ?>,
                        borderColor: incomeLineColor,
                        backgroundColor: incomeFillColor,
                        tension: 0.1,
                        fill: true,
                        pointBackgroundColor: incomeLineColor,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Daily Expenses',
                        data: <?php echo json_encode($dailyCashflow['expense_data']); ?>,
                        borderColor: expenseLineColor,
                        backgroundColor: expenseFillColor,
                        tension: 0.1,
                        fill: true,
                        pointBackgroundColor: expenseLineColor,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Account Balance', 
                        data: <?php echo json_encode($dailyCashflow['cumulative_net_data']); ?>,
                        borderColor: accountBalanceLineColor, // Consistent Blue color
                        backgroundColor: 'transparent',       // No fill
                        borderWidth: 2.5,
                        borderDash: [5, 5],                   // Dashed line style ("half-half")
                        tension: 0.1,
                        fill: false,
                        // Removed 'segment' and dynamic 'pointBackgroundColor'
                        pointBackgroundColor: accountBalanceLineColor, // Points also Blue
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1,
                        pointRadius: 3.5,
                        pointHoverRadius: 5.5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value, userCurrency);
                            }
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

    // Date validation script
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('start_date').max = today;
    document.getElementById('end_date').max = today;
    
    document.querySelector('form[method="get"]').addEventListener('submit', function(e) {
        const startInput = document.getElementById('start_date');
        const endInput = document.getElementById('end_date');
        
        if (!startInput.value || !endInput.value) {
            alert('Please select both a start and end date.');
            e.preventDefault();
            return;
        }
        const start = new Date(startInput.value);
        const end = new Date(endInput.value);

        if (start > end) {
            alert('End date must be on or after start date.');
            e.preventDefault();
            return;
        }
        
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
        if (diffDays > 366) { 
            alert('Please select a date range of 1 year or less for optimal performance.');
            e.preventDefault();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
