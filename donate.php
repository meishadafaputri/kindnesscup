<?php
// donate.php - receive donation form, store into DonationsWeb table
// Assumptions: MySQL on localhost, DB name `kindnesscup`, user `root`, empty password.
// Adjust DSN credentials below if your setup differs.

session_start();
$message = '';

// DB connection params - adjust as needed
$dbHost = '127.0.0.1';
$dbName = 'kindnesscup';
$dbUser = 'root';
$dbPass = '';

$pdo = null;
$hasCauses = false;
try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    // detect Causes table
    try {
        $hasCauses = $pdo->query("SHOW TABLES LIKE 'Causes'")->rowCount() > 0;
    } catch (PDOException $e) {
        $hasCauses = false;
    }
} catch (PDOException $ex) {
    // leave $pdo null; form will still render but without causes
    $pdo = null;
}

// fetch causes for the form (if available)
$causes = [];
if ($pdo && $hasCauses) {
    try {
        $stmtCauses = $pdo->query("SELECT cause_id, title FROM Causes WHERE is_active=1 ORDER BY title ASC");
        $causes = $stmtCauses->fetchAll();
    } catch (PDOException $e) {
        $causes = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simple sanitization and extraction
    $donation_frequency = isset($_POST['DonationFrequency']) ? trim($_POST['DonationFrequency']) : 'one_time';
    $selected_amount = isset($_POST['flexRadioDefault']) ? trim($_POST['flexRadioDefault']) : '';
    $custom_amount = isset($_POST['custom_amount']) ? trim($_POST['custom_amount']) : '';

    // choose amount: prefer custom if provided and numeric
    $amount = 0.00;
    if ($custom_amount !== '' && is_numeric(str_replace(',', '', $custom_amount))) {
        $amount = (float) str_replace(',', '', $custom_amount);
    } elseif ($selected_amount !== '' && is_numeric($selected_amount)) {
        $amount = (float) $selected_amount;
    }

    $donor_name = isset($_POST['donation-name']) ? trim($_POST['donation-name']) : '';
    $donor_email = isset($_POST['donation-email']) ? trim($_POST['donation-email']) : '';
    $payment_method = isset($_POST['DonationPayment']) ? trim($_POST['DonationPayment']) : '';
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    // Basic validation
    $errors = [];
    if ($amount <= 0) {
        $errors[] = 'Please provide a valid donation amount.';
    }
    if (!$is_anonymous) {
        if ($donor_name === '') {
            $errors[] = 'Please provide your name or choose to donate anonymously.';
        }
        if ($donor_email === '' || !filter_var($donor_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }
    }
    if ($payment_method === '') {
        $errors[] = 'Please choose a payment method.';
    }

    // Validate cause_id if provided
    $cause_id = null;
    if (isset($_POST['cause_id']) && $_POST['cause_id'] !== '') {
        $cid = $_POST['cause_id'];
        if (is_numeric($cid)) {
            $cid = (int) $cid;
            if ($pdo && $hasCauses) {
                try {
                    $c = $pdo->prepare('SELECT COUNT(*) FROM Causes WHERE cause_id = :id AND is_active = 1');
                    $c->execute([':id' => $cid]);
                    if ($c->fetchColumn() > 0) {
                        $cause_id = $cid;
                    }
                } catch (PDOException $e) {
                    // ignore
                }
            }
        }
    }

    if (empty($errors)) {
        // DB connection params - adjust as needed
        $dbHost = '127.0.0.1';
        $dbName = 'kindnesscup';
        $dbUser = 'root';
        $dbPass = '';

        try {
            if (!$pdo) {
                // try to create a connection if it wasn't opened earlier
                $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            }

            // Ensure `Donations` table exists. If Causes table exists, include FK.
            if ($hasCauses) {
                $createDonationsSql = "CREATE TABLE IF NOT EXISTS `Donations` (
                    `donation_id` INT(11) NOT NULL AUTO_INCREMENT,
                    `cause_id` INT(11) NULL,
                    `donor_name` VARCHAR(100) DEFAULT 'Anonim',
                    `donor_email` VARCHAR(100) DEFAULT NULL,
                    `amount` DECIMAL(10,2) NOT NULL,
                    `donation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `payment_method` VARCHAR(50) NOT NULL,
                    `frequency` VARCHAR(50) DEFAULT NULL,
                    `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`donation_id`),
                    INDEX (`cause_id`),
                    CONSTRAINT `fk_donations_causes` FOREIGN KEY (`cause_id`) REFERENCES `Causes`(`cause_id`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            } else {
                $createDonationsSql = "CREATE TABLE IF NOT EXISTS `Donations` (
                    `donation_id` INT(11) NOT NULL AUTO_INCREMENT,
                    `cause_id` INT(11) NULL,
                    `donor_name` VARCHAR(100) DEFAULT 'Anonim',
                    `donor_email` VARCHAR(100) DEFAULT NULL,
                    `amount` DECIMAL(10,2) NOT NULL,
                    `donation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `payment_method` VARCHAR(50) NOT NULL,
                    `frequency` VARCHAR(50) DEFAULT NULL,
                    `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`donation_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            }
            $pdo->exec($createDonationsSql);

            // If older DonationsWeb table exists and Donations is empty, migrate data.
            try {
                $hasWeb = $pdo->query("SHOW TABLES LIKE 'DonationsWeb'")->rowCount() > 0;
            } catch (PDOException $e) {
                $hasWeb = false;
            }
            if ($hasWeb) {
                try {
                    $countDon = $pdo->query("SELECT COUNT(*) AS c FROM Donations")->fetchColumn();
                    if ($countDon == 0) {
                        $migrateSql = "INSERT INTO Donations (cause_id, donor_name, donor_email, amount, donation_date, payment_method, frequency, is_anonymous)
                                       SELECT NULL, donor_name, donor_email, amount, donation_date, payment_method, frequency, is_anonymous FROM DonationsWeb";
                        $pdo->exec($migrateSql);
                    }
                } catch (PDOException $e) {
                    // ignore migration errors
                }
            }

            $insertSql = "INSERT INTO Donations (cause_id, donor_name, donor_email, amount, payment_method, frequency, is_anonymous)
                          VALUES (:cause_id, :donor_name, :donor_email, :amount, :payment_method, :frequency, :is_anonymous)";
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                ':cause_id' => $cause_id,
                ':donor_name' => $is_anonymous ? 'Anonim' : $donor_name,
                ':donor_email' => $is_anonymous ? null : $donor_email,
                ':amount' => $amount,
                ':payment_method' => $payment_method,
                ':frequency' => $donation_frequency,
                ':is_anonymous' => $is_anonymous
            ]);

            $message = 'Thank you — your donation was recorded successfully.';

            // Optionally make $message show donation amount
            $message .= ' Amount: $' . number_format($amount, 2);
        } catch (PDOException $ex) {
            $errors[] = 'Database error: ' . $ex->getMessage();
        }
    }

    if (!empty($errors)) {
        $message = implode('<br>', $errors);
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kindnesscup - Donation</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.css" rel="stylesheet">
    <link href="css/templatemo-kind-heart-charity.css" rel="stylesheet">
</head>

<body>

    <header class="site-header">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 col-12 d-flex flex-wrap">
                    <p class="d-flex me-4 mb-0">
                        <i class="bi-geo-alt me-2"></i>
                        Bandung, Indonesia
                    </p>
                    <p class="d-flex mb-0">
                        <i class="bi-envelope me-2"></i>
                        <a href="mailto:info@company.com">info@company.com</a>
                    </p>
                </div>
                <div class="col-lg-3 col-12 ms-auto d-lg-block d-none">
                    <ul class="social-icon">
                        <li class="social-icon-item"><a href="#" class="social-icon-link bi-twitter"></a></li>
                        <li class="social-icon-item"><a href="#" class="social-icon-link bi-facebook"></a></li>
                        <li class="social-icon-item"><a href="#" class="social-icon-link bi-instagram"></a></li>
                        <li class="social-icon-item"><a href="#" class="social-icon-link bi-youtube"></a></li>
                        <li class="social-icon-item"><a href="#" class="social-icon-link bi-whatsapp"></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <nav class="navbar navbar-expand-lg bg-light shadow-lg">
        <div class="container">
            <a class="navbar-brand" href="index.html">
                <img src="images/logo.png" class="logo img-fluid" alt="">
                <span>Kindnesscup<small>Non-profit Organization</small></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link click-scroll" href="index.html#section_1">Home</a></li>
                    <li class="nav-item"><a class="nav-link click-scroll" href="index.html#section_2">About</a></li>
                    <li class="nav-item"><a class="nav-link click-scroll" href="index.html#section_3">Causes</a></li>
                    <li class="nav-item"><a class="nav-link click-scroll" href="index.html#section_4">Volunteer</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link click-scroll dropdown-toggle" href="index.html#section_5"
                            id="navbarLightDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">News</a>
                        <ul class="dropdown-menu dropdown-menu-light" aria-labelledby="navbarLightDropdownMenuLink">
                            <li><a class="dropdown-item" href="news.html">News Listing</a></li>
                            <li><a class="dropdown-item" href="news-detail.html">News Detail</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link click-scroll" href="index.html#section_6">Contact</a></li>
                    <li class="nav-item ms-2"><a class="nav-link" href="report.php">Reports</a></li>
                    <li class="nav-item ms-3"><a class="nav-link custom-btn custom-border-btn btn" href="donate.php">Donate</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main>

        <section class="donate-section">
            <div class="section-overlay"></div>
            <div class="container">
                <div class="row">
                    <div class="col-lg-6 col-12 mx-auto">
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <form class="custom-form donate-form" action="donate.php" method="post" role="form">
                            <h3 class="mb-4">Make a donation</h3>

                            <div class="row">
                                <div class="col-lg-12 col-12">
                                    <h5 class="mb-3">Donation Frequency</h5>
                                </div>

                                <div class="col-lg-6 col-6 form-check-group form-check-group-donation-frequency">
                                    <div class="form-check form-check-radio">
                                        <input class="form-check-input" type="radio" name="DonationFrequency"
                                            id="DonationFrequencyOne" value="one_time" checked>
                                        <label class="form-check-label" for="DonationFrequencyOne">One Time</label>
                                    </div>
                                </div>

                                <div class="col-lg-6 col-6 form-check-group form-check-group-donation-frequency">
                                    <div class="form-check form-check-radio">
                                        <input class="form-check-input" type="radio" name="DonationFrequency"
                                            id="DonationFrequencyMonthly" value="monthly">
                                        <label class="form-check-label" for="DonationFrequencyMonthly">Monthly</label>
                                    </div>
                                </div>

                                <div class="col-lg-12 col-12">
                                    <h5 class="mt-2 mb-3">Select an amount</h5>
                                </div>

                                <div class="col-lg-3 col-md-6 col-6 form-check-group">
                                    <div class="form-check form-check-radio">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault"
                                            id="flexRadioDefault1" value="10">
                                        <label class="form-check-label" for="flexRadioDefault1">$10</label>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 col-6 form-check-group">
                                    <div class="form-check form-check-radio">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault"
                                            id="flexRadioDefault2" value="15">
                                        <label class="form-check-label" for="flexRadioDefault2">$15</label>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 col-6 form-check-group">
                                    <div class="form-check form-check-radio">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault"
                                            id="flexRadioDefault3" value="20">
                                        <label class="form-check-label" for="flexRadioDefault3">$20</label>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 col-6 form-check-group">
                                    <div class="form-check form-check-radio">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault"
                                            id="flexRadioDefault4" value="30">
                                        <label class="form-check-label" for="flexRadioDefault4">$30</label>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 col-6 form-check-group">
                                    <div class="form-check form-check-radio">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault"
                                            id="flexRadioDefault5" value="45">
                                        <label class="form-check-label" for="flexRadioDefault5">$45</label>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-6 col-6 form-check-group">
                                    <div class="form-check form-check-radio">
                                        <input class="form-check-input" type="radio" name="flexRadioDefault"
                                            id="flexRadioDefault6" value="50">
                                        <label class="form-check-label" for="flexRadioDefault6">$50</label>
                                    </div>
                                </div>

                                <div class="col-lg-6 col-12 form-check-group">
                                    <div class="input-group">
                                        <span class="input-group-text" id="basic-addon1">$</span>
                                        <input type="text" name="custom_amount" class="form-control" placeholder="Custom amount"
                                            aria-label="Custom amount" aria-describedby="basic-addon1">
                                    </div>
                                </div>

                                <div class="col-lg-12 col-12">
                                    <h5 class="mt-1">Personal Info</h5>
                                </div>

                                <div class="col-lg-12 col-12 mt-2">
                                    <label for="cause_id" class="form-label">Cause (optional)</label>
                                    <select name="cause_id" id="cause_id" class="form-select">
                                        <option value="">-- Select a cause (optional) --</option>
                                        <?php foreach ($causes as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c['cause_id']); ?>"><?php echo htmlspecialchars($c['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-lg-6 col-12 mt-2">
                                    <input type="text" name="donation-name" id="donation-name" class="form-control"
                                        placeholder="Jack Doe" required>
                                </div>

                                <div class="col-lg-6 col-12 mt-2">
                                    <input type="email" name="donation-email" id="donation-email"
                                        pattern="[^ @]*@[^ @]*" class="form-control" placeholder="Jackdoe@gmail.com" required>
                                </div>

                                <div class="col-lg-12 col-12">
                                    <h5 class="mt-4 pt-1">Choose Payment</h5>
                                </div>

                                <div class="col-lg-12 col-12 mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="DonationPayment"
                                            id="flexRadioDefault9" value="card">
                                        <label class="form-check-label" for="flexRadioDefault9">
                                            <i class="bi-credit-card custom-icon ms-1"></i> Debit or Credit card
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="DonationPayment"
                                            id="flexRadioDefault10" value="paypal">
                                        <label class="form-check-label" for="flexRadioDefault10">
                                            <i class="bi-paypal custom-icon ms-1"></i> Paypal
                                        </label>
                                    </div>

                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" name="is_anonymous" id="is_anonymous" value="1">
                                        <label class="form-check-label" for="is_anonymous">Donate anonymously</label>
                                    </div>

                                    <button type="submit" class="form-control mt-4">Submit Donation</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-12 mb-4">
                    <img src="images/logo.png" class="logo img-fluid" alt="">
                </div>
                <div class="col-lg-4 col-md-6 col-12 mb-4">
                    <h5 class="site-footer-title mb-3">Quick Links</h5>
                    <ul class="footer-menu">
                        <li class="footer-menu-item"><a href="#" class="footer-menu-link">Our Story</a></li>
                        <li class="footer-menu-item"><a href="#" class="footer-menu-link">Newsroom</a></li>
                        <li class="footer-menu-item"><a href="#" class="footer-menu-link">Causes</a></li>
                        <li class="footer-menu-item"><a href="#" class="footer-menu-link">Become a volunteer</a></li>
                        <li class="footer-menu-item"><a href="#" class="footer-menu-link">Partner with us</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-6 col-12 mx-auto">
                    <h5 class="site-footer-title mb-3">Contact Infomation</h5>
                    <p class="text-white d-flex mb-2"><i class="bi-telephone me-2"></i>
                        <a href="tel: 305-240-9671" class="site-footer-link">120-240-9600</a>
                    </p>
                    <p class="text-white d-flex"><i class="bi-envelope me-2"></i>
                        <a href="mailto:kindnesscup@gmail.com" class="site-footer-link">kindnesscup@gmail.com</a>
                    </p>
                    <p class="text-white d-flex mt-3"><i class="bi-geo-alt me-2"></i>Bandung, Indonesia</p>
                    <a href="#" class="custom-btn btn mt-3">Get Direction</a>
                </div>
            </div>
        </div>
        <div class="site-footer-bottom">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6 col-md-7 col-12">
                        <p class="copyright-text mb-0">Copyright © 2036 <a href="#">Kind Heart</a> Charity Org.
                            Design: <a href="https://templatemo.com" target="_blank">TemplateMo</a><br>Distribution:
                            <a href="https://themewagon.com">ThemeWagon</a>
                        </p>
                    </div>
                    <div class="col-lg-6 col-md-5 col-12 d-flex justify-content-center align-items-center mx-auto">
                        <ul class="social-icon">
                            <li class="social-icon-item"><a href="#" class="social-icon-link bi-twitter"></a></li>
                            <li class="social-icon-item"><a href="#" class="social-icon-link bi-facebook"></a></li>
                            <li class="social-icon-item"><a href="#" class="social-icon-link bi-instagram"></a></li>
                            <li class="social-icon-item"><a href="#" class="social-icon-link bi-linkedin"></a></li>
                            <li class="social-icon-item"><a href="https://youtube.com/templatemo" class="social-icon-link bi-youtube"></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.sticky.js"></script>
    <script src="js/counter.js"></script>
    <script src="js/custom.js"></script>
</body>

</html>
