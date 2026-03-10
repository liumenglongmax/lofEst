# 项目 URL 路径说明

项目根目录为 Web 文档根（如部署在 `/web/` 下则根为 `http://域名/web/`）。以下路径均为**相对站点根**的 URL 路径。

---

## 一、路径常量（代码中的定义）

| 常量 | 值 | 说明 |
|------|-----|------|
| `PATH_STOCK` | `/woody/res/` | 基金/股票相关页面 |
| `PATH_ACCOUNT` | `/account/` | 账户、工具、访问统计等 |
| `PATH_BLOG` | `/woody/blog/` | 博客/开发记录 |

---

## 二、主要入口与目录

### 1. 站点首页

| URL 路径 | 说明 |
|----------|------|
| `/woody/indexcn.php` | 中文首页（推荐入口） |
| `/woody/index.php` | 英文首页 |

### 2. LOF / 基金估值（`/woody/res/`）

| URL 路径 | 说明 |
|----------|------|
| `/woody/res/lofcn.php` | **LOF 汇总页**（有估值的 LOF 列表） |
| `/woody/res/qdiicn.php` | 美股 QDII |
| `/woody/res/qdiimixcn.php` | 混合 QDII |
| `/woody/res/qdiihkcn.php` | 港股 QDII |
| `/woody/res/qdiijpcn.php` | 日本 QDII |
| `/woody/res/qdiieucn.php` | 欧洲 QDII |
| `/woody/res/chinaindexcn.php` | A 股指数 |
| `/woody/res/spyfundcn.php` | 标普 500 相关 |
| `/woody/res/qqqfundcn.php` | 纳斯达克 100 相关 |
| `/woody/res/oilfundcn.php` | 原油相关 |
| `/woody/res/commoditycn.php` | 大宗商品/黄金 |
| `/woody/res/biotechcn.php` | 生物科技 |
| `/woody/res/chinainternetcn.php` | 中概互联 |
| `/woody/res/hangsengcn.php` | 恒生指数 |
| `/woody/res/hstechcn.php` | 恒生科技 |
| `/woody/res/hsharescn.php` | H 股指数 |
| `/woody/res/mscius50cn.php` | MSCI 美国 50 |
| **单只基金/LOF** | |
| `/woody/res/sz162411cn.php` | 单只 LOF（示例：华宝油气） |
| `/woody/res/sh513030cn.php` | 单只基金（示例：德国 DAX） |
| 其他单只 | `sz*.php` / `sh*.php` 为深市/沪市基金代码 |

### 3. 基金相关功能页（带 `?symbol=代码`）

| URL 路径 | 说明 |
|----------|------|
| `/woody/res/stockhistorycn.php` | 历史价格 |
| `/woody/res/fundhistorycn.php` | 基金溢价记录 |
| `/woody/res/netvaluehistorycn.php` | 净值记录 |
| `/woody/res/nvclosehistorycn.php` | 净值与价格比较 |
| `/woody/res/calibrationhistorycn.php` | 校准记录 |
| `/woody/res/fundpositioncn.php` | 仓位估算 |
| `/woody/res/fundaccountcn.php` | 场内申购账户 |
| `/woody/res/thanousparadoxcn.php` | 小心愿佯谬 |
| `/woody/res/holdingscn.php` | 基金持仓 |
| `/woody/res/fundsharecn.php` | 份额 |
| `/woody/res/ahhistorycn.php` | AH 历史比较 |

### 4. 自选与交易

| URL 路径 | 说明 |
|----------|------|
| `/woody/res/mystockcn.php` | 自选股票/基金 |
| `/woody/res/mystockgroupcn.php` | 自选分组 |
| `/woody/res/mystocktransactioncn.php` | 交易记录 |
| `/woody/res/myportfoliocn.php` | 组合 |
| `/woody/res/editstocktransactioncn.php` | 编辑交易 |
| `/woody/res/editstockgroupcn.php` | 编辑分组 |
| `/woody/res/submittransaction.php` | 提交交易（接口） |
| `/woody/res/submitgroup.php` | 提交分组（接口） |
| `/woody/res/submitnav.php` | 提交净值（接口） |
| `/woody/res/submithistory.php` | 提交历史（接口） |

### 5. 账户与工具（`/account/`）

| URL 路径 | 说明 |
|----------|------|
| `/account/logincn.php` | 登录 |
| `/account/registercn.php` | 注册 |
| `/account/profilecn.php` | 个人资料 |
| `/account/visitorcn.php` | **访问统计（按 IP）** |
| `/account/commentcn.php` | 全部评论 |
| `/account/ipcn.php` | IP 地址数据 |
| `/account/commonphrasecn.php` | 个人常用短语 |
| `/account/simpletestcn.php` | 简单测试 |
| `/account/benfordslawcn.php` | 本福特定律 |
| `/account/chisquaredtestcn.php` | Pearson 卡方检验 |
| `/account/cramersrulecn.php` | 解二元一次方程组 |
| `/account/dicecaptchacn.php` | 骰子验证码 |
| `/account/linearregressioncn.php` | 线性回归 |
| `/account/primenumbercn.php` | 分解质因数 |
| `/account/sinajscn.php` | 新浪股票接口 |
| `/account/updateemailcn.php` | 更新邮箱 |
| `/account/editprofilecn.php` | 编辑资料 |
| `/account/editcommentcn.php` | 编辑评论 |
| `/account/remindercn.php` | 提醒 |
| `/account/passwordcn.php` | 密码 |
| `/account/closeaccountcn.php` | 关闭账户 |

### 6. 博客（`/woody/blog/`）

| URL 路径 | 说明 |
|----------|------|
| `/woody/blog/entertainment/` | 娱乐分类 |
| `/woody/blog/ar1688/` | AR1688 等开发记录 |
| `/woody/blog/pa6488/` | PA6488 等 |
| `/woody/blog/palmmicro/` | Palm Micro 等 |
| 其他 | 按 `woody/blog/分类/日期cn.php` 形式 |

### 7. 其他

| URL 路径 | 说明 |
|----------|------|
| `/woody/mia/30days/indexcn.php` | Mia 30 天 |
| `/chishin/indexcn.php` | Chishin 首页 |
| `/woody/res/debugcn.php` | 调试页（若开放） |
| `/woody/res/uploadfile.php` | 上传文件（若开放） |

---

## 三、访问示例（项目在 `/web/` 时）

若站点根为 `http://localhost:8081/web/`，则：

- LOF 汇总：`http://localhost:8081/web/woody/res/lofcn.php`
- 单只 LOF：`http://localhost:8081/web/woody/res/sz162411cn.php`
- 访问统计：`http://localhost:8081/web/account/visitorcn.php`
- 历史价格：`http://localhost:8081/web/woody/res/stockhistorycn.php?symbol=SZ162411`

---

## 四、说明

- **中文页** 多为 `*cn.php`，英文为 `*.php`（无 `cn`）。
- **股票/基金代码** 通过 `symbol=` 传递，如 `?symbol=SZ162411`。
- **PATH_STOCK** 对应目录为 `woody/res/`，其下大量 `sz*.php`、`sh*.php` 为单只基金入口，具体列表见 `woody/res/` 目录或菜单中的 LOF/分组链接。
