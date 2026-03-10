# LOF 估值表字段计算逻辑说明

本文档说明从入口 **`…/woody/res/lofcn.php`** 打开的估值页面中，估值表各列的计算逻辑及所涉方法。表格由 `php/ui/fundestparagraph.php` 的 `_echoFundEstTableItem()` 输出，每行对应一只 LOF 基金，`$ref` 由 `StockGetFundReference($strSymbol)` 得到，类型因基金类别不同而不同。

---

## 一、估值表列与统一逻辑

| 列名       | 数据来源（代码） | 说明 |
|------------|------------------|------|
| 估值网页链接 | `$ref->GetStockLink()` | 跳转到该基金估值/详情页的链接 |
| 官方估值   | `$ref->GetOfficialNav()` | 官方净值估算值 |
| 日期       | `$ref->GetOfficialDate()` | 官方估值对应的估值日期 |
| 溢价       | `$ref->GetPercentageDisplay($strOfficialPrice)` | 相对官方估值的溢价率 |
| 参考估值   | `$ref->GetFairNav()` | 参考估值（部分类型无） |
| 溢价       | `$ref->GetPercentageDisplay($strFairPrice)` | 相对参考估值的溢价率 |
| 实时估值   | `$ref->GetRealtimeNav()` | 实时净值估算（部分类型无） |
| 溢价       | `$ref->GetPercentageDisplay($strRealtimePrice)` | 相对实时估值的溢价率 |

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
  - `StockUpdateEstResult()`：写入 fundest 表

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
4. **列顺序**：估值网页链接 → 官方估值 → 日期 → 溢价 →（若有参考估值）参考估值 → 溢价 →（若有实时估值）实时估值 → 溢价。

以上即 LOF 估值表各字段的计算逻辑及所涉方法，按基金类型分别对应 HoldingsReference、FundPairReference、QdiiReference 系与 FundReference。
