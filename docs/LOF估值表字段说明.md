# LOF 估值表字段计算逻辑说明

本文档说明从入口 **`…/woody/res/lofcn.php`** 打开的估值页面中，估值表各列的计算逻辑及所涉方法。表格由 `php/ui/fundestparagraph.php` 的 `_echoFundEstTableItem()` 输出，每行对应一只 LOF 基金，`$ref` 由 `StockGetFundReference($strSymbol)` 得到，类型因基金类别不同而不同。

---

## 一、估值表列与统一逻辑

当前 `lofcn.php` 的估值表由 `php/ui/fundestparagraph.php` 渲染，列定义在 `_getFundEstTableColumn()`。  
**现状是固定列模式**：`参考估值/溢价`、`实时估值/溢价` 两组列始终存在（无值时显示空白或 `—`）。

### 1.1 列顺序（窄屏/宽屏）

- **窄屏（`$bWide=false`）**：  
  `估值网页链接 | 名称 | 官方估值 | 日期 | 溢价 | 参考估值 | 溢价 | 实时估值 | 溢价`
- **宽屏（`$bWide=true`）**：  
  `估值网页链接 | 价格 | 涨跌幅 | 日期 | 时间 | 名称 | 官方估值 | 日期 | 溢价 | 参考估值 | 溢价 | 实时估值 | 溢价`

### 1.2 每列取值方法 + 内部调用 + 查表 + 写入触发

| 列 | 直接取值方法 | 内部关键调用 | 查询表 | 该表数据写入触发 |
|---|---|---|---|---|
| 估值网页链接 | `$ref->GetStockLink()` | `GetGroupStockLink` / `GetMyStockLink` | 无直接查表（仅符号分类/路由） | 无 |
| 名称 | `SqlGetStockName($ref->GetSymbol())`（无则 `GetChineseName()`） | `GetStockSql()->GetStockName` | `stock` | 新代码/新标的出现时由符号写入逻辑（如 `WriteSymbol/InsertSymbol`）补入 |
| 官方估值 | `$ref->GetOfficialNav()` | 见各 ref：`HoldingsReference::_estNav` / `FundPairReference::_estOfficialNetValue` / `_QdiiReference::EstNetValue` | 常用 `stockhistory`、`netvaluehistory`、`fundest`、`holdings`、`holdingsdate` | 官方估值算出后经 `StockUpdateEstResult()` 写 `fundest`（仅当 `netvaluehistory` 当天无正式净值） |
| 日期（官方） | `$ref->GetOfficialDate()` | 各 ref 自己决定（持仓法/配对法/QDII 法） | 常读 `fundest`、`netvaluehistory`、`stockhistory` | 来自估值链路本身；`fundest` 由 `StockUpdateEstResult()` 写入后可作为回退日期来源 |
| 溢价（官方） | `$ref->GetPercentageDisplay($strOfficialPrice)` | `GetPercentage` -> `StockGetPercentage`，分子默认 `GetPrice()`（场内价） | 无直接查表（主要用内存中的当前价） | 无 |
| 参考估值 | `$ref->GetFairNav()` | Holdings: `_estNav(false, ...)`；QDII: `EstRealtimeNetValue` 分支；FundPair 固定 false | 同官方估值链路相关表 | 一般不单独写库；展示为计算时结果 |
| 溢价（参考） | `$ref->GetPercentageDisplay($strFairPrice)` | 同官方溢价 | 无直接查表 | 无 |
| 实时估值 | `$ref->GetRealtimeNav()` | Holdings: `_estNav(false, true)`（非 A 股才有）；QDII: `EstRealtimeNetValue` | 常读 `stockhistory` + 实时行情；部分会用汇率 `netvaluehistory` | 一般不单独写库；展示为计算时结果 |
| 溢价（实时） | `$ref->GetPercentageDisplay($strRealtimePrice)` | 同官方溢价 | 无直接查表 | 无 |

**溢价计算公式（所有类型一致）**  
- 调用：`GetPercentageDisplay($strEst)`，内部用 `GetPercentage($strEst, $strDividend)`，未传第二参时 `$strDividend = $ref->GetPrice()`（场内价）。  
- 公式：`StockGetPercentage($strDivisor, $strDividend)` = **(场内价/估值 - 1) × 100%**。  
- 涉及方法：`php/stock/stockref.php` 的 `GetPercentage`、`GetPercentageDisplay`；`php/stock.php` 的 `StockGetPercentage`。

**价格展示**  
- 官方/参考/实时估值数值的展示：`$ref->GetPriceDisplay($strNav)`。  
- 涉及方法：`stockref.php` 的 `GetPriceDisplay`，`php/stock.php` 的 `StockGetPriceDisplay`。

---

## 二、基金类型与 ref 类型对应关系

`php/stock.php` 中 `StockGetFundReference($strSymbol)` 的分配逻辑如下。可把它理解为「按代码命中规则」决定属于哪一类 LOF 估值模型：

| 代码判断条件（按顺序） | ref 类型 | 对应 LOF 类型（业务含义） | 典型示例 |
|------------------------|----------|----------------------------|----------|
| `StockGetQdiiReference($strSymbol)` 非 false | 各类 **QdiiReference**（如 QdiiReference、QdiiHkReference、QdiiJpReference、QdiiEuReference） | **QDII 指数/商品型 LOF**：有明确「估值锚」（海外指数、ETF、期货等），通常可算官方/参考/实时三套估值 | 原油、黄金、标普、纳指等 QDII LOF |
| `in_arrayQdiiMix($strSymbol)` | **HoldingsReference** | **持仓混合型 LOF**（多见于 QDII 混合）：按披露持仓权重 + 成分行情 + 汇率做持仓法估值 | 中概互联等 |
| `in_arrayChinaIndex($strSymbol)` | **FundPairReference** | **配对映射型 LOF**：通过配对标的（指数/ETF/相关基金）映射换算净值 | 中国指数配对类（如 ASHR 配对） |
| 以上皆否 | **FundReference** | **普通 A 股 LOF（兜底类型）**：无专门估值子类时走基础 ref，是否有官方/参考/实时取决于外部是否写入 | 普通场内 LOF（非 QDII、非持仓混合、非配对） |

### 2.1 四种 ref 的“代码类型”具体说明

- **HoldingsReference（持仓估算法）**
  - 代码特征：命中 `in_arrayQdiiMix($strSymbol)`，会加载 holdings/holdingsdate。
  - 适用基金：持仓较透明、可由成分股与汇率重建净值的混合型 LOF（常见于部分 QDII 混合）。
  - 核心估值：`_estNav()`（按持仓权重合成）。

- **FundPairReference（配对估算法）**
  - 代码特征：命中 `in_arrayChinaIndex($strSymbol)`，依赖 `pair_ref` 配对标的。
  - 适用基金：与某个境外 ETF/指数存在稳定映射关系的指数类 LOF。
  - 核心估值：`EstFromPair()`（配对价格经汇率/因子换算）。

- **QdiiReference（指数锚估算法）**
  - 代码特征：`StockGetQdiiReference($strSymbol)` 返回具体 Qdii 子类。
  - 适用基金：有明确海外估值锚的 QDII LOF（指数、商品、区域市场等）。
  - 核心估值：`EstNetValue()` / `EstRealtimeNetValue()`（官方、参考、实时三层）。

- **FundReference（基础/兜底类型）**
  - 代码特征：上述三类都未命中时返回。
  - 适用基金：一般 A 股 LOF 或暂未配置专用估值模型的基金。
  - 核心特点：自身不“主动计算”估值，主要读取成员变量（是否有值取决于外部流程）。

---

## 三、按 ref 类型分列的计算逻辑与方法

### 3.1 估值网页链接（所有类型）

- **计算逻辑**：若该基金属于某「分组页」（如 lof、qdii 等），则链接到分组页；否则链接到该基金的 mystock 页。
- **涉及方法**：
  - `$ref->GetStockLink()`（`php/stock/stockref.php`）
  - `GetGroupStockLink($this->GetSymbol())`（`php/stocklink.php`）：分组链接
  - `$ref->GetMyStockLink()` → `GetMyStockLink($this->GetSymbol(), $this->GetDisplay())`：单基金页链接

---

### 3.2 HoldingsReference（持仓估算，如中概互联等 QDII 混合）

**官方估值**

- **计算逻辑**：取「官方估值日期」下的持仓法估算净值。先定 `GetOfficialDate()`，再在该日期上算 `_estNav($strDate, $bStrict)`；若汇率未配置则返回 false，页面显示「请配置汇率」。
- **涉及方法**：
  - `GetOfficialNav($bStrict)`（`php/stock/holdingsref.php`）
  - `GetOfficialDate()`：A 股取持仓成分股中最晚交易日，并结合 USCNY 该日是否有汇率
  - `_estNav($strDate, $bStrict)`：基于持仓权重与成分股涨跌幅、汇率调整（GetAdjustHkd/GetAdjustCny）合成净值
  - `GetAdjustHkd($strDate)` / `GetAdjustCny($strDate)`：汇率折算；`_getCnyRate`、净值表取 USCNY/HKCNY
  - `StockUpdateEstResult()`：写入 fundest 表（现已加空日期保护：`strDate` 为空则跳过写库并记 debug 日志）

**日期（GetOfficialDate）**

- **计算逻辑**：即上述 `GetOfficialDate()`，详见下文「四、GetOfficialDate 获取逻辑与相关表」。
- **涉及方法**：`GetOfficialDate()`、`_getEstDate()`（港股/美股成分股日期）、`uscny_ref->GetClose($strDate)`、`fund_est_sql->GetDatePrev()`。

**参考估值**

- **计算逻辑**：当「当前汇率/成分股日期」与官方估值日期不一致时，用**当前时点**再算一遍持仓法估值（不指定日期），作为参考估值。
- **涉及方法**：
  - `GetFairNav($bStrict)`（`holdingsref.php`）
  - `_estNav(false, $bStrict)`（`$strDate = false` 表示用当前价格与汇率）

**实时估值**

- **计算逻辑**：仅非 A 股 LOF（如场内为港股等）时存在；用 `_estNav(false, true)`，`bStrict = true` 时对港股二次上市成分用美股价格替代。
- **涉及方法**：`GetRealtimeNav()`、`_estNav(false, true)`、`GetSecondaryListingArray()`、`_getStrictRef()`。

**溢价**  
同上：`GetPercentageDisplay(估值)`，分母为估值、分子为场内价 `GetPrice()`。

---

### 3.3 FundPairReference（配对估算，如中国指数类）

**官方估值**

- **计算逻辑**：用「配对标的」当日净值/价格，通过汇率与校准因子换算成该 LOF 的 A 股净值。无配对标的时直接返回 false。
- **涉及方法**：
  - `GetOfficialNav()`（`php/stock/fundpairref.php`）
  - `$this->pair_ref === false` 时 return false
  - `$this->strOfficialDate = $this->pair_ref->GetDate()`：以配对标的日期为官方日期
  - `_estOfficialNetValue($strCny)`：内部 `EstFromPair($strEst, $strCny)`，用 `PairNavGetClose(pair_nav_ref, strOfficialDate)` 或 `pair_ref->GetPrice()` 取配对净值/价，再按公式换算
  - `GetFactor`、`_adjustByCny`、`StockUpdateEstResult()`

**日期**

- **计算逻辑**：即配对标的的日期 `$this->strOfficialDate`（来自 `pair_ref->GetDate()`）。
- **涉及方法**：`GetOfficialDate()`、`pair_ref->GetDate()`。

**参考估值 / 实时估值**

- **计算逻辑**：此类不提供参考估值与实时估值。
- **涉及方法**：`GetFairNav()` 固定返回 false；无 `GetRealtimeNav()`。

**溢价**  
同上：`GetPercentageDisplay(官方估值)`，与场内价比较。

---

### 3.4 QdiiReference 系（_QdiiReference 子类，如原油/黄金/标普/纳指等 QDII）

**官方估值**

- **计算逻辑**：以「估值指数/ETF」当日价格（或库中净值）乘当日汇率换算为人民币净值，并做校准；结果写入 `fOfficialNetValue`、`strOfficialDate`。
- **涉及方法**：
  - `EstNetValue()`（`php/stock/qdiiref.php` 中 _QdiiReference）
  - `GetEstRef()`：估值用指数/ETF ref
  - `_getEstVal($strDate)`：指数/ETF 在 $strDate 的净值或价格（SqlGetNavByDate、est_ref->GetPrice()、GetClosePrev 等）
  - `GetQdiiValue($strEstVal, $strOfficialCNY)`：汇率换算与校准
  - `cny_ref->GetClose($strDate)`：当日汇率
  - `GetOfficialNav()`：返回 `fOfficialNetValue`（FundReference）

**日期**

- **计算逻辑**：与官方估值同一天，即 `est_ref->GetDate()` 或库中 fundest 记录日期。
- **涉及方法**：`GetOfficialDate()`、`strOfficialDate`（在 EstNetValue 中赋值）。

**参考估值**

- **计算逻辑**：当「汇率或指数日期」与官方估值日不同时，用当前指数价格与当前汇率再算一次人民币估值。
- **涉及方法**：
  - `EstRealtimeNetValue()` 内：`GetDate() != strOfficialDate` 或 `est_ref->GetDate() != strOfficialDate` 时
  - `_getEstVal($strDate)`、`GetQdiiValue($strEstVal)`（用当前 cny_ref）
  - `GetFairNav()`：返回 `fFairNetValue`

**实时估值**

- **计算逻辑**：若有实时源（如期货、RT ETF），用实时价通过 EstByEtf 等得到实时指数估值，再乘汇率得到实时人民币净值。
- **涉及方法**：
  - `EstRealtimeNetValue()`：`GetRealtimeRef()`、`rt_etf_ref`、`realtime_ref->LoadEtfFactor()`、`EstByEtf()`
  - `GetQdiiValue(strval($fRealtime))` 写入 `fRealtimeNetValue`
  - `GetRealtimeNav()`：返回 `fRealtimeNetValue`

**溢价**  
同上：三列溢价均为 `GetPercentageDisplay(对应估值)`，与场内价比较；有 `stock_ref` 时用 `stock_ref->GetPercentageDisplay`（FundReference）。

---

### 3.5 FundReference（普通 LOF，无子类估值逻辑时）

- **官方估值 / 日期**：来自 `fOfficialNetValue`、`strOfficialDate`，若未由外部或子类赋值则为 false/空，页面不展示或显示「—」。
- **参考估值 / 实时估值**：来自 `fFairNetValue`、`fRealtimeNetValue`，同样可能未设置。
- **涉及方法**：`GetOfficialNav()`、`GetOfficialDate()`、`GetFairNav()`、`GetRealtimeNav()`（`php/stock/fundref.php`），仅读成员变量。

---

## 四、GetOfficialDate 获取逻辑与相关表

### 4.1 各 ref 类型下 GetOfficialDate 的获取方式

| ref 类型 | 适用 LOF 类型 | 获取逻辑 | 可能为空/无日期的原因 |
|----------|----------------|----------|------------------------|
| **HoldingsReference** | 持仓混合型 LOF（常见于可持仓重建净值的 QDII 混合） | ① 先取 `$strDate = $this->GetDate()`（本基金净值 ref 的日期，来自 **netvaluehistory** 该基金最新一条记录的 date）。② 若是 A 股 LOF，用 `_getEstDate()` 覆盖：取持仓成分里**港股**和**美股**各自最新交易日的较晚者（无港股用美股）。③ 若该日 `uscny_ref->GetClose($strDate)` 在 **netvaluehistory** 中无 USCNY 记录，则用 `fund_est_sql->GetDatePrev($stockId, $strDate)` 从 **fundest** 表取「该基金在 $strDate 之前的最近一条估值记录」的 date，作为官方日期。④ 返回上述 $strDate。 | ① 本基金在 netvaluehistory 无记录 → GetDate() 为空。② 无港股/美股成分或成分 ref 无日期 → _getEstDate() 为 false，沿用 GetDate() 若也为空则整体为空。③ fundest 无该基金历史且当日无 USCNY → GetDatePrev 可能为 false，最终返回的 $strDate 仍可能为空。 |
| **FundPairReference** | 配对映射型 LOF（通过配对标的换算净值的指数类） | **不单独算日期**：在 `GetOfficialNav()` 内先设 `$this->strOfficialDate = $this->pair_ref->GetDate()`（配对标的的行情/净值日期）。若当日无汇率，则从 **fundest** 取该基金最新一条记录的 date 作为 strOfficialDate。`GetOfficialDate()` 直接返回成员 `$this->strOfficialDate`。 | ① 无配对标的（pair_ref === false）时不会进 GetOfficialNav，strOfficialDate 从未被赋值 → 为空。② 配对标的无行情/净值日期且 fundest 无该基金记录 → 可能仍为空。 |
| **QdiiReference 系** | QDII 指数/商品型 LOF（有海外指数/ETF/期货估值锚） | 在 `EstNetValue()` 里赋值：有当日汇率时 `$this->strOfficialDate = $est_ref->GetDate()`（估值用指数/ETF 的日期）；无当日汇率时从 **fundest** 的 `GetRecordNow($stockId)` 取 `record['date']`。`GetOfficialDate()` 返回该成员。 | ① 未调用过 EstNetValue()（如未跑过估值流程）→ strOfficialDate 未赋值。② 估值指数/ETF 无日期且 fundest 无该基金记录 → 为空。 |
| **FundReference** | 普通 A 股 LOF（兜底类型，未配置专门估值模型） | 仅返回成员 `$this->strOfficialDate`，由子类或外部在算官方估值时赋值；基类构造里不设。 | 普通 LOF 若从未写入过官方估值或未走 Qdii 等子类逻辑，strOfficialDate 一直未设 → 为空。 |

### 4.2 相关数据库表

以下表与「官方估值」及 **GetOfficialDate** 的读写直接相关（表名以代码中实际使用为准）。

| 表名 | 用途 | 与 GetOfficialDate / 官方估值的关系 |
|------|------|--------------------------------------|
| **fundest** | 估值结果表（Sql 类：`FundEstSql`，见 `php/sql/sqldailytime.php`）。字段含 key（stock_id）、date、close、time。 | 写入：`StockUpdateEstResult()` 在算出官方估值后写入 (stock_id, date, close)。读取：HoldingsReference 用 `GetDatePrev(stockId, strDate)` 取「该基金在 strDate 之前的最近一条记录」的 date，用于无当日汇率时回退日期；FundPairReference / QdiiReference 用 `GetRecordNow(stockId)` 取最新一条的 date 作为 strOfficialDate（无配对日期或无当日汇率时）。 |
| **netvaluehistory** | 净值/汇率历史（Sql 类：`NavHistorySql` 等，见 `php/sql/sqlstocksymbol.php`）。按 stock_id + date 存净值或汇率（如 USCNY、HKCNY、各基金净值）。 | 基金自身最新净值日期：HoldingsReference 的 `GetDate()` 来自本基金在此表的最新记录。汇率：`uscny_ref->GetClose(strDate)` 查 USCNY 在 strDate 的汇率，用于判断「该日是否有汇率」并参与持仓法折算。QDII 的 cny_ref 同样从此表取汇率。 |
| **stock** | 股票/基金代码与 id（Sql 类：`StockSql` 等）。 | `SqlGetStockId(symbol)`、各 ref 的 `GetStockId()` 依赖此表；fundest / netvaluehistory 的 key 均为 stock_id。 |
| **holdings** / **holdingsdate** | 持仓明细与持仓日期（Sql 类：`HoldingsSql`、`HoldingsDateSql`，见 `php/sql/sqlholdings.php`、`php/sql/sqldate.php`）。 | 仅 **HoldingsReference**：用 holdingsdate 取该基金的 `strHoldingsDate`，用 holdings 取持仓比例与成分；成分的行情日期参与 _getEstDate()，间接影响官方日期的选取（不直接存日期，但成分 ref 的 GetDate() 来自行情/净值）。 |
| **stockhistory** | 行情历史（复权价等）。 | 持仓成分股、配对标的、估值指数/ETF 的 `GetDate()`、`GetClose(date)`、`GetAdjClose()` 等来自此表或与净值表配合；用于 _estNav、EstFromPair、_getEstVal 等，从而决定「用哪一天」的价来算官方估值，进而影响写入 fundest 的 date。 |

### 4.3 关键写入触发点（按 `lofcn.php` 页面链路）

| 写入对象 | 触发方法 | 触发位置 |
|---|---|---|
| `netvaluehistory`（USCNY/HKCNY/EUCNY/JPCNY） | `_chinaMoneyInsertData()` | `woody/res/php/_mystockgroup.php` 在渲染前调用 `GetChinaMoney(new CnyReference('USCNY'))` |
| `fundest`（估值结果） | `StockUpdateEstResult()` -> `FundEstSql::WriteDaily()` | 各 ref 的 `GetOfficialNav()` 计算成功时触发（Holdings/FundPair/QDII） |
| `netvaluehistory`（基金正式净值） | `StockCompareEstResult()` -> `NavHistorySql::InsertDaily()` | 校准/净值比较流程触发（非估值表每次必触发） |

**小结**：官方估值对应的「日期」在多数情况下 = 用来算该笔估值的那一天的交易日（成分股/配对标的/估值指数的日期，或该日无汇率时从 fundest 回退的上一次估值日）。有的基金没有日期，是因为：该 ref 类型下 strOfficialDate 从未被赋值（未算过官方估值、无配对、或 fundest/netvaluehistory 无可用记录）。

---

## 五、方法索引（文件级）

| 方法 / 函数 | 文件 | 作用 |
|-------------|------|------|
| `GetStockLink` | php/stock/stockref.php | 估值页/分组链接 |
| `GetGroupStockLink` | php/stocklink.php | 分组页链接 |
| `GetMyStockLink` | php/stocklink.php | 单基金 mystock 链接 |
| `GetOfficialNav` | holdingsref.php / fundpairref.php / fundref.php | 官方估值 |
| `GetOfficialDate` | 同上 | 官方估值日期 |
| `GetFairNav` | holdingsref.php / fundref.php（FundPairReference 恒为 false） | 参考估值 |
| `GetRealtimeNav` | holdingsref.php / fundref.php | 实时估值 |
| `_estNav` | php/stock/holdingsref.php | 持仓法估算净值 |
| `GetAdjustHkd` / `GetAdjustCny` | holdingsref.php | 汇率调整系数 |
| `_getCnyRate` | holdingsref.php | 从 ref 或库取 USCNY/HKCNY |
| `_estOfficialNetValue` / `EstFromPair` | fundpairref.php | 配对法官方估值 |
| `EstNetValue` / `EstRealtimeNetValue` | qdiiref.php | QDII 官方/参考/实时估值 |
| `GetQdiiValue` | qdiiref.php 及子类 | QDII 汇率换算 |
| `GetPercentage` / `GetPercentageDisplay` | php/stock/stockref.php | 溢价计算与展示 |
| `StockGetPercentage` | php/stock.php | (市价/估值 - 1)×100 |
| `GetPriceDisplay` | php/stock/stockref.php、fundref.php | 价格展示 |
| `StockUpdateEstResult` | 各 ref 内调用 | 写入 fundest 表 |

---

## 六、页面与数据流简述

1. **入口**：`…/woody/res/lofcn.php` → `_mystockgroup.php` → `_echoStockGroupArray($arStock, $bWide)`。
2. 对每组中的 A 股基金：`$fund = StockGetFundReference($strSymbol)`，满足 `RefHasData($fund)` 的放入 `$arFund`。
3. **输出表格**：`EchoFundArrayEstParagraph($arFund, ...)` → `_echoFundEstParagraph` → 对每个 `$ref` 调用 `_echoFundEstTableItem($ref, $bFair, $bWide)`。
4. **列顺序**：估值网页链接 → 官方估值 → 日期 → 溢价 → 参考估值 → 溢价 → 实时估值 → 溢价（当前固定列模式，缺值显示空白/`—`）。

以上即 LOF 估值表各字段的计算逻辑及所涉方法，按基金类型分别对应 HoldingsReference、FundPairReference、QdiiReference 系与 FundReference。

---

## 七、LOF 页面基金清单（33 只）与逐只计算口径

本节按 `GetCategoryArray('lof')` 的实际筛选逻辑（`GetAllSymbolArray()` + `IsLofA()`）统计当前 LOF 页面基金。  
每只基金在页面上的各列都走同一套渲染入口：`_echoFundEstTableItem($ref, ...)`，差异只在 `$ref` 类型和其估值方法。

### 7.1 HoldingsReference（4 只，持仓法）

| 基金代码 | ref 类型 | 官方估值列 | 官方日期列 | 参考估值列 | 实时估值列 | 主要依赖 |
|---|---|---|---|---|---|---|
| SH501225 | HoldingsReference | `GetOfficialNav()->_estNav($strDate)` | `GetOfficialDate()` | `GetFairNav()->_estNav(false)` | 非 A 股时 `GetRealtimeNav()->_estNav(false,true)`；A 股一般为空 | `holdings/holdingsdate` + `stockhistory` + `netvaluehistory(USCNY/HKCNY)` |
| SH501312 | HoldingsReference | 同上 | 同上 | 同上 | 同上 | 同上 |
| SZ160644 | HoldingsReference | 同上 | 同上 | 同上 | 同上 | 同上 |
| SZ164906 | HoldingsReference | 同上 | 同上 | 同上 | 同上 | 同上 |

### 7.2 FundPairReference（1 只，配对法）

| 基金代码 | ref 类型 | 官方估值列 | 官方日期列 | 参考估值列 | 实时估值列 | 主要依赖 |
|---|---|---|---|---|---|---|
| SH501043 | FundPairReference | `GetOfficialNav()->_estOfficialNetValue()->EstFromPair()` | `pair_ref->GetDate()`（无当日汇率时回退 `fundest` 最新） | 固定 false | 无 | `fundpair` + `stockhistory/netvaluehistory` + `calibrationhistory` + `fundest` |

### 7.3 QdiiReference（22 只，美元口径 QDII）

| 基金代码 | 估值锚（`QdiiGetEstSymbol`） | 实时锚（`QdiiGetRealtimeSymbol`） | ref 类型 |
|---|---|---|---|
| SH501018 | USO | hf_CL | QdiiReference |
| SH501300 | AGG | 无 | QdiiReference |
| SZ160140 | SCHH | 无 | QdiiReference |
| SZ160216 | USO | hf_CL | QdiiReference |
| SZ160416 | IXC | hf_CL | QdiiReference |
| SZ160719 | GLD | hf_GC | QdiiReference |
| SZ160723 | USO | hf_CL | QdiiReference |
| SZ161116 | GLD | hf_GC | QdiiReference |
| SZ161125 | ^GSPC | hf_ES | QdiiReference |
| SZ161126 | XLV | 无 | QdiiReference |
| SZ161127 | XBI | 无 | QdiiReference |
| SZ161128 | XLK | 无 | QdiiReference |
| SZ161129 | USO | hf_CL | QdiiReference |
| SZ161130 | ^NDX | hf_NQ | QdiiReference |
| SZ161815 | GSG | 无 | QdiiReference |
| SZ162411 | XOP | hf_CL | QdiiReference |
| SZ162415 | XLY | 无 | QdiiReference |
| SZ162719 | IEO | hf_CL | QdiiReference |
| SZ163208 | XLE | 无 | QdiiReference |
| SZ164701 | GLD | hf_GC | QdiiReference |
| SZ164824 | INDA | znb_SENSEX | QdiiReference |
| SZ165513 | GSG | 无 | QdiiReference |

上述 22 只在列级别统一为：
- **官方估值列**：`EstNetValue()`（可写 `fundest`）  
- **官方日期列**：`strOfficialDate`（由 `est_ref->GetDate()` 或 `fundest` 回退）  
- **参考估值列**：`EstRealtimeNetValue()` 中 `fFairNetValue`（日期不一致时）  
- **实时估值列**：`EstRealtimeNetValue()` 中 `fRealtimeNetValue`（有实时锚时）

### 7.4 QdiiHkReference（6 只，港币口径 QDII）

| 基金代码 | 估值锚（`QdiiHkGetEstSymbol`） | 实时锚（`QdiiHkGetRealtimeSymbol`） | ref 类型 |
|---|---|---|---|
| SH501025 | SH000869 | 无 | QdiiHkReference |
| SH501302 | ^HSI | hf_HSI | QdiiHkReference |
| SZ160717 | ^HSCE | 无 | QdiiHkReference |
| SZ160924 | ^HSI | hf_HSI | QdiiHkReference |
| SZ161831 | ^HSCE | 无 | QdiiHkReference |
| SZ164705 | ^HSI | hf_HSI | QdiiHkReference |

上述 6 只的列级逻辑与 QdiiReference 相同，但汇率 ref 改为 `HKCNY`。

---

## 八、依赖表的数据来源与触发时机（面向“什么时候触发计算依赖数据”）

### 8.1 依赖表 -> 数据来源 -> 触发

| 表 | 数据内容 | 数据来源（外部） | 代码写入入口 | 触发时机 |
|---|---|---|---|---|
| `stock` | 符号与名称 | 新浪/雅虎等行情解析后的符号与名称 | `StockSql::WriteSymbol/InsertSymbol` | 新符号首次出现、更新脚本或页面链路预取时 |
| `stockhistory` | 行情历史（OHLC/adjclose） | 新浪行情、雅虎历史等 | `StockHistorySql::WriteHistory` 等 | 历史更新流程（脚本/后台任务/手工更新） |
| `netvaluehistory` | 基金净值/汇率历史（USCNY/HKCNY/EUCNY/JPCNY） | 中国货币网汇率、基金净值源（深交所/雅虎等） | `NavHistorySql::InsertDaily/WriteDaily` | 访问估值页面时 `GetChinaMoney()` 会尝试补汇率；净值更新流程另行触发 |
| `holdingsdate` | 基金持仓日期 | 基金公司披露/CSV/交易所持仓文件 | `HoldingsDateSql::WriteDate` | 持仓导入流程（`_submitstockoptions.php`、`_kraneholdingscsv.php`、`_qdiimix.php`） |
| `holdings` | 持仓成分及权重 | 同上 | `HoldingsSql::InsertHolding/InsertHoldingsArray` | 持仓导入流程 |
| `calibrationhistory` | 校准因子 | 由基金净值与配对锚反推 | `CalibrationSql::WriteDaily` | QDII/配对基金校准流程运行时（构造 ref 或手工校准） |
| `fundest` | 官方估值快照 | 由估值算法计算而来（非外部原始源） | `StockUpdateEstResult()->FundEstSql::WriteDaily` | `GetOfficialNav()/EstNetValue()` 成功计算且当日正式净值尚未入库时 |

### 8.2 LOF 页面一次请求里的即时触发链路

1. 入口 `lofcn.php` -> `_mystockgroup.php::_echoStockGroupArray`。  
2. 先执行 `GetChinaMoney(new CnyReference('USCNY'))`：若 `netvaluehistory` 缺 USCNY/HKCNY 且时间 >= 9:15，会请求中国货币网并写库。  
3. 执行 `StockPrefetchArrayExtendedData($arStock)`：预取所需基金、估值锚、实时锚行情（新浪接口，本地 debug 文件节流缓存）。  
4. 对每个 LOF 构建 `$fund = StockGetFundReference($symbol)`：  
   - QDII/QDII-HK 在构造时即 `EstNetValue()`，可更新 `fundest`；  
   - 持仓法/配对法在渲染列时调用 `GetOfficialNav()`，可更新 `fundest`。  
5. `_echoFundEstTableItem` 输出：官方估值/日期/溢价 + 参考估值/溢价 + 实时估值/溢价。

### 8.3 各列“依赖数据是否必须当次触发”

| 列 | 依赖是否必须在本次请求实时拉取 | 说明 |
|---|---|---|
| 官方估值 | 否 | 可直接用当次行情计算，也可在缺汇率/缺校准时回退 `fundest` 最新记录 |
| 官方日期 | 否 | 可能来自当次锚日期，也可能来自 `fundest` 回退日期 |
| 参考估值 | 通常是 | 依赖“当前价/当前汇率”与官方日期不一致时才出现 |
| 实时估值 | 是（有实时锚时） | 需实时锚（如 `hf_CL/hf_ES/hf_NQ/hf_HSI`）可用，否则为空 |
| 溢价（三列） | 是（取当前场内价） | 用当前 `GetPrice()` 与对应估值计算 `(场内价/估值 - 1) * 100%` |

---

## 九、按你提供的 2026-03-12 14:54 快照逐只归因

说明：本节对应你贴出的 33 只 LOF 当日表格，给出“该行每列值来自哪条估值链路”的落地映射。  
其中三列溢价统一公式不变：`(场内价格 / 对应估值 - 1) * 100%`（`GetPercentageDisplay` -> `StockGetPercentage`）。

### 9.1 持仓法（HoldingsReference，4 只）

| 代码 | 你给出的表现 | 列值归因 |
|---|---|---|
| SZ164906 | 官方估值=价格，官方日期=当日，参考/实时为空 | 命中 `GetOfficialDate()==当日` 且 `uscny日期==officialDate`、`_getEstDate()==officialDate`，所以 `GetFairNav()` 返回 false；A 股 `GetRealtimeNav()` 固定 false。 |
| SH501312 | 官方估值=价格，官方日期=当日，参考/实时为空 | 同上。 |
| SZ160644 | 官方估值=价格，官方日期=当日，参考/实时为空 | 同上。 |
| SH501225 | 官方/参考同值，且显著溢价 | `GetFairNav()` 触发后与 `GetOfficialNav()`结果接近（当次输入相同）；实时列为空是 A 股持仓法基金特性。 |

### 9.2 配对法（FundPairReference，1 只）

| 代码 | 你给出的表现 | 列值归因 |
|---|---|---|
| SH501043 | 官方估值有值但“官方日期”空，参考/实时空 | `GetOfficialNav()` 可由 `pair_ref->GetPrice()`算出值；若 `pair_ref->GetDate()` 为空（且 `fundest` 无可回退日期），则 `strOfficialDate` 为空，导致日期列空白。`GetFairNav()`固定 false。 |

### 9.3 QDII-港币口径（QdiiHkReference，6 只）

代码：`SH501025`、`SH501302`、`SZ160717`、`SZ160924`、`SZ161831`、`SZ164705`。  

你给出的共同表现：官方日期多为 `2026-03-11`，参考估值和实时估值多数为空。  
归因：
- 官方估值：`EstNetValue()` 用 `est_ref`（`^HSI/^HSCE/SH000869`）和 `HKCNY` 算出 `fOfficialNetValue`。  
- 官方日期：来自 `est_ref->GetDate()`，所以常晚于/早于 A 股当日。  
- 参考估值：只有当“当前汇率或估值锚日期 != 官方日期”才出；你这批里多数未触发。  
- 实时估值：仅有实时锚的品种（如恒指 `hf_HSI`）才可能有值；其余为空是预期行为。

### 9.4 QDII-美元口径（QdiiReference，22 只）

#### A. 实时列为空（无实时锚或当次未形成有效实时估值）

`SZ164824`、`SZ161127`、`SZ160140`、`SZ165513`、`SH501300`、`SZ162415`、`SZ161126`、`SZ161815`、`SZ161128`。  

归因：这些代码要么 `QdiiGetRealtimeSymbol()` 本身返回 false，要么实时链路未形成有效 `fRealtimeNetValue`，因此实时估值显示 `—`。

#### B. 实时列有值（存在实时锚并算出 `fRealtimeNetValue`）

`SZ160719`、`SZ164701`、`SZ160216`、`SZ161116`、`SZ161130`、`SZ163208`、`SZ161125`、`SZ162719`、`SZ162411`、`SZ160723`、`SH501018`、`SZ160416`、`SZ161129`。  

归因：这些代码命中实时锚（如 `hf_GC/hf_CL/hf_NQ/hf_ES`），`EstRealtimeNetValue()` 成功计算：
- 参考估值列：`fFairNetValue`（当前锚/汇率与官方日期不一致时）  
- 实时估值列：`fRealtimeNetValue`（实时锚换算后）  

---

### 9.5 你这份数据里“空值/日期差异”的判定规则汇总

| 现象 | 直接原因 | 对应代码路径 |
|---|---|---|
| 参考估值空 | `GetFairNav()`返回 false（未触发“日期不一致”条件） | `holdingsref.php::GetFairNav` / `qdiiref.php::EstRealtimeNetValue` |
| 实时估值空 | 无实时锚，或实时链路未得到有效值 | `QdiiGetRealtimeSymbol` + `EstRealtimeNetValue` |
| 官方日期不是当天 | 官方锚日期来自海外市场/汇率可用日期 | `EstNetValue` 内 `strOfficialDate = est_ref->GetDate()` |
| 官方日期空但官方估值有值 | 价格可算、日期不可得（或无可回退记录） | `fundpairref.php::GetOfficialNav` |

### 9.6 33 行逐行一对一（按你贴出的顺序）

说明：  
- **官方列方法** = 官方估值/官方日期对应的主方法。  
- **参考列方法** = 参考估值列（含空值条件）。  
- **实时列方法** = 实时估值列（含空值条件）。  
- **依赖表** 仅列核心计算依赖（不含 `stock` 基础字典表）。

| 代码 | ref 类型 | 官方列方法（估值/日期） | 参考列方法 | 实时列方法 | 依赖表 |
|---|---|---|---|---|---|
| SZ164824 | QdiiReference | `EstNetValue` -> `GetQdiiValue`；日期=`strOfficialDate` | `EstRealtimeNetValue` 中 `fFairNetValue`（条件触发） | `QdiiGetRealtimeSymbol=znb_SENSEX`，当次未形成有效实时值则 `—` | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ161127 | QdiiReference | 同 QDII 官方链路 | 同上 | `QdiiGetRealtimeSymbol=false`，固定 `—` | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ160140 | QdiiReference | 同 QDII 官方链路 | 同上 | `QdiiGetRealtimeSymbol=false`，固定 `—` | 同上 |
| SZ164906 | HoldingsReference | `GetOfficialNav` -> `_estNav(GetOfficialDate())`；日期=`GetOfficialDate` | `GetFairNav`（若 `uscny/date` 与 `officialDate` 不同才有值） | `GetRealtimeNav`（A 股返回 false） | `holdings`,`holdingsdate`,`stockhistory`,`netvaluehistory`,`fundest` |
| SZ160719 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `QdiiGetRealtimeSymbol=hf_GC`，`EstRealtimeNetValue` 生成实时值 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SH501302 | QdiiHkReference | `EstNetValue`（HK 口径）日期来自 `est_ref->GetDate` | `EstRealtimeNetValue` 中 `fFairNetValue`（条件触发） | `QdiiHkGetRealtimeSymbol=hf_HSI`，未命中则 `—` | `stockhistory`,`netvaluehistory(HKCNY)`,`calibrationhistory`,`fundest` |
| SZ164701 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_GC` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ165513 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `QdiiGetRealtimeSymbol=false`，固定 `—` | 同上 |
| SH501312 | HoldingsReference | `GetOfficialNav` -> `_estNav(GetOfficialDate())` | `GetFairNav`（条件触发） | A 股 `GetRealtimeNav=false` | `holdings`,`holdingsdate`,`stockhistory`,`netvaluehistory`,`fundest` |
| SZ160924 | QdiiHkReference | HK 口径 `EstNetValue` | HK 口径 `fFairNetValue` | `hf_HSI` 实时链路（未命中则 `—`） | `stockhistory`,`netvaluehistory(HKCNY)`,`calibrationhistory`,`fundest` |
| SZ160717 | QdiiHkReference | HK 口径 `EstNetValue` | HK 口径 `fFairNetValue` | `QdiiHkGetRealtimeSymbol=false`，固定 `—` | 同上 |
| SH501300 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `QdiiGetRealtimeSymbol=false`，固定 `—` | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ164705 | QdiiHkReference | HK 口径 `EstNetValue` | HK 口径 `fFairNetValue` | `hf_HSI` 实时链路（未命中则 `—`） | `stockhistory`,`netvaluehistory(HKCNY)`,`calibrationhistory`,`fundest` |
| SZ161831 | QdiiHkReference | HK 口径 `EstNetValue` | HK 口径 `fFairNetValue` | `QdiiHkGetRealtimeSymbol=false`，固定 `—` | 同上 |
| SZ162415 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `QdiiGetRealtimeSymbol=false`，固定 `—` | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SH501043 | FundPairReference | `GetOfficialNav` -> `_estOfficialNetValue` -> `EstFromPair`；日期=`pair_ref->GetDate` 或 `fundest` 回退 | `GetFairNav` 固定 false | 无实时估值方法 | `fundpair`,`stockhistory/netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ160216 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_CL` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SH501025 | QdiiHkReference | HK 口径 `EstNetValue`（估值锚 SH000869） | HK 口径 `fFairNetValue` | `QdiiHkGetRealtimeSymbol=false`，固定 `—` | `stockhistory`,`netvaluehistory(HKCNY)`,`calibrationhistory`,`fundest` |
| SZ161126 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `QdiiGetRealtimeSymbol=false`，固定 `—` | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ160644 | HoldingsReference | `GetOfficialNav` -> `_estNav(GetOfficialDate())` | `GetFairNav`（条件触发） | A 股 `GetRealtimeNav=false` | `holdings`,`holdingsdate`,`stockhistory`,`netvaluehistory`,`fundest` |
| SZ161116 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_GC` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ161130 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_NQ` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ161815 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `QdiiGetRealtimeSymbol=false`，固定 `—` | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ163208 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_CL` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ161125 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_ES` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ161128 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `QdiiGetRealtimeSymbol=false`，固定 `—` | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ162719 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_CL` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ162411 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_CL` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ160723 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_CL` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SH501018 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_CL` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SZ160416 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_CL` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |
| SH501225 | HoldingsReference | `GetOfficialNav` -> `_estNav(GetOfficialDate())` | `GetFairNav`（触发时可与官方同值） | A 股 `GetRealtimeNav=false` | `holdings`,`holdingsdate`,`stockhistory`,`netvaluehistory`,`fundest` |
| SZ161129 | QdiiReference | 同 QDII 官方链路 | 同 QDII 参考链路 | `hf_CL` 实时链路 | `stockhistory`,`netvaluehistory`,`calibrationhistory`,`fundest` |

补充：
- 上表中“同 QDII 官方链路”统一指：`_QdiiReference::EstNetValue` -> `_getEstVal` + `GetQdiiValue`，并在满足条件时 `StockUpdateEstResult` 写 `fundest`。  
- “同 QDII 参考链路”统一指：`_QdiiReference::EstRealtimeNetValue` 中 `fFairNetValue` 分支（`GetDate()/est_ref->GetDate()` 与 `strOfficialDate` 不一致时）。  
- 所有“溢价”列统一方法：`GetPercentageDisplay(估值列)`，数据依赖为当前场内价（`GetPrice()`）+ 对应估值列结果。
