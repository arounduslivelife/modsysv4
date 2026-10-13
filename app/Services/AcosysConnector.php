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

            return [
                'status' => true,
                'message' => "Koneksi berhasil! Terhubung ke {$companyName} ({$customerCount} pelanggan, MariaDB/MySQL {$version}).",
                'version' => $version,
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
}
