<?php

namespace App\Services;

use App\Models\AcosysConnection;
use PDO;
use PDOException;
use Throwable;

class AcosysConnector
{
    /**
     * Test connection credentials.
     */
    public function testConnection(string $host, int $port, string $database, string $username, string $password): array
    {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 6,
            ]);

            // Query check
            $version = $pdo->query("SELECT VERSION()")->fetchColumn();
            
            // Check necessary tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $requiredTables = ['customer', 'sales', 'salesdetail', 'salespayments'];
            $missing = array_diff($requiredTables, $tables);

            if (!empty($missing)) {
                return [
                    'status' => false,
                    'message' => "Terhubung ke database, namun tabel Acosys tidak lengkap (kurang: " . implode(', ', $missing) . ").",
                    'version' => $version,
                ];
            }

            $customerCount = (int) $pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn();
            $companyName = (string) ($pdo->query("SELECT name FROM company LIMIT 1")->fetchColumn() ?: 'Acosys');

            // Detect Acosys version (V8 vs V4)
            $detectedVersion = 'v4';
            $acosysVersionLabel = 'Acosys V4';
            if (in_array('version', $tables)) {
                try {
                    $v8Ver = $pdo->query("SELECT version FROM version LIMIT 1")->fetchColumn();
                    if ($v8Ver) {
                        $detectedVersion = 'v8';
                        $acosysVersionLabel = "Acosys V{$v8Ver}";
                    }
                } catch (\Throwable $e) {}
            }

            return [
                'status' => true,
                'message' => "Koneksi berhasil! Terhubung ke {$companyName} ({$customerCount} pelanggan, {$acosysVersionLabel}, DBMS {$version}).",
                'version' => $version,
                'detected_version' => $detectedVersion,
                'acosys_version_label' => $acosysVersionLabel,
                'company_name' => $companyName,
                'customer_count' => $customerCount,
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'message' => "Koneksi gagal: " . $e->getMessage(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => false,
                'message' => "Kesalahan: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Get an open PDO instance for a stored connection.
     */
    public function getPdo(AcosysConnection $connection): PDO
    {
        $dsn = "mysql:host={$connection->host};port={$connection->port};dbname={$connection->database};charset=utf8mb4";
        return new PDO($dsn, $connection->username, $connection->decrypted_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 8,
        ]);
    }

    /**
     * Get company details (name, phone, address).
     */
    public function getCompanyInfo(AcosysConnection $connection): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->query("SELECT name, address, telephone, email, motto FROM company LIMIT 1");
            return $stmt->fetch() ?: ['name' => 'Toko Saya', 'telephone' => ''];
        } catch (Throwable $e) {
            return ['name' => $connection->label, 'telephone' => ''];
        }
    }

    /**
     * Fetch list of customers with unpaid receivables.
     * Uses the exact tested SQL logic from monitor_bisnis / get_piutang_owner_light.php
     */
    public function getReceivables(AcosysConnection $connection, array $filters = []): array
    {
        $pdo = $this->getPdo($connection);

        $sql = "
            SELECT
                c.id   AS customer_id,
                c.name AS customer_name,
                c.address,
                c.mobilephone,
                c.telephone,
                c.creditlimit AS credit_limit,

                COALESCE(b.total_belanja, 0) AS total_belanja,
                COALESCE(p.total_bayar, 0)   AS total_bayar,

                (COALESCE(b.total_belanja, 0) - COALESCE(p.total_bayar, 0)) AS total_piutang,

                -- Jumlah nota overdue (jatuh tempo sudah lewat hari ini)
                COALESCE((
                    SELECT COUNT(*)
                    FROM sales s2
                    WHERE s2.customerid = c.id
                      AND s2.kind IN (0, 2)
                      AND s2.paidinfull = 0
                      AND DATE_ADD(s2.salesdate, INTERVAL s2.duedays DAY) <= CURDATE()
                ), 0) AS overdue_count,

                -- Jumlah seluruh nota belum lunas
                COALESCE((
                    SELECT COUNT(*)
                    FROM sales s3
                    WHERE s3.customerid = c.id
                      AND s3.kind IN (0, 2)
                      AND s3.paidinfull = 0
                ), 0) AS unpaid_count,

                -- Tanggal jatuh tempo terdekat
                (
                    SELECT MIN(DATE_ADD(s4.salesdate, INTERVAL s4.duedays DAY))
                    FROM sales s4
                    WHERE s4.customerid = c.id
                      AND s4.kind IN (0, 2)
                      AND s4.paidinfull = 0
                ) AS earliest_due_date

            FROM customer c

            LEFT JOIN (
                SELECT customerid, SUM(amount) AS total_belanja
                FROM (
                    SELECT s.customerid, sd.netamount AS amount
                    FROM sales s
                    JOIN salesdetail sd ON sd.salesid = s.salesid
                    WHERE s.kind IN (0, 2) AND s.paidinfull = 0
                    
                    UNION ALL
                    
                    SELECT s.customerid, (se.debit - se.credit) AS amount
                    FROM sales s
                    JOIN salesexpensesdetail se ON se.salesid = s.salesid
                    WHERE s.kind IN (0, 2) AND s.paidinfull = 0
                ) as combined
                GROUP BY customerid
            ) b ON b.customerid = c.id

            LEFT JOIN (
                SELECT s.customerid, SUM(sp.debit) AS total_bayar
                FROM salespayments sp
                JOIN sales s ON sp.salesidref = s.salesid
                INNER JOIN receivablepayment rp ON sp.salesid = rp.transid
                WHERE rp.accepted = 1 AND s.paidinfull = 0 AND s.kind IN (0, 2)
                GROUP BY s.customerid
            ) p ON p.customerid = c.id

            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE :search1 OR c.id LIKE :search2) ";
            $params['search1'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['customer_id'])) {
            $sql .= " AND c.id = :cust_id ";
            $params['cust_id'] = $filters['customer_id'];
        }

        $sql .= " HAVING total_piutang > 0 ";

        if (!empty($filters['overdue_only'])) {
            $sql .= " AND overdue_count > 0 ";
        }

        $sql .= " ORDER BY total_piutang DESC, customer_name ASC ";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Get phone overrides & opt-out from SaaS database
        $contacts = $connection->reminderContacts()->get()->keyBy('customer_id');

        return array_map(function ($row) use ($contacts) {
            $override = $contacts->get($row['customer_id']);
            $finalPhone = $override?->phone_override ?: ($row['mobilephone'] ?: $row['telephone']);
            
            // Format phone to international 62
            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$finalPhone);
            if (str_starts_with($cleanPhone, '08')) {
                $cleanPhone = '628' . substr($cleanPhone, 2);
            }

            return [
                'customer_id'    => (string)$row['customer_id'],
                'customer_name'  => (string)$row['customer_name'],
                'address'        => (string)($row['address'] ?? '-'),
                'raw_phone'      => (string)($row['mobilephone'] ?: $row['telephone'] ?: '-'),
                'phone'          => $cleanPhone,
                'has_override'   => !empty($override?->phone_override),
                'opt_out'        => (bool)($override?->opt_out ?? false),
                'credit_limit'   => (float)$row['credit_limit'],
                'total_belanja'  => (float)$row['total_belanja'],
                'total_bayar'    => (float)$row['total_bayar'],
                'total_piutang'  => (float)$row['total_piutang'],
                'unpaid_count'   => (int)$row['unpaid_count'],
                'overdue_count'  => (int)$row['overdue_count'],
                'earliest_due_date' => $row['earliest_due_date'],
            ];
        }, $rows);
    }

    /**
     * Get invoices list for a single customer.
     */
    public function getCustomerInvoices(AcosysConnection $connection, string $customerId): array
    {
        $pdo = $this->getPdo($connection);

        $sql = "
            SELECT 
                s.salesid, 
                s.salesdate, 
                s.duedays,
                DATE_ADD(s.salesdate, INTERVAL s.duedays DAY) as duedate,
                (
                  COALESCE((SELECT SUM(sd.netamount) FROM salesdetail sd WHERE sd.salesid = s.salesid), 0) + 
                  COALESCE((SELECT SUM(se.debit - se.credit) FROM salesexpensesdetail se WHERE se.salesid = s.salesid), 0)
                ) as total_faktur,
                COALESCE((
                    SELECT SUM(sp.debit) 
                    FROM salespayments sp 
                    INNER JOIN receivablepayment rp ON sp.salesid = rp.transid
                    WHERE sp.salesidref = s.salesid AND rp.accepted = 1
                ), 0) as total_dibayar
            FROM sales s
            WHERE s.customerid = :customer_id
              AND s.kind IN (0, 2) 
              AND s.paidinfull = 0
            HAVING (total_faktur - total_dibayar) > 0
            ORDER BY s.salesdate ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['customer_id' => $customerId]);
        $rows = $stmt->fetchAll();

        return array_map(function ($row) {
            $totalFaktur = (float)$row['total_faktur'];
            $totalDibayar = (float)$row['total_dibayar'];
            $sisaTagihan = $totalFaktur - $totalDibayar;
            $dueDate = $row['duedate'];
            $isOverdue = strtotime($dueDate) < strtotime(date('Y-m-d'));

            return [
                'salesid'       => $row['salesid'],
                'salesdate'     => $row['salesdate'],
                'duedays'       => (int)$row['duedays'],
                'duedate'       => $dueDate,
                'total_faktur'  => $totalFaktur,
                'total_dibayar' => $totalDibayar,
                'sisa_tagihan'  => $sisaTagihan,
                'is_overdue'    => $isOverdue,
            ];
        }, $rows);
    }

    /**
     * Authenticate a user against Acosys V8 users table.
     */
    public function authenticateV8User(AcosysConnection $connection, string $username, string $password): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->prepare("
                SELECT id, name, userpassword, isactive, roles 
                FROM users 
                WHERE id = :id 
                LIMIT 1
            ");
            $stmt->execute(['id' => $username]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['status' => false, 'message' => "Pengguna '$username' tidak ditemukan di database Acosys."];
            }

            if (empty($user['isactive'])) {
                return ['status' => false, 'message' => "Akun kasir '$username' sedang nonaktif."];
            }

            $hash = $user['userpassword'] ?? '';
            $isValid = false;

            // 1. Cek Bcrypt standard ($2y$, $2a$)
            if (str_starts_with($hash, '$2')) {
                $isValid = password_verify($password, $hash);
            } elseif ($hash === $password || md5($password) === $hash) {
                // 2. Fallback legacy / plain
                $isValid = true;
            }

            if (!$isValid) {
                return ['status' => false, 'message' => 'Password yang Anda masukkan salah.'];
            }

            return [
                'status' => true,
                'message' => 'Autentikasi berhasil!',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'] ?: $user['id'],
                    'roles' => $user['roles'] ?: 'User',
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => false, 'message' => 'Kesalahan koneksi database Acosys: ' . $e->getMessage()];
        }
    }

    /**
     * Get payment accounts from paymenttype with their real-time balance from journaltrans.
     * (Hanya paymentacc yang ada di tabel paymenttype Acosys V8)
     */
    public function getPaymentAccountsWithBalance(AcosysConnection $connection): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $sql = "
                SELECT DISTINCT
                    pt.paymentacc as account_id,
                    a.name as account_name,
                    GROUP_CONCAT(DISTINCT pt.name SEPARATOR ' / ') as payment_types
                FROM paymenttype pt
                JOIN account a ON pt.paymentacc = a.id
                WHERE pt.isactive = 1
                GROUP BY pt.paymentacc, a.name
                ORDER BY pt.paymentacc ASC
            ";
            $accounts = $pdo->query($sql)->fetchAll();

            $results = [];
            foreach ($accounts as $acc) {
                $stmt = $pdo->prepare("
                    SELECT 
                        (COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) as saldo
                    FROM journaltrans 
                    WHERE accountid = :account_id
                ");
                $stmt->execute(['account_id' => $acc['account_id']]);
                $saldo = (float)$stmt->fetchColumn();

                $results[] = [
                    'account_id' => $acc['account_id'],
                    'account_name' => $acc['account_name'],
                    'payment_types' => $acc['payment_types'],
                    'saldo' => $saldo,
                    'formatted_saldo' => 'Rp ' . number_format($saldo, 0, ',', '.'),
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get single account balance info.
     */
    public function getSingleAccountBalance(AcosysConnection $connection, string $accountId): ?array
    {
        try {
            $pdo = $this->getPdo($connection);
            $accStmt = $pdo->prepare("SELECT id, name FROM account WHERE id = :id LIMIT 1");
            $accStmt->execute(['id' => $accountId]);
            $acc = $accStmt->fetch();

            if (!$acc) return null;

            $balStmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(debit), 0) as total_debit,
                    COALESCE(SUM(credit), 0) as total_credit,
                    (COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) as saldo
                FROM journaltrans 
                WHERE accountid = :account_id
            ");
            $balStmt->execute(['account_id' => $accountId]);
            $bal = $balStmt->fetch();

            return [
                'account_id' => $acc['id'],
                'account_name' => $acc['name'],
                'total_debit' => (float)$bal['total_debit'],
                'total_credit' => (float)$bal['total_credit'],
                'saldo' => (float)$bal['saldo'],
                'formatted_saldo' => 'Rp ' . number_format((float)$bal['saldo'], 0, ',', '.'),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get list of divisions from table division in Acosys V8.
     */
    public function getDivisions(AcosysConnection $connection): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->query("SELECT id, description, frmjournalentryid, journalentryidno FROM division ORDER BY id ASC");
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get default division (store division id '11' or first division).
     */
    public function getDefaultDivision(AcosysConnection $connection): ?array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->prepare("SELECT id, description, frmjournalentryid, journalentryidno FROM division WHERE id = '11' LIMIT 1");
            $stmt->execute();
            $div = $stmt->fetch();
            if ($div) return $div;

            $stmt2 = $pdo->query("SELECT id, description, frmjournalentryid, journalentryidno FROM division ORDER BY id ASC LIMIT 1");
            return $stmt2->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Search postable accounts (isgroup = 0) in Acosys V8.
     */
    public function searchPostableAccounts(AcosysConnection $connection, string $keyword = '', int $limit = 8): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $sql = "SELECT id, name, accountgroup FROM account WHERE isgroup = 0";
            $params = [];

            if (!empty($keyword)) {
                $sql .= " AND (name LIKE :kw1 OR id LIKE :kw2)";
                $params['kw1'] = "%{$keyword}%";
                $params['kw2'] = "%{$keyword}%";
            }

            $sql .= " ORDER BY id ASC LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get popular accounts for quick inline keyboard in Bot Telegram.
     */
    public function getPopularAccounts(AcosysConnection $connection): array
    {
        try {
            $pdo = $this->getPdo($connection);
            // Ambil akun kas & bank utama + beberapa akun biaya umum yang ada
            $popularIds = ['101.001', '102.001', '102.002', '102.003', '603.011', '603.002', '105.001'];
            $inClause = "'" . implode("','", $popularIds) . "'";

            $stmt = $pdo->query("
                SELECT id, name, accountgroup 
                FROM account 
                WHERE isgroup = 0 AND id IN ($inClause)
                ORDER BY FIELD(id, $inClause)
            ");
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get single account detail by ID.
     */
    public function getAccountById(AcosysConnection $connection, string $accountId): ?array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->prepare("SELECT id, name, accountgroup, isgroup FROM account WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $accountId]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Generate next journal ID based on division format and counter.
     */
    public function getNextJournalId(AcosysConnection $connection, string $divisionId): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->prepare("SELECT id, description, frmjournalentryid, journalentryidno FROM division WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $divisionId]);
            $div = $stmt->fetch();

            if (!$div) {
                return ['jtid' => 'JT-' . time(), 'counter' => 1, 'division_id' => $divisionId, 'division_name' => ''];
            }

            $pattern = !empty($div['frmjournalentryid']) ? $div['frmjournalentryid'] : 'I/JT-######';
            $counter = (int)$div['journalentryidno'];
            $hashCount = substr_count($pattern, '#');
            if ($hashCount === 0) $hashCount = 6;

            $formattedNum = str_pad((string)$counter, $hashCount, '0', STR_PAD_LEFT);
            $jtid = str_replace(str_repeat('#', $hashCount), $formattedNum, $pattern);

            return [
                'jtid' => $jtid,
                'counter' => $counter,
                'division_id' => $div['id'],
                'division_name' => $div['description'] ?? '',
            ];
        } catch (\Throwable $e) {
            return ['jtid' => 'JT-' . time(), 'counter' => 1, 'division_id' => $divisionId, 'division_name' => ''];
        }
    }

    /**
     * Create journal transaction in Acosys V8 (atomic transaction).
     */
    public function createJournalTransaction(AcosysConnection $connection, array $data): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $pdo->beginTransaction();

            $divisionId = (string)($data['division_id'] ?? '11');
            $memo = trim((string)($data['memo'] ?? ''));
            $debitAccountId = (string)($data['debit_account_id'] ?? '');
            $debitAccountName = (string)($data['debit_account_name'] ?? '');
            $debitAmount = (float)($data['debit_amount'] ?? 0);
            $creditAccountId = (string)($data['credit_account_id'] ?? '');
            $creditAccountName = (string)($data['credit_account_name'] ?? '');
            $creditAmount = (float)($data['credit_amount'] ?? 0);
            $userId = (string)($data['user_id'] ?? $data['user_create'] ?? 'admin');

            // 1. Kunci dan ambil nomor urut division
            $stmtDiv = $pdo->prepare("SELECT id, description, frmjournalentryid, journalentryidno FROM division WHERE id = :id FOR UPDATE");
            $stmtDiv->execute(['id' => $divisionId]);
            $div = $stmtDiv->fetch();

            if (!$div) {
                // Fallback cari divisi pertama jika id tidak ditemukan
                $stmtDiv2 = $pdo->query("SELECT id, description, frmjournalentryid, journalentryidno FROM division ORDER BY id ASC LIMIT 1 FOR UPDATE");
                $div = $stmtDiv2->fetch();
                if ($div) $divisionId = $div['id'];
            }

            $pattern = !empty($div['frmjournalentryid']) ? $div['frmjournalentryid'] : 'I/JT-######';
            $counter = (int)($div['journalentryidno'] ?? 1);
            $hashCount = substr_count($pattern, '#');
            if ($hashCount === 0) $hashCount = 6;

            $formattedNum = str_pad((string)$counter, $hashCount, '0', STR_PAD_LEFT);
            $jtid = str_replace(str_repeat('#', $hashCount), $formattedNum, $pattern);

            $jtDate = date('Y-m-d');
            $jtTime = date('H:i:s');
            $nowDateTime = date('Y-m-d H:i:s');
            $journalValue = $debitAmount;

            // 2. Insert Header (tabel journal)
            $insH = $pdo->prepare("
                INSERT INTO journal (
                    jtid, jtdate, jttime, memo, memoedit, division, projectid, journalvalue,
                    printed, approved, approvalstatus, approvallevel, approvalnote, approvaluser,
                    usercreate, useredit, updatetimestamp
                ) VALUES (
                    :jtid, :jtdate, :jttime, :memo, '', :division, NULL, :journalvalue,
                    0, 1, 1, 0, '', '',
                    :usercreate, '', :updatetimestamp
                )
            ");
            $insH->execute([
                'jtid' => $jtid,
                'jtdate' => $jtDate,
                'jttime' => $jtTime,
                'memo' => mb_substr($memo, 0, 100),
                'division' => $divisionId,
                'journalvalue' => $journalValue,
                'usercreate' => mb_substr($userId, 0, 16),
                'updatetimestamp' => $nowDateTime,
            ]);

            // 3. Insert Baris Debit (tabel journaltrans)
            $insD = $pdo->prepare("
                INSERT INTO journaltrans (
                    jtid, jtdate, memo, accountid, accountname, debit, credit,
                    division, projectid, reftransaction, approved, usercreate, useredit, updatetimestamp
                ) VALUES (
                    :jtid, :jtdate, :memo, :accountid, :accountname, :debit, 0,
                    :division, NULL, '', 1, :usercreate, '', :updatetimestamp
                )
            ");
            $insD->execute([
                'jtid' => $jtid,
                'jtdate' => $nowDateTime,
                'memo' => mb_substr($memo, 0, 100),
                'accountid' => $debitAccountId,
                'accountname' => mb_substr($debitAccountName, 0, 150),
                'debit' => $debitAmount,
                'division' => $divisionId,
                'usercreate' => mb_substr($userId, 0, 16),
                'updatetimestamp' => $nowDateTime,
            ]);

            // 4. Insert Baris Kredit (tabel journaltrans)
            $insC = $pdo->prepare("
                INSERT INTO journaltrans (
                    jtid, jtdate, memo, accountid, accountname, debit, credit,
                    division, projectid, reftransaction, approved, usercreate, useredit, updatetimestamp
                ) VALUES (
                    :jtid, :jtdate, :memo, :accountid, :accountname, 0, :credit,
                    :division, NULL, '', 1, :usercreate, '', :updatetimestamp
                )
            ");
            $insC->execute([
                'jtid' => $jtid,
                'jtdate' => $nowDateTime,
                'memo' => mb_substr($memo, 0, 100),
                'accountid' => $creditAccountId,
                'accountname' => mb_substr($creditAccountName, 0, 150),
                'credit' => $creditAmount,
                'division' => $divisionId,
                'usercreate' => mb_substr($userId, 0, 16),
                'updatetimestamp' => $nowDateTime,
            ]);

            // 5. Update nomor urut counter division
            $upDiv = $pdo->prepare("UPDATE division SET journalentryidno = journalentryidno + 1 WHERE id = :id");
            $upDiv->execute(['id' => $divisionId]);

            $pdo->commit();

            return [
                'status' => true,
                'message' => 'Transaksi jurnal berhasil disimpan ke database Acosys V8.',
                'jtid' => $jtid,
                'jtdate' => $jtDate,
                'jttime' => $jtTime,
                'date' => date('d/m/Y', strtotime($jtDate)),
                'time' => date('H:i', strtotime($jtTime)),
                'memo' => $memo,
                'journalvalue' => $journalValue,
                'division' => $div['description'] ?? $divisionId,
                'division_id' => $divisionId,
                'division_name' => $div['description'] ?? '',
                'usercreate' => $userId,
            ];
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'status' => false,
                'message' => 'Gagal menyimpan transaksi jurnal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch recent journal entries for web UI monitoring.
     */
    public function getRecentJournals(AcosysConnection $connection, int $limit = 20): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->prepare("
                SELECT jtid, jtdate, jttime, memo, journalvalue, division, usercreate, updatetimestamp
                FROM journal
                ORDER BY jtdate DESC, jttime DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $journals = $stmt->fetchAll() ?: [];

            $results = [];
            foreach ($journals as $j) {
                // Ambil baris-baris debit & kreditnya
                $lStmt = $pdo->prepare("
                    SELECT accountid, accountname, debit, credit 
                    FROM journaltrans 
                    WHERE jtid = :jtid 
                    ORDER BY id ASC
                ");
                $lStmt->execute(['jtid' => $j['jtid']]);
                $lines = $lStmt->fetchAll() ?: [];

                $debitAccounts = [];
                $creditAccounts = [];
                foreach ($lines as $line) {
                    if ($line['debit'] > 0) {
                        $debitAccounts[] = "[{$line['accountid']}] {$line['accountname']} (Rp " . number_format($line['debit'], 0, ',', '.') . ")";
                    }
                    if ($line['credit'] > 0) {
                        $creditAccounts[] = "[{$line['accountid']}] {$line['accountname']} (Rp " . number_format($line['credit'], 0, ',', '.') . ")";
                    }
                }

                $results[] = [
                    'jtid' => $j['jtid'],
                    'jtdate' => $j['jtdate'],
                    'jttime' => $j['jttime'],
                    'memo' => $j['memo'] ?: '-',
                    'journalvalue' => (float)$j['journalvalue'],
                    'formatted_value' => 'Rp ' . number_format((float)$j['journalvalue'], 0, ',', '.'),
                    'division' => $j['division'],
                    'usercreate' => $j['usercreate'],
                    'debit_summary' => !empty($debitAccounts) ? implode(', ', $debitAccounts) : '-',
                    'credit_summary' => !empty($creditAccounts) ? implode(', ', $creditAccounts) : '-',
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Search products by ID, alias ID (barcode), or name in Acosys V8 with smart relevance and multi-token matching.
     */
    public function searchProducts(AcosysConnection $connection, string $keyword, int $limit = 8): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $cleanKw = trim($keyword);
            $tokens = array_filter(preg_split('/\s+/', $cleanKw));

            // 1. Pencarian berbasis frase utama dengan ranking relevansi
            $stmt1 = $pdo->prepare("
                SELECT p.id, p.name, p.aliasid, p.brand, p.minimum,
                       MIN(u.name) as unit_name, MIN(u.salesprice1) as salesprice1,
                       CASE 
                           WHEN p.id = :kw OR p.aliasid = :kw THEN 1
                           WHEN p.name = :kw THEN 2
                           WHEN p.name LIKE :kw_start THEN 3
                           WHEN p.name LIKE :kw_like THEN 4
                           ELSE 5
                       END as relevance
                FROM product p
                LEFT JOIN units u ON u.productid = p.id AND u.isactive = 1
                WHERE p.id = :kw OR p.aliasid = :kw OR p.name LIKE :kw_like
                GROUP BY p.id, p.name, p.aliasid, p.brand, p.minimum
                ORDER BY relevance ASC, p.name ASC
                LIMIT :limit
            ");
            $stmt1->bindValue(':kw', $cleanKw);
            $stmt1->bindValue(':kw_start', "{$cleanKw}%");
            $stmt1->bindValue(':kw_like', "%{$cleanKw}%");
            $stmt1->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt1->execute();
            $results = $stmt1->fetchAll() ?: [];

            // 2. Jika hasil masih kosong dan kata kunci multi-kata, gunakan pencarian per token (AND)
            if (empty($results) && count($tokens) > 1) {
                $whereClauses = [];
                $params = [];
                foreach ($tokens as $idx => $token) {
                    $whereClauses[] = "p.name LIKE :token_{$idx}";
                    $params["token_{$idx}"] = "%{$token}%";
                }
                $sql = "
                    SELECT p.id, p.name, p.aliasid, p.brand, p.minimum,
                           MIN(u.name) as unit_name, MIN(u.salesprice1) as salesprice1
                    FROM product p
                    LEFT JOIN units u ON u.productid = p.id AND u.isactive = 1
                    WHERE " . implode(' AND ', $whereClauses) . "
                    GROUP BY p.id, p.name, p.aliasid, p.brand, p.minimum
                    ORDER BY p.name ASC
                    LIMIT :limit
                ";
                $stmt2 = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt2->bindValue(":{$k}", $v);
                }
                $stmt2->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt2->execute();
                $results = $stmt2->fetchAll() ?: [];
            }

            // 3. Fallback pencarian terdekat jika masih kosong (potongan awalan 3 karakter)
            if (empty($results) && mb_strlen($cleanKw) >= 3) {
                $prefix = mb_substr($cleanKw, 0, 3);
                $stmt3 = $pdo->prepare("
                    SELECT p.id, p.name, p.aliasid, p.brand, p.minimum,
                           MIN(u.name) as unit_name, MIN(u.salesprice1) as salesprice1
                    FROM product p
                    LEFT JOIN units u ON u.productid = p.id AND u.isactive = 1
                    WHERE p.name LIKE :prefix
                    GROUP BY p.id, p.name, p.aliasid, p.brand, p.minimum
                    ORDER BY p.name ASC
                    LIMIT :limit
                ");
                $stmt3->bindValue(':prefix', "%{$prefix}%");
                $stmt3->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt3->execute();
                $results = $stmt3->fetchAll() ?: [];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::error('searchProducts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get complete real-time stock details for a specific product in Acosys V8.
     */
    public function getProductStockDetail(AcosysConnection $connection, string $productId): ?array
    {
        try {
            $pdo = $this->getPdo($connection);

            // 1. Ambil data master produk
            $stmtP = $pdo->prepare("
                SELECT id, aliasid, name, description, brand, minimum, category
                FROM product
                WHERE id = :id
                LIMIT 1
            ");
            $stmtP->execute(['id' => $productId]);
            $product = $stmtP->fetch();

            if (!$product) {
                return null;
            }

            // 2. Ambil data satuan dan harga jual kasir
            $stmtU = $pdo->prepare("
                SELECT name, quantity, costprice, salesprice1, salesprice2
                FROM units
                WHERE productid = :pid AND isactive = 1
                ORDER BY quantity ASC
            ");
            $stmtU->execute(['pid' => $productId]);
            $units = $stmtU->fetchAll() ?: [];

            // 3. Hitung total sisa stok fisik realtime dari inventory
            $stmtStock = $pdo->prepare("
                SELECT COALESCE(SUM(invin - invout), 0) as total_stock
                FROM inventory
                WHERE productid = :pid
            ");
            $stmtStock->execute(['pid' => $productId]);
            $totalStock = (float)$stmtStock->fetchColumn();

            // 4. Hitung rincian stok per lokasi divisi
            $stmtDiv = $pdo->prepare("
                SELECT i.division, COALESCE(d.description, CONCAT('Divisi ', i.division)) as division_name,
                       COALESCE(SUM(i.invin - i.invout), 0) as stock_qty
                FROM inventory i
                LEFT JOIN division d ON d.id = i.division
                WHERE i.productid = :pid
                GROUP BY i.division, d.description
                HAVING stock_qty != 0
                ORDER BY i.division ASC
            ");
            $stmtDiv->execute(['pid' => $productId]);
            $divisionStocks = $stmtDiv->fetchAll() ?: [];

            // Status stok
            $minStock = (float)($product['minimum'] ?? 0);
            if ($totalStock <= 0) {
                $statusText = 'Habis';
            } elseif ($minStock > 0 && $totalStock <= $minStock) {
                $statusText = 'Kritis (Di Bawah Minimum)';
            } else {
                $statusText = 'Aman';
            }

            return [
                'id' => $product['id'],
                'aliasid' => $product['aliasid'] ?: '-',
                'name' => $product['name'],
                'brand' => ($product['brand'] && $product['brand'] !== 'none') ? $product['brand'] : '-',
                'minimum' => $minStock,
                'total_stock' => $totalStock,
                'status_text' => $statusText,
                'units' => $units,
                'primary_unit' => $units[0]['name'] ?? 'PCS',
                'primary_price' => (float)($units[0]['salesprice1'] ?? 0),
                'formatted_price' => 'Rp ' . number_format((float)($units[0]['salesprice1'] ?? 0), 0, ',', '.'),
                'division_stocks' => $divisionStocks,
            ];
        } catch (\Throwable $e) {
            Log::error('getProductStockDetail error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get products with critical or zero stock in Acosys V8 for dashboard monitoring.
     */
    public function getCriticalStockProducts(AcosysConnection $connection, int $limit = 20): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.aliasid, p.minimum,
                       u.name as unit_name, u.salesprice1,
                       COALESCE(SUM(i.invin - i.invout), 0) as current_stock
                FROM product p
                LEFT JOIN units u ON u.productid = p.id AND u.isactive = 1
                JOIN inventory i ON i.productid = p.id
                GROUP BY p.id, p.name, p.aliasid, p.minimum, u.name, u.salesprice1
                HAVING current_stock <= p.minimum OR current_stock <= 0
                ORDER BY current_stock ASC, p.name ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            Log::error('getCriticalStockProducts error: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // MODUL V8: LAPORAN PIUTANG (customerbalance, customer, sales)
    // =========================================================================

    /**
     * Get global accounts receivable summary for Acosys V8.
     */
    public function getReceivableSummary(AcosysConnection $connection): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->query("
                SELECT 
                    COUNT(DISTINCT t.customerid) as total_customers,
                    COALESCE(SUM(t.balance), 0) as total_receivable
                FROM (
                    SELECT customerid, (SUM(debit) - SUM(credit)) as balance
                    FROM customerbalance
                    GROUP BY customerid
                    HAVING balance > 0
                ) t
            ");
            $row = $stmt->fetch();

            $totalAmt = (float)($row['total_receivable'] ?? 0);
            return [
                'total_customers' => (int)($row['total_customers'] ?? 0),
                'total_receivable' => $totalAmt,
                'formatted_total' => 'Rp ' . number_format($totalAmt, 0, ',', '.'),
            ];
        } catch (\Throwable $e) {
            Log::error('getReceivableSummary error: ' . $e->getMessage());
            return ['total_customers' => 0, 'total_receivable' => 0, 'formatted_total' => 'Rp 0'];
        }
    }

    /**
     * Get top debtors (pelanggan dengan piutang terbesar) in Acosys V8.
     */
    public function getTopDebtors(AcosysConnection $connection, int $limit = 5): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->prepare("
                SELECT cb.customerid, COALESCE(c.name, cb.customerid) as name,
                       COALESCE(c.mobilephone, c.telephone, '-') as phone, c.address,
                       (SUM(cb.debit) - SUM(cb.credit)) as balance
                FROM customerbalance cb
                LEFT JOIN customer c ON c.id = cb.customerid
                GROUP BY cb.customerid, c.name, c.mobilephone, c.telephone, c.address
                HAVING balance > 0
                ORDER BY balance DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as &$r) {
                $r['balance'] = (float)$r['balance'];
                $r['formatted_balance'] = 'Rp ' . number_format($r['balance'], 0, ',', '.');
            }
            return $rows;
        } catch (\Throwable $e) {
            Log::error('getTopDebtors error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search customers by ID or name with outstanding receivable, with smart relevance and multi-token matching.
     */
    public function searchCustomerReceivables(AcosysConnection $connection, string $keyword, int $limit = 8): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $cleanKw = trim($keyword);
            $tokens = array_filter(preg_split('/\s+/', $cleanKw));

            // 1. Pencarian berbasis ID atau frase nama utama
            $stmt1 = $pdo->prepare("
                SELECT cb.customerid, COALESCE(c.name, cb.customerid) as name,
                       COALESCE(c.mobilephone, c.telephone, '-') as phone, c.address,
                       (SUM(cb.debit) - SUM(cb.credit)) as balance,
                       CASE 
                           WHEN cb.customerid = :kw THEN 1
                           WHEN c.name = :kw THEN 2
                           WHEN c.name LIKE :kw_start THEN 3
                           WHEN c.name LIKE :kw_like THEN 4
                           ELSE 5
                       END as relevance
                FROM customerbalance cb
                LEFT JOIN customer c ON c.id = cb.customerid
                WHERE cb.customerid = :kw OR c.name LIKE :kw_like
                GROUP BY cb.customerid, c.name, c.mobilephone, c.telephone, c.address
                HAVING balance > 0
                ORDER BY relevance ASC, balance DESC
                LIMIT :limit
            ");
            $stmt1->bindValue(':kw', $cleanKw);
            $stmt1->bindValue(':kw_start', "{$cleanKw}%");
            $stmt1->bindValue(':kw_like', "%{$cleanKw}%");
            $stmt1->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt1->execute();
            $rows = $stmt1->fetchAll() ?: [];

            // 2. Jika kosong dan kata kunci multi-kata, gunakan pencarian per token (AND)
            if (empty($rows) && count($tokens) > 1) {
                $whereClauses = [];
                $params = [];
                foreach ($tokens as $idx => $token) {
                    $whereClauses[] = "c.name LIKE :token_{$idx}";
                    $params["token_{$idx}"] = "%{$token}%";
                }
                $sql = "
                    SELECT cb.customerid, COALESCE(c.name, cb.customerid) as name,
                           COALESCE(c.mobilephone, c.telephone, '-') as phone, c.address,
                           (SUM(cb.debit) - SUM(cb.credit)) as balance
                    FROM customerbalance cb
                    LEFT JOIN customer c ON c.id = cb.customerid
                    WHERE " . implode(' AND ', $whereClauses) . "
                    GROUP BY cb.customerid, c.name, c.mobilephone, c.telephone, c.address
                    HAVING balance > 0
                    ORDER BY balance DESC
                    LIMIT :limit
                ";
                $stmt2 = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt2->bindValue(":{$k}", $v);
                }
                $stmt2->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt2->execute();
                $rows = $stmt2->fetchAll() ?: [];
            }

            // 3. Fallback pencarian terdekat jika masih kosong (awalan 3 karakter)
            if (empty($rows) && mb_strlen($cleanKw) >= 3) {
                $prefix = mb_substr($cleanKw, 0, 3);
                $stmt3 = $pdo->prepare("
                    SELECT cb.customerid, COALESCE(c.name, cb.customerid) as name,
                           COALESCE(c.mobilephone, c.telephone, '-') as phone, c.address,
                           (SUM(cb.debit) - SUM(cb.credit)) as balance
                    FROM customerbalance cb
                    LEFT JOIN customer c ON c.id = cb.customerid
                    WHERE c.name LIKE :prefix
                    GROUP BY cb.customerid, c.name, c.mobilephone, c.telephone, c.address
                    HAVING balance > 0
                    ORDER BY balance DESC
                    LIMIT :limit
                ");
                $stmt3->bindValue(':prefix', "%{$prefix}%");
                $stmt3->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt3->execute();
                $rows = $stmt3->fetchAll() ?: [];
            }

            foreach ($rows as &$r) {
                $r['balance'] = (float)$r['balance'];
                $r['formatted_balance'] = 'Rp ' . number_format($r['balance'], 0, ',', '.');
            }
            return $rows;
        } catch (\Throwable $e) {
            Log::error('searchCustomerReceivables error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed receivables and unpaid invoices for a specific customer in Acosys V8.
     */
    public function getCustomerReceivableDetail(AcosysConnection $connection, string $customerId): ?array
    {
        try {
            $pdo = $this->getPdo($connection);

            $stmtC = $pdo->prepare("
                SELECT id, name, address, telephone, mobilephone, creditlimit
                FROM customer
                WHERE id = :id
                LIMIT 1
            ");
            $stmtC->execute(['id' => $customerId]);
            $cust = $stmtC->fetch();

            if (!$cust) {
                $cust = ['id' => $customerId, 'name' => $customerId, 'address' => '-', 'telephone' => '-', 'mobilephone' => '-', 'creditlimit' => 0];
            }

            $stmtInv = $pdo->prepare("
                SELECT cb.salesidref as invoice_no,
                       COALESCE(s.salesdate, DATE(MIN(cb.transdate))) as invoice_date,
                       DATE_ADD(COALESCE(s.salesdate, DATE(MIN(cb.transdate))), INTERVAL COALESCE(s.duedays, 0) DAY) as due_date,
                       COALESCE(s.salestotal, SUM(cb.debit)) as invoice_total,
                       (SUM(cb.debit) - SUM(cb.credit)) as remaining_balance
                FROM customerbalance cb
                LEFT JOIN sales s ON s.salesid = cb.salesidref
                WHERE cb.customerid = :cid
                GROUP BY cb.salesidref, s.salesdate, s.duedays, s.salestotal
                HAVING remaining_balance > 0
                ORDER BY invoice_date ASC
                LIMIT 15
            ");
            $stmtInv->execute(['cid' => $customerId]);
            $invoices = $stmtInv->fetchAll() ?: [];

            $totalBalance = 0;
            foreach ($invoices as &$inv) {
                $rem = (float)$inv['remaining_balance'];
                $totalBalance += $rem;
                $inv['remaining_balance'] = $rem;
                $inv['formatted_remaining'] = 'Rp ' . number_format($rem, 0, ',', '.');
                $inv['formatted_total'] = 'Rp ' . number_format((float)$inv['invoice_total'], 0, ',', '.');
                $inv['is_overdue'] = !empty($inv['due_date']) && strtotime($inv['due_date']) < strtotime(date('Y-m-d'));
            }

            $stmtNet = $pdo->prepare("
                SELECT (COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) as net_balance
                FROM customerbalance
                WHERE customerid = :cid
            ");
            $stmtNet->execute(['cid' => $customerId]);
            $netBalance = (float)$stmtNet->fetchColumn();

            $depositCredit = max(0, $totalBalance - $netBalance);

            return [
                'id' => $cust['id'],
                'name' => $cust['name'],
                'phone' => $cust['mobilephone'] ?: ($cust['telephone'] ?: '-'),
                'address' => $cust['address'] ?: '-',
                'total_balance' => $totalBalance,
                'net_balance' => $netBalance,
                'deposit_credit' => $depositCredit,
                'formatted_balance' => 'Rp ' . number_format($netBalance, 0, ',', '.'),
                'formatted_gross' => 'Rp ' . number_format($totalBalance, 0, ',', '.'),
                'formatted_deposit' => 'Rp ' . number_format($depositCredit, 0, ',', '.'),
                'invoices' => $invoices,
            ];
        } catch (\Throwable $e) {
            Log::error('getCustomerReceivableDetail error: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // MODUL V8: LAPORAN HUTANG (supplierbalance, supplier, purchase)
    // =========================================================================

    /**
     * Get global accounts payable summary for Acosys V8.
     */
    public function getPayableSummary(AcosysConnection $connection): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->query("
                SELECT 
                    COUNT(DISTINCT t.supplierid) as total_suppliers,
                    COALESCE(SUM(t.balance), 0) as total_payable
                FROM (
                    SELECT supplierid, (SUM(debit) - SUM(credit)) as balance
                    FROM supplierbalance
                    GROUP BY supplierid
                    HAVING balance > 0
                ) t
            ");
            $row = $stmt->fetch();

            $totalAmt = (float)($row['total_payable'] ?? 0);
            return [
                'total_suppliers' => (int)($row['total_suppliers'] ?? 0),
                'total_payable' => $totalAmt,
                'formatted_total' => 'Rp ' . number_format($totalAmt, 0, ',', '.'),
            ];
        } catch (\Throwable $e) {
            Log::error('getPayableSummary error: ' . $e->getMessage());
            return ['total_suppliers' => 0, 'total_payable' => 0, 'formatted_total' => 'Rp 0'];
        }
    }

    /**
     * Get top creditors (pemasok/supplier dengan hutang terbesar) in Acosys V8.
     */
    public function getTopCreditors(AcosysConnection $connection, int $limit = 5): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $stmt = $pdo->prepare("
                SELECT sb.supplierid, COALESCE(s.name, sb.supplierid) as name,
                       COALESCE(s.mobilephone, s.telephone, '-') as phone, s.address,
                       (SUM(sb.debit) - SUM(sb.credit)) as balance
                FROM supplierbalance sb
                LEFT JOIN supplier s ON s.id = sb.supplierid
                GROUP BY sb.supplierid, s.name, s.mobilephone, s.telephone, s.address
                HAVING balance > 0
                ORDER BY balance DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as &$r) {
                $r['balance'] = (float)$r['balance'];
                $r['formatted_balance'] = 'Rp ' . number_format($r['balance'], 0, ',', '.');
            }
            return $rows;
        } catch (\Throwable $e) {
            Log::error('getTopCreditors error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search suppliers by ID or name with outstanding payable balance, with smart relevance and multi-token matching.
     */
    public function searchSupplierPayables(AcosysConnection $connection, string $keyword, int $limit = 8): array
    {
        try {
            $pdo = $this->getPdo($connection);
            $cleanKw = trim($keyword);
            $tokens = array_filter(preg_split('/\s+/', $cleanKw));

            // 1. Pencarian berbasis ID atau frase nama utama
            $stmt1 = $pdo->prepare("
                SELECT sb.supplierid, COALESCE(s.name, sb.supplierid) as name,
                       COALESCE(s.mobilephone, s.telephone, '-') as phone, s.address,
                       (SUM(sb.debit) - SUM(sb.credit)) as balance,
                       CASE 
                           WHEN sb.supplierid = :kw THEN 1
                           WHEN s.name = :kw THEN 2
                           WHEN s.name LIKE :kw_start THEN 3
                           WHEN s.name LIKE :kw_like THEN 4
                           ELSE 5
                       END as relevance
                FROM supplierbalance sb
                LEFT JOIN supplier s ON s.id = sb.supplierid
                WHERE sb.supplierid = :kw OR s.name LIKE :kw_like
                GROUP BY sb.supplierid, s.name, s.mobilephone, s.telephone, s.address
                HAVING balance > 0
                ORDER BY relevance ASC, balance DESC
                LIMIT :limit
            ");
            $stmt1->bindValue(':kw', $cleanKw);
            $stmt1->bindValue(':kw_start', "{$cleanKw}%");
            $stmt1->bindValue(':kw_like', "%{$cleanKw}%");
            $stmt1->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt1->execute();
            $rows = $stmt1->fetchAll() ?: [];

            // 2. Jika kosong dan kata kunci multi-kata, gunakan pencarian per token (AND)
            if (empty($rows) && count($tokens) > 1) {
                $whereClauses = [];
                $params = [];
                foreach ($tokens as $idx => $token) {
                    $whereClauses[] = "s.name LIKE :token_{$idx}";
                    $params["token_{$idx}"] = "%{$token}%";
                }
                $sql = "
                    SELECT sb.supplierid, COALESCE(s.name, sb.supplierid) as name,
                           COALESCE(s.mobilephone, s.telephone, '-') as phone, s.address,
                           (SUM(sb.debit) - SUM(sb.credit)) as balance
                    FROM supplierbalance sb
                    LEFT JOIN supplier s ON s.id = sb.supplierid
                    WHERE " . implode(' AND ', $whereClauses) . "
                    GROUP BY sb.supplierid, s.name, s.mobilephone, s.telephone, s.address
                    HAVING balance > 0
                    ORDER BY balance DESC
                    LIMIT :limit
                ";
                $stmt2 = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt2->bindValue(":{$k}", $v);
                }
                $stmt2->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt2->execute();
                $rows = $stmt2->fetchAll() ?: [];
            }

            // 3. Fallback pencarian terdekat jika masih kosong (awalan 3 karakter)
            if (empty($rows) && mb_strlen($cleanKw) >= 3) {
                $prefix = mb_substr($cleanKw, 0, 3);
                $stmt3 = $pdo->prepare("
                    SELECT sb.supplierid, COALESCE(s.name, sb.supplierid) as name,
                           COALESCE(s.mobilephone, s.telephone, '-') as phone, s.address,
                           (SUM(sb.debit) - SUM(sb.credit)) as balance
                    FROM supplierbalance sb
                    LEFT JOIN supplier s ON s.id = sb.supplierid
                    WHERE s.name LIKE :prefix
                    GROUP BY sb.supplierid, s.name, s.mobilephone, s.telephone, s.address
                    HAVING balance > 0
                    ORDER BY balance DESC
                    LIMIT :limit
                ");
                $stmt3->bindValue(':prefix', "%{$prefix}%");
                $stmt3->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt3->execute();
                $rows = $stmt3->fetchAll() ?: [];
            }

            foreach ($rows as &$r) {
                $r['balance'] = (float)$r['balance'];
                $r['formatted_balance'] = 'Rp ' . number_format($r['balance'], 0, ',', '.');
            }
            return $rows;
        } catch (\Throwable $e) {
            Log::error('searchSupplierPayables error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed payables and unpaid purchase invoices for a specific supplier in Acosys V8.
     */
    public function getSupplierPayableDetail(AcosysConnection $connection, string $supplierId): ?array
    {
        try {
            $pdo = $this->getPdo($connection);

            $stmtS = $pdo->prepare("
                SELECT id, name, address, telephone, mobilephone, creditlimit
                FROM supplier
                WHERE id = :id
                LIMIT 1
            ");
            $stmtS->execute(['id' => $supplierId]);
            $supp = $stmtS->fetch();

            if (!$supp) {
                $supp = ['id' => $supplierId, 'name' => $supplierId, 'address' => '-', 'telephone' => '-', 'mobilephone' => '-', 'creditlimit' => 0];
            }

            $stmtInv = $pdo->prepare("
                SELECT sb.purchaseidref as invoice_no,
                       COALESCE(p.purchasedate, DATE(MIN(sb.transdate))) as invoice_date,
                       DATE_ADD(COALESCE(p.purchasedate, DATE(MIN(sb.transdate))), INTERVAL COALESCE(p.duedays, 0) DAY) as due_date,
                       COALESCE(p.purchasetotal, SUM(sb.debit)) as invoice_total,
                       (SUM(sb.debit) - SUM(sb.credit)) as remaining_balance
                FROM supplierbalance sb
                LEFT JOIN purchase p ON p.purchaseid = sb.purchaseidref
                WHERE sb.supplierid = :sid
                GROUP BY sb.purchaseidref, p.purchasedate, p.duedays, p.purchasetotal
                HAVING remaining_balance > 0
                ORDER BY invoice_date ASC
                LIMIT 15
            ");
            $stmtInv->execute(['sid' => $supplierId]);
            $invoices = $stmtInv->fetchAll() ?: [];

            $totalBalance = 0;
            foreach ($invoices as &$inv) {
                $rem = (float)$inv['remaining_balance'];
                $totalBalance += $rem;
                $inv['remaining_balance'] = $rem;
                $inv['formatted_remaining'] = 'Rp ' . number_format($rem, 0, ',', '.');
                $inv['formatted_total'] = 'Rp ' . number_format((float)$inv['invoice_total'], 0, ',', '.');
                $inv['is_overdue'] = !empty($inv['due_date']) && strtotime($inv['due_date']) < strtotime(date('Y-m-d'));
            }

            $stmtNet = $pdo->prepare("
                SELECT (COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0)) as net_balance
                FROM supplierbalance
                WHERE supplierid = :sid
            ");
            $stmtNet->execute(['sid' => $supplierId]);
            $netBalance = (float)$stmtNet->fetchColumn();

            $depositCredit = max(0, $totalBalance - $netBalance);

            return [
                'id' => $supp['id'],
                'name' => $supp['name'],
                'phone' => $supp['mobilephone'] ?: ($supp['telephone'] ?: '-'),
                'address' => $supp['address'] ?: '-',
                'total_balance' => $totalBalance,
                'net_balance' => $netBalance,
                'deposit_credit' => $depositCredit,
                'formatted_balance' => 'Rp ' . number_format($netBalance, 0, ',', '.'),
                'formatted_gross' => 'Rp ' . number_format($totalBalance, 0, ',', '.'),
                'formatted_deposit' => 'Rp ' . number_format($depositCredit, 0, ',', '.'),
                'invoices' => $invoices,
            ];
        } catch (\Throwable $e) {
            Log::error('getSupplierPayableDetail error: ' . $e->getMessage());
            return null;
        }
    }
}
