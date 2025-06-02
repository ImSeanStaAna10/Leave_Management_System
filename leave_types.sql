-- Drop the table if it exists
DROP TABLE IF EXISTS leave_types;

-- Create the leave_types table
CREATE TABLE leave_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    days INT NOT NULL,
    description TEXT
);

-- Insert initial leave types with descriptions
INSERT INTO leave_types (id, name, days, description) VALUES 
(1, 'Annual Leave', 14, 'Standard annual leave entitlement. Employees are eligible after completing 6 months of service. Can be taken in minimum blocks of 1 day. Requires advance notice of at least 2 weeks.'),
(2, 'Sick Leave', 7, 'Medical leave for illness or injury. Requires medical certificate for leaves longer than 3 days. Can be taken in minimum blocks of 1 day. No advance notice required for emergency cases.'),
(3, 'Maternity Leave', 90, 'For female employees. Requires medical documentation and advance notice of at least 3 months. Can be extended in special cases with management approval.'),
(4, 'Paternity Leave', 14, 'For new fathers. Must be taken within 6 months of child birth. Requires birth certificate and advance notice of at least 1 month.'),
(5, 'Unpaid Leave', 30, 'Special circumstances only. Requires management approval and documentation. Must be applied for at least 1 month in advance. Maximum 30 days per calendar year.'); 