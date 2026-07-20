USE uiri_ims;

CREATE TABLE IF NOT EXISTS inventory_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    incident_type ENUM('damaged','missing') NOT NULL,
    quantity INT NOT NULL,
    details VARCHAR(500) NOT NULL,
    incident_date DATE NOT NULL,
    status ENUM('open','recovered','written_off') NOT NULL DEFAULT 'open',
    reported_by INT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolution_note VARCHAR(500) DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_incident_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_incident_reporter FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_incident_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_incident_status (status),
    INDEX idx_incident_type (incident_type),
    INDEX idx_incident_date (incident_date)
) ENGINE=InnoDB;
