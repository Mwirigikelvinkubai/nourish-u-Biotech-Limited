<?php
/**
 * Commission engine.
 *
 * Hybrid model:
 *   line_commission = line_total × product.base_commission_pct (already stored on sale_items)
 *   monthly_bonus   = monthly_sales_total × tier.bonus_pct      (looked up from commission_tiers)
 *   total           = SUM(line_commission)  +  monthly_bonus
 */

function compute_rep_month(PDO $pdo, int $repId, int $year, int $month): array
{
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end   = date('Y-m-d', strtotime("$start +1 month"));

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(s.subtotal),0)              AS sales_total,
                COALESCE(SUM(si.commission_amount),0)    AS base_total,
                COUNT(DISTINCT s.id)                     AS invoice_count
           FROM sales s
           JOIN sale_items si ON si.sale_id = s.id
          WHERE s.rep_id = ?
            AND s.sale_date >= ?
            AND s.sale_date <  ?"
    );
    $stmt->execute([$repId, $start, $end]);
    $r = $stmt->fetch();

    $sales = (float)$r['sales_total'];
    $base  = (float)$r['base_total'];

    /* find matching tier */
    $tier = $pdo->prepare(
        "SELECT * FROM commission_tiers
          WHERE min_amount <= ?
            AND (max_amount IS NULL OR max_amount >= ?)
          ORDER BY min_amount DESC LIMIT 1"
    );
    $tier->execute([$sales, $sales]);
    $tier = $tier->fetch() ?: null;

    $bonusPct = $tier ? (float)$tier['bonus_pct'] : 0;
    $bonus    = round($sales * $bonusPct / 100, 2);

    return [
        'rep_id'           => $repId,
        'period_year'      => $year,
        'period_month'     => $month,
        'sales_total'      => round($sales, 2),
        'base_total'       => round($base, 2),
        'bonus_pct'        => $bonusPct,
        'bonus_total'      => $bonus,
        'total_commission' => round($base + $bonus, 2),
        'invoice_count'    => (int)$r['invoice_count'],
        'tier_label'       => $tier['label'] ?? 'No tier',
    ];
}

function save_rep_month(PDO $pdo, array $c): int
{
    /* upsert into commissions table */
    $stmt = $pdo->prepare(
        "INSERT INTO commissions
           (rep_id, period_year, period_month, sales_total, base_total,
            bonus_pct, bonus_total, total_commission, status, generated_at)
         VALUES (?,?,?,?,?,?,?,?, 'pending', NOW())
         ON DUPLICATE KEY UPDATE
           sales_total      = VALUES(sales_total),
           base_total       = VALUES(base_total),
           bonus_pct        = VALUES(bonus_pct),
           bonus_total      = VALUES(bonus_total),
           total_commission = VALUES(total_commission),
           generated_at     = NOW()"
    );
    $stmt->execute([
        $c['rep_id'], $c['period_year'], $c['period_month'],
        $c['sales_total'], $c['base_total'],
        $c['bonus_pct'], $c['bonus_total'], $c['total_commission'],
    ]);
    $id = $pdo->prepare("SELECT id FROM commissions WHERE rep_id=? AND period_year=? AND period_month=?");
    $id->execute([$c['rep_id'], $c['period_year'], $c['period_month']]);
    return (int)$id->fetchColumn();
}
