<?php
/**
 * Lightweight runtime migrator.
 * Adds any missing v1.1 / v1.2 columns on first request, so the app never
 * crashes on "Unknown column" when the user hasn't manually imported the
 * migration SQL.
 *
 * It runs ONCE per session (cached in $_SESSION).
 * Safe to re-run: every ALTER is gated on information_schema existence.
 */

function nu_run_migrations(PDO $pdo): void
{
    if (!empty($_SESSION['nu_migrated'])) return;

    $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    if (!$db) return;

    /** @param string $table @param string $col @param string $ddl */
    $addCol = function(string $table, string $col, string $ddl) use ($pdo, $db) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$db, $table, $col]);
        if ((int)$stmt->fetchColumn() === 0) {
            try {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
            } catch (Throwable $e) { /* swallow - admin can run sql manually */ }
        }
    };

    $tableExists = function(string $table) use ($pdo, $db): bool {
        $s = $pdo->prepare(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
        );
        $s->execute([$db, $table]);
        return (bool)$s->fetchColumn();
    };

    // Soft-delete trios on the mutable tables -------------------------
    $softTables = ['users','products','clients','sales','feedback','sample_drops'];
    foreach ($softTables as $t) {
        if (!$tableExists($t)) continue;
        $addCol($t, 'deleted_at',    '`deleted_at` DATETIME NULL');
        $addCol($t, 'deleted_by',    '`deleted_by` INT UNSIGNED NULL');
        $addCol($t, 'delete_reason', '`delete_reason` VARCHAR(255) NULL');
    }

    // v1.1 client KYC additions ---------------------------------------
    if ($tableExists('clients')) {
        $addCol('clients', 'postal_address',     '`postal_address` VARCHAR(255) NULL');
        $addCol('clients', 'directors',          '`directors` TEXT NULL');
        $addCol('clients', 'accountant_name',    '`accountant_name` VARCHAR(160) NULL');
        $addCol('clients', 'accountant_phone',   '`accountant_phone` VARCHAR(40) NULL');
        $addCol('clients', 'bank_name',          '`bank_name` VARCHAR(80) NULL');
        $addCol('clients', 'bank_branch',        '`bank_branch` VARCHAR(80) NULL');
        $addCol('clients', 'payment_terms',      '`payment_terms` VARCHAR(120) NULL');
        $addCol('clients', 'credit_period_days', '`credit_period_days` INT NULL');
        $addCol('clients', 'trade_ref_1',        '`trade_ref_1` TEXT NULL');
        $addCol('clients', 'trade_ref_2',        '`trade_ref_2` TEXT NULL');
        $addCol('clients', 'trade_ref_3',        '`trade_ref_3` TEXT NULL');
        $addCol('clients', 'signed_name',        '`signed_name` VARCHAR(160) NULL');
        $addCol('clients', 'signed_position',    '`signed_position` VARCHAR(80) NULL');
        $addCol('clients', 'signed_at',          '`signed_at` DATE NULL');
    }

    // v1.2 sample_drops pickup workflow -------------------------------
    if ($tableExists('sample_drops')) {
        $addCol('sample_drops', 'payment_collected', '`payment_collected` DECIMAL(14,2) NOT NULL DEFAULT 0');
        $addCol('sample_drops', 'payment_method',    '`payment_method` VARCHAR(40) NULL');
        $addCol('sample_drops', 'next_pickup_date',  '`next_pickup_date` DATE NULL');
        $addCol('sample_drops', 'reschedule_reason', '`reschedule_reason` VARCHAR(255) NULL');

        // Expand ENUM if needed
        $s = $pdo->prepare(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'sample_drops' AND COLUMN_NAME = 'status'"
        );
        $s->execute([$db]);
        $type = (string)$s->fetchColumn();
        if ($type && stripos($type, 'rescheduled') === false) {
            try {
                $pdo->exec(
                    "ALTER TABLE sample_drops MODIFY COLUMN status
                      ENUM('scheduled','dropped','rescheduled','no_show','picked_up','cancelled')
                      NOT NULL DEFAULT 'scheduled'"
                );
            } catch (Throwable $e) {}
        }
    }

    // v1.1 settings (bank + company) ----------------------------------
    if ($tableExists('settings')) {
        $defaults = [
            'company_tagline'    => 'Your Partner in Natural Wellness',
            'bank_name'          => 'NCBA Bank',
            'bank_branch'        => 'ABC Place',
            'bank_account_name'  => 'Nourish U Biotech Limited',
            'bank_account_kes'   => '1005858439',
            'bank_account_usd'   => '1006641133',
            'bank_swift'         => 'CBAFKENX',
            'mpesa_paybill'      => '880100',
            'mpesa_account'      => '606264',
        ];
        try {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO settings (`key`,`value`) VALUES (?, ?)"
            );
            foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);
        } catch (Throwable $e) {}
    }


    // v1.3 expenses table -----------------------------------------------
    if (!$tableExists('expenses')) {
        try {
            $pdo->exec(
                "CREATE TABLE expenses (
                    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
                    rep_id        INT UNSIGNED  NOT NULL,
                    spent_on      DATE          NOT NULL,
                    category      VARCHAR(60)   NOT NULL DEFAULT 'Other',
                    amount        DECIMAL(14,2) NOT NULL DEFAULT 0,
                    description   TEXT          NULL,
                    receipt_path  VARCHAR(255)  NULL,
                    client_id     INT UNSIGNED  NULL,
                    status        ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
                    review_notes  TEXT          NULL,
                    approved_by   INT UNSIGNED  NULL,
                    approved_at   DATETIME      NULL,
                    paid_at       DATETIME      NULL,
                    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at    DATETIME      NULL,
                    deleted_by    INT UNSIGNED  NULL,
                    delete_reason VARCHAR(255)  NULL,
                    INDEX idx_exp_rep    (rep_id),
                    INDEX idx_exp_date   (spent_on),
                    INDEX idx_exp_status (status),
                    INDEX idx_exp_del    (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {}
    }

    $_SESSION['nu_migrated'] = true;
}

nu_run_migrations($pdo);
