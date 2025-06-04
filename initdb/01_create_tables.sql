CREATE DATABASE IF NOT EXISTS invasiflora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE invasiflora;


-- 2. spinfo 物種資訊
CREATE TABLE `spcode` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,  -- 自動遞增 ID
  `spcode` VARCHAR(20) NOT NULL,         -- 物種代碼
  `chname` VARCHAR(100),               -- 中文名
  `endemic` TINYINT(1),                -- 特有種
  `naturalized` TINYINT(1),            -- 歸化種
  `cultivated` TINYINT(1),             -- 栽培種
  `uncertain` TINYINT(1),              -- 不確定分類
  `growth_form` VARCHAR(50),           -- 生長型態
  `latinname` VARCHAR(200),            -- 學名
  `simname` VARCHAR(200),              -- 簡化學名
  `genus` VARCHAR(100),                -- 屬名
  `family` VARCHAR(100),               -- APG 科名
  `chfamily` VARCHAR(100),             -- 中文科名
  `updated_by` VARCHAR(50),            -- 更新者帳號或名稱
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP  -- 更新時間
)


-- 3. spcode_index 學名代碼與中文俗名對照表
CREATE TABLE spcode_index (
  id INT AUTO_INCREMENT PRIMARY KEY,
  spcode VARCHAR(50) NOT NULL,
  chname_index VARCHAR(255),
  note TEXT,
  created_by VARCHAR(50),
  created_at DATETIME,
  updated_by VARCHAR(50),
  updated_at DATETIME
);


-- 5. plot_list 樣區編號
CREATE TABLE plot_list (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team VARCHAR(50),
  plot VARCHAR(100),
  county VARCHAR(100)
);

-- 6. im_splotdata_2025 調查點位資訊
CREATE TABLE im_splotdata_2025 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team VARCHAR(50),
  date DATE,
  year INT,
  month INT,
  day INT,
  investigator VARCHAR(100),
  recorder VARCHAR(100),
  plot_full_id VARCHAR(100),
  tm2_x DOUBLE,
  tm2_y DOUBLE,
  dd97_x DOUBLE,
  dd97_y DOUBLE,
  gps_error DOUBLE
  plot VARCHAR(100),
  habitat_code VARCHAR(50),
  subplot_id VARCHAR(50),
  subplot_area VARCHAR(50),
  island_category VARCHAR(50),
  plot_env VARCHAR(100),
  elevation FLOAT,
  slope FLOAT,
  aspect FLOAT,
  light_0 FLOAT,
  light_45 FLOAT,
  light_90 FLOAT,
  light_135 FLOAT,
  light_180 FLOAT,
  light_225 FLOAT,
  light_270 FLOAT,
  light_315 FLOAT,
  photo_id VARCHAR(100),
  env_description TEXT,
  note TEXT,
  validation_message TEXT,
  created_by VARCHAR(100),
  created_at DATETIME,
  updated_by VARCHAR(100),
  updated_at DATETIME
);

-- 7. habitat_info 生育地類型說明
CREATE TABLE habitat_info (
  id INT AUTO_INCREMENT PRIMARY KEY,
  habitat_code VARCHAR(50),
  habitat VARCHAR(100),
  definition TEXT,
  sampling_principle TEXT
);

-- 8. im_spvptdata_2025 樣區調查資料
CREATE TABLE im_spvptdata_2025 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plot_full_id VARCHAR(100),
  spcode VARCHAR(50),
  chname_index VARCHAR(255),
  life_type VARCHAR(100),
  coverage FLOAT,
  flowering BOOLEAN DEFAULT 0,
  fruiting BOOLEAN DEFAULT 0,
  note TEXT,
  specimen_id TEXT,
  cov_error BOOLEAN DEFAULT 0,
  unidentified BOOLEAN DEFAULT 0,
  created_by VARCHAR(100),
  created_at DATETIME,
  updated_by VARCHAR(100),
  updated_at DATETIME
);

-- 9. fixlog

CREATE TABLE fix_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100),         -- 例如 'plot_info'
    record_id BIGINT,                -- 對應 plot_info.id
    changes JSON,                    -- ⬅️ 使用 JSON 欄位類型，比 VARCHAR 可靠
    modified_by VARCHAR(100),        -- 使用者帳號或 email 前綴
    modified_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

