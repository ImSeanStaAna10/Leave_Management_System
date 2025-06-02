DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  job_title VARCHAR(100) NOT NULL,
  contact_number VARCHAR(20) NOT NULL,
  birthday DATE NOT NULL,
  department VARCHAR(100) NOT NULL,
  gender ENUM('male', 'female', 'other') NOT NULL,
  date_of_joining DATE NOT NULL,
  status ENUM('active', 'inactive') DEFAULT 'active',
  address TEXT NOT NULL,
  role ENUM('admin','employee') DEFAULT 'employee',
  profile_picture VARCHAR(255) DEFAULT NULL
);

CREATE TABLE leave_balances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  leave_type_id INT NOT NULL,
  year INT NOT NULL,
  total_days INT NOT NULL,
  used_days INT DEFAULT 0,
  remaining_days INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  balance INT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
);

CREATE TABLE leaves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  leave_type INT NOT NULL,
  purpose VARCHAR(255),
  duration INT,
  start_date DATE,
  end_date DATE,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (leave_type) REFERENCES leave_types(id)
); 