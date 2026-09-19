-- LINE 打刻システム DB スキーマ
-- 実行先: line-timecard-liff データベース（新規作成）

-- 1. staff テーブル拡張用 SQL
ALTER TABLE staff ADD COLUMN line_user_id VARCHAR(255) UNIQUE;
ALTER TABLE staff ADD COLUMN hourly_rate INT DEFAULT 1200;
ALTER TABLE staff ADD COLUMN transport_fee_per_day INT DEFAULT 200;

-- 2. timecards テーブル（新規作成）
CREATE TABLE timecards (
  id INT PRIMARY KEY AUTO_INCREMENT,
  staff_id INT NOT NULL,
  date DATE NOT NULL,
  clock_in_time TIME,
  break_start_time TIME,
  break_end_time TIME,
  clock_out_time TIME,
  latitude DECIMAL(10, 8),
  longitude DECIMAL(11, 8),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id) REFERENCES staff(id),
  UNIQUE KEY unique_daily_record (staff_id, date)
);

-- 3. payroll_settings テーブル（新規作成）
CREATE TABLE payroll_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  closing_day INT DEFAULT 30,
  break_duration_minutes INT DEFAULT 30,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. payroll_records テーブル（新規作成）
CREATE TABLE payroll_records (
  id INT PRIMARY KEY AUTO_INCREMENT,
  staff_id INT NOT NULL,
  year_month VARCHAR(7),
  working_hours DECIMAL(10, 2),
  transport_fee INT,
  gross_salary INT,
  status ENUM('pending', 'confirmed', 'sent') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id) REFERENCES staff(id),
  UNIQUE KEY unique_monthly_record (staff_id, year_month)
);
