# LOF 估值 Web 项目说明

本仓库是一个 **PHP 项目**，主要提供 A 股 **LOF（上市型开放式基金）** 及 **QDII 基金** 的净值估算工具。通过跟踪境外指数/ETF 或成分股持仓，结合汇率与校准因子，计算基金的官方估值、参考估值与实时估值。

- **数据库与部署**：见 [部署文档](docs/DEPLOY.md)（配置位置、数据库、部署步骤）。

---

## 一、项目结构概览

```
web/
├── php/                    # 核心 PHP 逻辑（股票、估值、SQL、UI）
│   ├── stock/              # 股票与基金估值核心
│   ├── ui/                 # 页面展示与表格
│   ├── sql/                # 数据库表与访问
│   └── ...
├── woody/                  # 站点页面与资源
│   ├── res/                # 各只基金/LOF 的入口页与 PHP 逻辑
│   │   ├── php/            # 各组/单只基金的 _qdii.php、_qdiigroup.php 等
│   │   ├── sz162411cn.php  # 单只 LOF 入口（如华宝油气）
│   │   ├── lofcn.php       # LOF 汇总页入口
│   │   └── ...
│   └── indexcn.php
└── README.md               # 本文件
```

---

## 二、LOF 相关逻辑位置总结

### 1. LOF 身份判定（代码区间）

| 文件 | 作用 |
|------|------|
| `php/stock/stocksymbol.php` | 根据代码区间判断是否为 LOF：<br>• **深市 LOF**：`_isDigitShenZhenLof()`，代码 160000–169999<br>• **沪市 LOF**：`_isDigitShangHaiLof()`，代码 500000–509999<br>• `IsLofA()`：深市或沪市 LOF 之一即为 A 股 LOF |

深市 LOF 判断（约 303–306 行）、沪市 LOF 判断（约 333–336 行）、`IsLofA()`（约 601–605 行）、LOF 默认仓位 `GetDefaultPosition()` 返回 0.95（约 895–898 行）。

### 2. 估值公式与校准（核心计算）

| 文件 | 作用 |
|------|------|
| `php/stock/qdiiref.php` | **QDII/LOF 估值核心**：<br>• `QdiiGetCalibration($strEst, $strCNY, $strNav)`：校准因子 = 参考标的净值 × 汇率 / 基金净值<br>• `QdiiGetVal($fEst, $fCny, $fFactor)`：估值 = 参考标的 × 汇率 / 校准因子<br>• `_QdiiReference::EstNetValue()`：官方估值计算<br>• `_QdiiReference::EstRealtimeNetValue()`：实时估值（叠加期货/ETF 实时价）<br>• `AdjustFactor()`：用最新公布净值与参考标的、汇率做自动校准并写入 `CalibrationSql` |
| `php/stock/fundref.php` | **基金基类**：<br>• `FundAdjustPosition` / `FundReverseAdjustPosition`：按仓位比例调整估值（公式注释在文件头部）<br>• `AdjustPosition($fVal)` / `ReverseAdjustPosition($fVal)`：使用 `RefGetPosition()` 的仓位与校准基准值做调整<br>• `fFactor` 来自 `CalibrationSql`（校准表） |

校准因子 `fFactor` 在 `php/stock/mysqlref.php` 中定义（约 8 行），由 `CalibrationSql` 读写；QDII 校准写入在 `qdiiref.php` 的 `AdjustFactor()` 中。

### 3. 参考标的与实时标的（QDII 映射）

| 文件 | 作用 |
|------|------|
| `php/stock/qdiiref.php` | **标的映射**：<br>• `QdiiGetEstSymbol($strSymbol)`：A 股代码 → 美股/指数代码（如华宝油气 → XOP，原油 → USO，标普 → ^GSPC 等）<br>• `QdiiGetRealtimeSymbol($strSymbol)`：实时估值用期货/ETF（如 hf_CL、hf_ES、hf_NQ）<br>• `QdiiHkGetEstSymbol` / `QdiiJpGetEstSymbol` / `QdiiEuGetEstSymbol`：港股、日本、欧洲 QDII 的参考标的 |

单只 LOF 的“估值逻辑”即：用上述映射得到的 `est_ref`（参考标的）价格 × 汇率 ÷ 校准因子，再经仓位调整。

### 4. 成分股持仓估值（多市场混合基金）

| 文件 | 作用 |
|------|------|
| `php/stock/holdingsref.php` | **按持仓比例估算净值**：<br>• 使用 `HoldingsDateSql`、`HoldingsSql` 取持仓比例与持仓日净值<br>• `_estNav()`：按各成分股涨跌幅 × 持仓比例汇总，再乘以 `RefGetPosition()` 仓位，得到新净值<br>• 用于中概互联等“美股+港股+A股”混合的 QDII，使参考估值能反映白天港股/A股波动 |

与“单一日度参考标的 + 汇率”的 QDII 估值形成互补。

### 5. 深市 LOF 份额数据

| 文件 | 作用 |
|------|------|
| `php/stock/szse.php` | **深市 LOF 份额**：<br>• `SzseGetLofShares($ref)`：仅对 `IsShenZhenLof()` 为真的基金，从深交所接口拉取当日 LOF 份额并写入 `SharesHistorySql`<br>• 用于展示或后续与规模相关的逻辑 |

调用处包括 `_qdiigroup.php`、`_qdiimix.php`、`_chinaindex.php`、`_fundshare.php` 等。

### 6. 仓位与套利

| 文件 | 作用 |
|------|------|
| `php/stock.php` | `RefGetPosition($ref)`：从 `FundPositionSql` 读仓位，若无则用 `$ref->GetDefaultPosition()`（LOF 默认 0.95） |
| `php/stock/fundref.php` | 仓位参与 `AdjustPosition` / `ReverseAdjustPosition`，影响最终显示的估值与反向换算 |

套利、交易展示在 `woody/res/php/_qdiigroup.php`（如 `EchoArbitrageParagraph`、`ConvertToEtfTransaction` / `ConvertToQdiiTransaction`）以及 UI 层 `php/ui/arbitrageparagraph.php` 等。

### 7. 单只 LOF 页与 LOF 汇总页

| 路径 | 作用 |
|------|------|
| `woody/res/sz162411cn.php` 等 | 单只 LOF 入口，例如华宝油气：`require('php/_qdii.php')`，由 `QdiiAccount` 创建 `QdiiReference` 并输出估值表、历史、链接等 |
| `woody/res/php/_qdii.php` | 单只 QDII/LOF 页逻辑：`QdiiAccount` → `QdiiReference`，`EchoFundEstParagraph($ref)` 等 |
| `woody/res/php/_qdiigroup.php` | 共用逻辑：预取数据、`SzseGetLofShares`、创建基金组、历史与套利展示 |
| `woody/res/lofcn.php` | LOF 汇总页：`require('php/_mystockgroup.php')`，通过分组 `lof` 展示“本网站有估值的 LOF 基金”列表 |

估值表格列（官方估值、参考估值、实时估值等）在 `php/ui/fundestparagraph.php` 中定义（`_echoFundEstTableItem`、`EchoFundEstParagraph`）。

### 8. 链接与菜单

| 文件 | 作用 |
|------|------|
| `php/stocklink.php` | `LOF_ALL_DISPLAY` 常量、`GetAllLofLink()`：指向 LOF 汇总页的链接 |
| `woody/res/php/_stockaccount.php` | 根据 `IsShenZhenLof()` / `IsShangHaiLof()` 显示深市/沪市 LOF 列表链接；菜单中 `GetAllLofLink()` |
| `php/stock/_stocklink.php`（res 下） | `case 'lof'` 时用 `IsLofA()` 筛选 A 股 LOF 等 |

### 9. 数据库与校准历史

- **校准历史**：`CalibrationSql` 存校准因子（按日/按标的），`php/ui/calibrationhistoryparagraph.php` 展示校准历史。
- **净值历史**：`FundEstSql`、`NavHistorySql` 等存估值结果与官方净值，用于对比与自动校准。
- **持仓、份额、仓位**：`HoldingsSql`、`SharesHistorySql`、`FundPositionSql` 等（见 `php/sql/` 及各 `*Sql` 类）。

### 10. 测试与外部数据

| 文件 | 作用 |
|------|------|
| `php/test/updatechinastock.php` | 新浪 `node=lof_hq_fund` 等，与 LOF 列表/更新相关（代码中 REGEXP 对 SH50/SZ16 的匹配） |

---

## 三、估值数据流简要

1. **单只 QDII/LOF 页**（如华宝油气）：  
   访问 → `QdiiAccount` 创建 → `QdiiReference` 构造时调用 `EstNetValue()` → 取参考标的价格、汇率、校准因子 → `GetQdiiValue()` 得到官方/参考估值 → 若有实时标的则 `EstRealtimeNetValue()` 得到实时估值 → 再经 `AdjustPosition()` 做仓位调整 → 在 `fundestparagraph` 中输出表格。

2. **自动校准**：  
   当有当日基金官方净值时，`UpdateOfficialNetValue()` 比较估值与官方净值，若满足条件则 `AdjustFactor()` 用“参考标的 × 汇率 / 基金净值”更新校准因子并写入 `CalibrationSql`。

3. **LOF 汇总**：  
   进入 `lofcn.php` → `_mystockgroup.php` 按分组 `lof` 取有估值的 LOF 列表 → 对每只调用 `StockGetFundReference` 得到 `FundReference`（含 QDII 等子类）→ `EchoFundArrayEstParagraph` 输出估值表。

---

## 四、安全与依赖说明

- 估值依赖**外部数据**（新浪、深交所、汇率、Yahoo 等），需注意接口可用性与频率限制。
- 密钥、数据库账号等应通过环境变量或配置管理，不要写死在代码中（符合项目安全基线）。
- 校准与净值写入涉及数据库写操作，应仅在受控流程（如管理员确认更新净值）中调用。

---

## 五、LOF 逻辑速查表

| 需求 | 主要文件 |
|------|----------|
| 判断是否为 LOF、代码区间 | `php/stock/stocksymbol.php` |
| 估值公式、校准、官方/实时估值 | `php/stock/qdiiref.php`、`php/stock/fundref.php` |
| 参考标的/实时标的映射 | `php/stock/qdiiref.php`（Qdii*GetEstSymbol / Qdii*GetRealtimeSymbol） |
| 成分股持仓估值 | `php/stock/holdingsref.php` |
| 深市 LOF 份额获取 | `php/stock/szse.php` → `SzseGetLofShares` |
| 仓位默认值及读取 | `php/stock.php`（RefGetPosition）、`stocksymbol.php`（GetDefaultPosition） |
| 单只 LOF 页逻辑 | `woody/res/php/_qdii.php`、`_qdiigroup.php` |
| LOF 汇总页 | `woody/res/lofcn.php`、`woody/res/php/_mystockgroup.php` |
| 估值表格与展示 | `php/ui/fundestparagraph.php` |
| LOF 链接与菜单 | `php/stocklink.php`、`woody/res/php/_stockaccount.php` |

以上即为本项目中与 **LOF 估值** 相关的主要逻辑位置与数据流总结。
