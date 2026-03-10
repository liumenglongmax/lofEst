# LOF 估值项目 — 部署文档

本文档说明数据库等配置所在位置，以及如何部署本 PHP 项目。

---

## 一、配置位置总览

| 配置项 | 所在文件 | 说明 |
|--------|----------|------|
| 数据库连接（主机、用户、库名、密码） | 见下文「数据库配置」 | 主机、用户名、库名在 `php/sql.php` 中定义；密码在 `php/_private.php` |
| 数据库主机/用户/库名 | `php/sql.php` | `DB_HOST`、`DB_USER`、`DB_DATABASE` |
| 表名常量 | `php/sql.php` | `TABLE_MEMBER`、`TABLE_STOCK_GROUP` 等 |
| 建表时库名 | `php/sql/sqltable.php` 等 | 使用 `DB_DATABASE` 常量，与 `sql.php` 一致 |
| 错误通知邮箱 | `php/email.php` | `ADMIN_EMAIL` |

---

## 二、数据库配置

### 2.1 连接建立位置

- **文件**：`php/sql.php`
- **函数**：`SqlConnectDatabase()`（约 185–206 行）

连接代码逻辑为：

```php
$g_link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
mysqli_set_charset($g_link, 'utf8');
mysqli_select_db($g_link, DB_DATABASE);
```

即：

- **主机**：`DB_HOST`，在 `php/sql.php` 中定义（当前为 `127.0.0.1`）
- **用户名**：`DB_USER`，在 `php/sql.php` 中定义（当前为 `morgan`）
- **密码**：`DB_PASSWORD`（**必须**在 `php/_private.php` 中定义）
- **数据库名**：`DB_DATABASE`，在 `php/sql.php` 中定义（当前为 `morgan_db`）
- **字符集**：`utf8`

### 2.2 私有配置文件（必须自行创建）

- **路径**：`php/_private.php`
- **说明**：该文件**未纳入版本库**（敏感信息），需在部署环境中**自行创建**。
- **必须定义**：

```php
<?php
define('DB_PASSWORD', '你的MySQL密码');
```

### 2.3 修改数据库主机、用户或库名时

- 仅需修改 `php/sql.php` 中的 `DB_HOST`、`DB_USER`、`DB_DATABASE` 三个常量；建表逻辑（如 `php/sql/sqltable.php`）已使用 `DB_DATABASE`，会随之生效。

---

## 三、数据库表（与 LOF/估值相关的主要表）

表结构由各 `php/sql/*.php` 中的 Sql 类在首次访问时通过 `CreateTable` / `CreateIdTable` 等**按需创建**（`CREATE TABLE IF NOT EXISTS`）。主要表名如下（名称来自 sql 层常量或类构造参数）：

| 表名 | 用途 |
|------|------|
| member / member2 | 用户/会员 |
| profile | 用户资料 |
| page / pagecomment | 页面与评论 |
| visitor / ip | 访问与 IP |
| stock | 股票/基金代码与基础信息 |
| stockgroup / stockgroupitem | 自选分组与分组项 |
| stocktransaction | 交易记录 |
| stockhistory | 行情历史 |
| netvaluehistory | 净值历史 |
| fundpurchase | 申购等基金购买记录 |
| fundest | 估值结果（官方估值等） |
| calibrationhistory | 校准历史（校准因子） |
| fundposition | 基金仓位 |
| fundarbitrage | 套利建议等 |
| holdings / holdingsdate | 持仓与持仓日期 |
| stockhistorydate / holdingsdate / navfiledate | 各类日期索引 |
| shareshistory / sharesdiff | 份额历史等 |
| fundpair / ahpair / abpair / adrpair | 基金配对、AH/AB/ADR 等 |
| stockdividend / stocksplit | 分红、拆股 |
| gb2312 | 编码等 |
| commonphrase | 常用语等 |

数据库若为空，首次访问会触发相应 Sql 类的 `Create()`，从而创建上述表（字符集为 utf8，排序规则 utf8_unicode_ci）。

---

## 四、部署步骤

### 4.1 环境要求

- **PHP**：支持 mysqli 扩展；建议 PHP 7.4+（视实际运行环境而定）。
- **MySQL/MariaDB**：5.x 或 8.x，支持 utf8 字符集。
- **Web 服务器**：Apache 或 Nginx，需配置为执行 PHP（如 PHP-FPM）。

### 4.2 代码与目录

1. 将项目放到 Web 可访问目录，例如文档根目录为 `/var/www/lofvaluation/web`（或你的实际路径）。
2. 确保 **文档根** 指向包含 `php/`、`woody/` 的目录，使得：
   - 访问 `http(s)://域名/woody/res/sz162411cn.php` 等 URL 能正确映射到 `woody/res/sz162411cn.php`。

### 4.3 创建私有配置

1. 在 `php/` 目录下创建 `_private.php`。
2. 至少定义数据库密码：

```php
<?php
define('DB_PASSWORD', '你的MySQL密码');
```

3. 确保 `_private.php` 权限仅应用可读，且**不要**提交到版本库（建议加入 `.gitignore`）。

### 4.4 数据库准备

1. 在 MySQL 中创建用户（若与当前代码一致，用户名为 `morgan`），并授予对数据库的权限。
2. 创建数据库（若与当前代码一致，库名为 `morgan_db`），字符集 utf8：

```sql
CREATE DATABASE morgan_db DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
GRANT ALL ON morgan_db.* TO 'morgan'@'%';
FLUSH PRIVILEGES;
```

3. 若使用**不同主机/库名/用户名**，仅需修改 `php/sql.php` 中的 `DB_HOST`、`DB_USER`、`DB_DATABASE`，并在 `_private.php` 中设置对应 `DB_PASSWORD`。
4. 表无需手动建：首次访问会通过各 Sql 类自动 `CREATE TABLE IF NOT EXISTS`。

### 4.5 Web 服务器配置要点

- **文档根**：指向项目根（即包含 `php`、`woody` 的目录），以便 `woody/res/*.php` 中的 `require('php/...')`、`require('../../php/...')` 等相对路径正确解析（一般需保证「当前工作目录」为项目根，或所有 require 基于同一根做 include_path）。
- **PHP**：确保请求 `.php` 时由 PHP 解析；若使用 Nginx，需配置 `try_files` 和 `fastcgi_pass` 到 PHP-FPM。
- **权限**：运行 Web 的用户需要对 `php/`、`woody/` 等有读权限；若有写调试/缓存文件，需对相应目录有写权限。

### 4.6 首次访问与校验

1. 打开站点首页（如 `woody/indexcn.php`）或任意 LOF 页（如 `woody/res/sz162411cn.php`）。
2. 若数据库连接失败，会输出 `Failed to connect to server` 或类似错误；若 `_private.php` 缺失会报未定义 `DB_PASSWORD`。
3. 连接成功后，访问涉及估值、分组、交易等功能的页面，会自动创建对应表。

### 4.7 汇率数据（USCNY、HKCNY）

- **来源**：USCNY（美元/人民币）、HKCNY（港币/人民币）等汇率**通过接口获取**，接口地址为中国货币网：
  - `http://www.chinamoney.com.cn/r/cms/www/chinamoney/data/fx/ccpr.json`
- **写入**：由 `php/stock/chinamoney.php` 中的 `GetChinaMoney()` 解析该 JSON，将 USD/CNY、HKD/CNY 等写入数据库净值历史表（与基金净值同表，按品种 ID 区分）。
- **触发时机**：`GetChinaMoney()` 在访问以下页面时会被调用（任一处即可拉取并写入当日汇率）：
  - **LOF 汇总页**（`woody/res/php/_mystockgroup.php`）— 打开汇总即会尝试拉取一次；
  - QDII 分组页（`woody/res/php/_qdiigroup.php`）；
  - QDII 混合页（`woody/res/php/_qdiimix.php`）；
  - 中国指数页（`woody/res/php/_chinaindex.php`）。
- **为何会为空**：若上述页面均未访问过，或访问时接口失败（网络、域名不可达等），则库中无当日汇率，估值页会显示「请配置汇率」。
- **建议**：确保服务器能访问 `chinamoney.com.cn`；若该接口长期不可用，可考虑在 `_private.php` 中配置备用汇率或自行写入净值表（参见 `php/stock/holdingsref.php` 中 USCNY/HKCNY 的用法）。
- **若 USCNY 仍为空**：① 接口为 HTTPS，程序使用 8～10 秒超时单独拉取；② 访问时间需在中国时间 9:15 之后（接口届时才更新）；③ 确保服务器能访问中国货币网该接口。

- **为什么估值页 33 项都显示「请配置汇率」**：页面上每只 LOF 的估值都依赖 **USCNY / HKCNY** 等汇率（见 `php/stock/holdingsref.php` 中 `GetAdjustCny` / `GetAdjustHkd`）。这些汇率**只有**在 `GetChinaMoney()` 成功拉取并写入 `netvaluehistory` 后才有值。只要**库中没有对应日期的 USCNY、HKCNY 记录**，所有依赖该汇率的基金在 `php/ui/fundestparagraph.php` 中都会走「汇率未配置」分支，统一显示「请配置汇率」。因此 33 项一起显示该提示，说明**当前环境尚未成功写入过汇率**。处理方式：先访问会触发 `GetChinaMoney` 的页面（如 LOF 汇总），且满足「中国时间 ≥ 9:15」和「服务器能访问中国货币网接口」；若接口不可用，需自行向 `netvaluehistory` 写入 USCNY/HKCNY 的当日数据，或通过其他方式提供汇率（见上文建议）。

### 4.8 可选：错误通知

- 错误处理中会向 `ADMIN_EMAIL` 发邮件（见 `php/sql.php` 中 `_errorHandler`、`php/email.php`）。
- 若需修改收件邮箱，改 `php/email.php` 中的 `ADMIN_EMAIL`。

---

## 五、配置与部署速查

| 项目 | 位置/做法 |
|------|------------|
| 数据库密码 | 在 `php/_private.php` 中定义 `DB_PASSWORD`，该文件需自行创建且不提交版本库 |
| 数据库主机/用户/库名 | `php/sql.php` 中 `DB_HOST`、`DB_USER`、`DB_DATABASE`（当前分别为 `127.0.0.1`、`morgan`、`morgan_db`） |
| 表结构 | 由 `php/sql/` 下各 Sql 类按需创建，库名统一使用 `DB_DATABASE`，无需再改其他文件 |
| 部署入口 | 将 Web 文档根指向项目根，通过 `woody/indexcn.php`、`woody/res/*.php` 等访问 |
| 错误通知邮箱 | `php/email.php` 中 `ADMIN_EMAIL` |
| 汇率 USCNY/HKCNY | 通过中国货币网接口按请求拉取并写入库；访问 LOF 汇总或 QDII/中国指数等页会触发拉取，参见 4.7 |

按上述步骤即可完成数据库配置与项目部署。修改主机/用户/库名时只需改 `php/sql.php` 中对应常量；密码等敏感信息仅放在 `_private.php` 且不进入版本库。

---

## 六、Windows 本地部署（Laragon）

以下说明在 **Windows** 上使用 **Laragon** 完成 PHP + MySQL + Web 环境的搭建、项目放置与访问。Laragon 轻量、支持多 PHP 版本，自带 Nginx/Apache，并可为每个项目自动生成虚拟域名，适合本地开发与调试。

### 6.1 下载与安装 Laragon

1. **下载**
   - 打开 [Laragon 官网](https://laragon.org/)（或 [GitHub Releases](https://github.com/leokhoa/laragon/releases)）。
   - 选择 **Laragon Full** 或 **Laragon Portable**：
     - **Full**：安装到固定目录（默认 `C:\laragon`），会注册右键菜单等。
     - **Portable**：解压即用，可放 U 盘或任意目录，不写注册表。

2. **安装**
   - 运行安装程序，安装路径建议保持默认 `C:\laragon`（路径中避免空格与中文）。
   - 安装完成后启动 Laragon，右下角托盘会出现 Laragon 图标。

3. **PHP 与扩展**
   - 本项目需要 **PHP 7.4+** 且启用 **mysqli**。Laragon 自带 PHP，一般已包含 mysqli。
   - 若需切换 PHP 版本：右键托盘图标 → **PHP** → 选择版本（如 8.1、8.2）；或菜单 **Menu** → **PHP** → **Version**。
   - 确认 mysqli：在 Laragon 中点击 **Terminal** 打开终端，执行 `php -m | findstr mysqli`，应看到 `mysqli`。

### 6.2 启动服务

1. 在 Laragon 主界面点击 **Start All**，会启动：
   - **Apache** 或 **Nginx**（可在 **Menu** → **Preferences** 中切换，默认多为 Apache）；
   - **MySQL**（MariaDB）。
2. 启动成功后，“Apache”和“MySQL”旁会显示绿色运行状态。
3. **端口**（若冲突可改）：
   - 在 **Menu** → **Preferences** → **Port** 中可查看/修改：
     - Apache 默认 80，Nginx 默认 80；
     - MySQL 默认 3306。
   - 若 80 被占用，可改为 8080 等，访问时需带端口，例如 `http://localhost:8080/...`。

### 6.3 放置项目目录

1. **Laragon 的 Web 根目录** 为安装目录下的 `www`，例如：
   - `C:\laragon\www\`
2. **放置本项目**：
   - 将项目根目录（包含 `php/`、`woody/` 的目录）放到 `www` 下，例如：
     - `C:\laragon\www\lofvaluation\web\`
   - 确保该目录下有 `php/`、`woody/`，路径中尽量无空格和中文，避免 `require` 路径异常。

3. **虚拟域名（推荐）**
   - Laragon 支持“自动虚拟主机”：在 `www` 下创建一个**子文件夹作为站点根**，即可通过 `http://文件夹名.test` 访问（Laragon 会将该文件夹名写入 hosts，如 `lofvaluation.test`）。
   - **推荐做法**：将项目根（含 `php/`、`woody/`）直接放在 `www` 下一级，例如：
     - 路径：`C:\laragon\www\lofvaluation\`（即 `php`、`woody` 在 `lofvaluation` 目录内）；
     - 访问：`http://lofvaluation.test/woody/indexcn.php`。
   - **另一种放法**：若项目在 `C:\laragon\www\lofvaluation\web\`，则无虚拟主机时用：
     - `http://localhost/lofvaluation/web/woody/indexcn.php`；
     - 虚拟主机名为 `www` 下第一级文件夹，此时为 `lofvaluation`，文档根为 `C:\laragon\www\lofvaluation`，故若要以虚拟域名访问，建议把项目内容放到 `www\lofvaluation\`，再用 `http://lofvaluation.test/woody/indexcn.php`。
   - 确认 **Auto** 已开启：**Menu** → **Preferences** → **General** → 勾选 **Auto**（自动创建虚拟主机），**Menu** → **Tools** → **Edit hosts file** 可查看 Laragon 写入的 hosts。

### 6.4 创建私有配置（_private.php）

1. 在项目目录下进入 `php/`，新建文件 `_private.php`（若不存在）。
2. 内容至少包含数据库密码（与 6.5 中创建的 MySQL 用户密码一致）：
   ```php
   <?php
   define('DB_PASSWORD', '你的MySQL密码');
   ```
3. 不要将含真实密码的 `_private.php` 提交到版本库；建议加入 `.gitignore`。

### 6.5 创建数据库与用户（MySQL）

本项目默认使用 `php/sql.php` 中的配置：`DB_HOST = '127.0.0.1'`、`DB_USER = 'morgan'`、`DB_DATABASE = 'morgan_db'`。需在 Laragon 自带的 MySQL 中建库、建用户并授权。

1. **打开 MySQL 命令行**
   - 在 Laragon 中点击 **Terminal**，或 Win+R 输入 `cmd` 后进入项目目录；
   - Laragon 的 MySQL 默认 **root 无密码**，执行：
     ```bat
     mysql -u root
     ```
   - 若已设过 root 密码，使用：`mysql -u root -p` 并输入密码。

2. **执行 SQL**
   - 在 MySQL 命令行中执行（将 `你的MySQL密码` 替换为实际密码，并与 `_private.php` 中 `DB_PASSWORD` 一致）：
     ```sql
     CREATE DATABASE morgan_db DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
     CREATE USER 'morgan'@'localhost' IDENTIFIED BY '你的MySQL密码';
     GRANT ALL ON morgan_db.* TO 'morgan'@'localhost';
     FLUSH PRIVILEGES;
     EXIT;
     ```

3. **使用其他库名/用户名时**
   - 修改 `php/sql.php` 中的 `DB_USER`、`DB_DATABASE`，并在 `_private.php` 中填写对应用户的密码。

### 6.6 访问站点与校验

1. 浏览器访问（按实际放置方式二选一）：
   - 若项目根在 `C:\laragon\www\lofvaluation\`（推荐）：`http://lofvaluation.test/woody/indexcn.php`；
   - 若项目根在 `C:\laragon\www\lofvaluation\web\`：`http://localhost/lofvaluation/web/woody/indexcn.php`（若改了端口，例如 8080，则为 `http://localhost:8080/lofvaluation/web/woody/indexcn.php`）。
2. 若出现 “Failed to connect to server” 或 “DB_PASSWORD 未定义”：
   - 检查 `php/_private.php` 是否存在且定义了 `DB_PASSWORD`；
   - 检查 MySQL 是否已通过 **Start All** 启动；
   - 检查 6.5 中建库、建用户、授权是否执行成功，密码是否与 `_private.php` 一致。
3. 连接成功后，访问估值、分组等页面时，表会按需自动创建（无需手动建表）。

### 6.7 打包与拷贝到其他 Windows 机器（可选）

若需在本机打包后到另一台 Windows 机器用 Laragon 运行：

1. **打包内容**：项目根下 `php/`、`woody/`、`docs/`、`README.md` 等；**不要**包含 `.git/`，也**不要**包含已填真实密码的 `php/_private.php`。
2. 可保留一份 `php/_private.php.example`（仅含 `define('DB_PASSWORD', '');`），在目标机复制为 `_private.php` 并填写该机 MySQL 密码。
3. 将打包目录放到目标机的 `C:\laragon\www\` 下（如 `C:\laragon\www\lofvaluation\web\`），按 6.4～6.6 在目标机创建 `_private.php`、建库建用户后访问即可。

### 6.8 Windows + Laragon 部署速查

| 步骤 | 操作 |
|------|------|
| 环境 | 安装 [Laragon](https://laragon.org/)，主界面 **Start All** 启动 Apache/Nginx + MySQL |
| 项目位置 | 放到 `C:\laragon\www\` 下，如 `C:\laragon\www\lofvaluation\web\`（目录内需有 `php/`、`woody/`） |
| 配置 | 在 `php/_private.php` 中定义 `DB_PASSWORD`；主机/用户/库名在 `php/sql.php`（默认 127.0.0.1 / morgan / morgan_db） |
| 数据库 | Laragon Terminal 执行 `mysql -u root`，再执行建库、建用户、授权 SQL（见 6.5） |
| 访问 | 虚拟域名：`http://lofvaluation.test/woody/indexcn.php`（项目在 `www\lofvaluation\` 时）；或 localhost：`http://localhost/lofvaluation/web/woody/indexcn.php`（改过端口则加 `:端口`） |
| 打包 | 复制项目目录（不含 `.git`、不含含密码的 `_private.php`），可打 ZIP；目标机解压到 `www` 下后按上表配置并建库 |
