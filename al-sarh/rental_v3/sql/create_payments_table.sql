DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT NOT NULL,
    installment_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    method_id INT NOT NULL,
    receipt_number VARCHAR(50) NOT NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (method_id) REFERENCES payment_methods(method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
