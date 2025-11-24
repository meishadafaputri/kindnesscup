<?php
// report.php - simple donation reporting page
// Shows donations from DonationsWeb and (if exists) Donations table
// Supports date filtering and CSV export via GET params: start_date, end_date, export=csv

$dbHost = '127.0.0.1';
$dbName = 'kindnesscup';
$dbUser = 'root';
$dbPass = '';

try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $ex) {
    die('DB connection failed: ' . htmlspecialchars($ex->getMessage()));
}

// Read filters
$start = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
$end = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : null;
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

// Build WHERE clauses for date filtering (applies to donation_date)
$where = '';
$params = [];
if ($start) {
    $where .= ' WHERE donation_date >= :start';
    $params[':start'] = $start . ' 00:00:00';
}
if ($end) {
    if ($where === '') {
        $where .= ' WHERE donation_date <= :end';
    } else {
        $where .= ' AND donation_date <= :end';
    }
    $params[':end'] = $end . ' 23:59:59';
}

// We'll select normalized columns from both tables and union them.
$selectWeb = "SELECT donation_id AS id, donor_name, donor_email, amount, donation_date, payment_method, frequency, is_anonymous, 'DonationsWeb' AS source FROM DonationsWeb";
$selectDon = "SELECT donation_id AS id, donor_name, donor_email, amount, donation_date, payment_method, NULL AS frequency, is_anonymous, 'Donations' AS source FROM Donations";

// Check if Donations table exists
$hasDonations = false;
try {
    $res = $pdo->query("SHOW TABLES LIKE 'Donations'");
    if ($res && $res->rowCount() > 0) {
        $hasDonations = true;
    }
} catch (PDOException $e) {
    // ignore
}

$queries = [$selectWeb];
if ($hasDonations) $queries[] = $selectDon;

$sql = implode(' UNION ALL ', $queries) . $where . ' ORDER BY donation_date DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $ex) {
    die('Query error: ' . htmlspecialchars($ex->getMessage()));
}

// Totals
$totalAmount = 0.0;
foreach ($rows as $r) {
    $totalAmount += (float) $r['amount'];
}

if ($export) {
    // Send CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=donations_report.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','donor_name','donor_email','amount','donation_date','payment_method','frequency','is_anonymous','source']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'],$r['donor_name'],$r['donor_email'],$r['amount'],$r['donation_date'],$r['payment_method'],$r['frequency'],$r['is_anonymous'],$r['source']]);
    }
    fclose($out);
    exit;
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donations Report</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/templatemo-kind-heart-charity.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Donations Report</h2>
        <div>
            <a class="btn btn-sm btn-outline-secondary" href="report.php">Refresh</a>
            <a class="btn btn-sm btn-outline-primary" href="report.php?export=csv">Export CSV (all)</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2" method="get" action="report.php">
                <div class="col-auto">
                    <label class="form-label">Start date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start ?? ''); ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label">End date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end ?? ''); ?>">
                </div>
                <div class="col-auto align-self-end">
                    <button class="btn btn-primary" type="submit">Filter</button>
                </div>
                <div class="col-auto align-self-end">
                    <a class="btn btn-outline-primary" href="report.php?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>">Export CSV</a>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <strong>Total donations:</strong> <?php echo count($rows); ?>
        &nbsp;&nbsp; <strong>Total amount:</strong> $<?php echo number_format($totalAmount,2); ?>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Donor</th>
                    <th>Email</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Payment</th>
                    <th>Frequency</th>
                    <th>Anon</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['id']); ?></td>
                        <td><?php echo htmlspecialchars($r['donor_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['donor_email']); ?></td>
                        <td>$<?php echo number_format($r['amount'],2); ?></td>
                        <td><?php echo htmlspecialchars($r['donation_date']); ?></td>
                        <td><?php echo htmlspecialchars($r['payment_method']); ?></td>
                        <td><?php echo htmlspecialchars($r['frequency']); ?></td>
                        <td><?php echo $r['is_anonymous'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo htmlspecialchars($r['source']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted small">Note: This page is not authenticated. Protect it before publishing.</p>
</div>

</body>
</html>
