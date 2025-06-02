CREATE TABLE leave_types (
  id INT PRIMARY KEY,
  name VARCHAR(80),
  description VARCHAR(255)
);

INSERT INTO leave_types (id, name, description) VALUES
(1, 'Service Incentive Leave (SIL)', '5 days paid leave per year after 1 year of service (required by law).'),
(2, 'Sick Leave', 'Paid leave for health-related issues. Number of days varies by company (common: 5–15 days).'),
(3, 'Vacation Leave', 'Paid leave for personal rest or travel. Not mandated but widely practiced (common: 10–15 days).'),
(4, 'Maternity Leave', '105 days paid leave (can extend up to 120 days for solo parents); covered by SSS and RA 11210.'),
(5, 'Paternity Leave', '7 days paid leave for married male employees (up to 4 children).'),
(6, 'Bereavement Leave', 'Leave for death of immediate family. Commonly 3–5 days; not required by law.'),
(7, 'Solo Parent Leave', '7 days per year for solo parents (RA 8972), after 1 year of service.'),
(8, 'Special Leave for Women', 'Up to 2 months leave for surgery due to gynecological disorders (RA 9710).'); 