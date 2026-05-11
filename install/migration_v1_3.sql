-- =======================================================================
--  Nourish U Biotech - Migration to v1.3
--  Adds the expenses module.
-- =======================================================================

CREATE TABLE IF NOT EXISTS expenses (
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
    INDEX idx_exp_del    (deleted_at),
    CONSTRAINT fk_exp_rep      FOREIGN KEY (rep_id)      REFERENCES users(id),
    CONSTRAINT fk_exp_client   FOREIGN KEY (client_id)   REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_exp_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
