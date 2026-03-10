<?php

class HoldingsReference extends MyStockReference
{
	var $nav_ref;
    var $uscny_ref;
    var $hkcny_ref;
    
    var $ar_holdings_ref = array();

    var $strNav;
    var $strHoldingsDate;
    var $arHoldingsRatio = array();
    var $bRateNotConfigured = false;   // 汇率未配置时由 _estNav 置为 true，供页面提示
    
    public function __construct($strSymbol) 
    {
        parent::__construct($strSymbol);
       	$this->nav_ref = new NetValueReference($strSymbol);
   		$this->hkcny_ref = new CnyReference('HKCNY');
   		$this->uscny_ref = new CnyReference('USCNY');

        $strStockId = $this->GetStockId();
    	$date_sql = new HoldingsDateSql();
    	if ($this->strHoldingsDate = $date_sql->ReadDate($strStockId))
    	{
			if ($this->strNav = SqlGetNavByDate($strStockId, $this->strHoldingsDate))
			{
				$holdings_sql = GetHoldingsSql();
				$this->arHoldingsRatio = $holdings_sql->GetHoldingsArray($strStockId);
				$sql = GetStockSql();
				foreach ($this->arHoldingsRatio as $strId => $strRatio)
				{
					$this->ar_holdings_ref[] = new MyStockReference($sql->GetStockSymbol($strId));
				}
			}
			else	
			{
				DebugString(__CLASS__.'->'.__FUNCTION__.': Missing NAV on '.$this->strHoldingsDate);
//				$nav_ref = new NetValueReference($strSymbol);
//				SqlSetNav($strStockId, $this->strHoldingsDate, $nav_ref->GetPrevPrice());
//				if ($this->strHoldingsDate == $nav_ref->GetDate())		SqlSetNav($strStockId, $this->strHoldingsDate, $nav_ref->GetPrice());
			}
    	}
    }
    
    function GetFundEstSql()
    {
    	return $this->nav_ref->GetFundEstSql();
    }
    
    function GetNavRef()
    {
    	return $this->nav_ref;
    }
    
    function GetUscnyRef()
    {
    	return $this->uscny_ref;
    }
    
    function GetHkcnyRef()
    {
    	return $this->hkcny_ref;
    }
    
    function GetHoldingsDate()
    {
    	return $this->strHoldingsDate;
    }
    
    function GetHoldingsRatioArray()
    {
    	return $this->arHoldingsRatio;
    }
    
    function GetHoldingRefArray()
    {
    	return $this->ar_holdings_ref;
    }
    
    function GetNav()
    {
    	return $this->nav_ref->GetPrice();
    }
    
    /** 从 ref 或库中取汇率，避免 CnyReference 初始化时未加载到数据导致一直为 0 */
    function _getCnyRate($cny_ref, $strDate = false)
    {
        $v = $strDate ? $cny_ref->GetClose($strDate) : $cny_ref->GetPrice();
        if ($v !== false && $v !== '' && floatval($v) >= 1e-10)	return floatval($v);
        $id = $cny_ref->GetStockId();
        if ($id)	$v = $strDate ? SqlGetNavByDate($id, $strDate) : SqlGetNav($id);
        return ($v !== false && $v !== '' && floatval($v) >= 1e-10) ? floatval($v) : false;
    }

    function GetAdjustHkd($strDate = false)
    {
        // 持仓日无汇率时用最新价，避免仅因“持仓日无历史汇率”就整页显示请配置汇率
        $strHKDCNY = $this->hkcny_ref->GetClose($this->strHoldingsDate);
        if ($strHKDCNY === false || $strHKDCNY === '')	$strHKDCNY = $this->hkcny_ref->GetPrice();
        $strUSDCNYHoldings = $this->uscny_ref->GetClose($this->strHoldingsDate);
        if ($strUSDCNYHoldings === false || $strUSDCNYHoldings === '')	$strUSDCNYHoldings = $this->uscny_ref->GetPrice();

        // ref 可能初始化时未加载到数据，直接查库兜底
        if (($strHKDCNY === false || $strHKDCNY === '') && $this->hkcny_ref->GetStockId())
            $strHKDCNY = SqlGetNavByDate($this->hkcny_ref->GetStockId(), $this->strHoldingsDate) ?: SqlGetNav($this->hkcny_ref->GetStockId());
        if (($strUSDCNYHoldings === false || $strUSDCNYHoldings === '') && $this->uscny_ref->GetStockId())
            $strUSDCNYHoldings = SqlGetNavByDate($this->uscny_ref->GetStockId(), $this->strHoldingsDate) ?: SqlGetNav($this->uscny_ref->GetStockId());

        if ($strHKDCNY === false || $strHKDCNY === '' || $strUSDCNYHoldings === false || $strUSDCNYHoldings === '')
        	return false;
        $fHkd = floatval($strHKDCNY);
        if ($fHkd < 1e-10)	return false;
        $fOldUSDHKD = floatval($strUSDCNYHoldings) / $fHkd;

		$fHkcny = $this->_getCnyRate($this->hkcny_ref);
		if ($fHkcny === false)	return false;
		$fUscny = $this->_getCnyRate($this->uscny_ref);
		if ($fUscny === false)	return false;
		$fUSDHKD = $fUscny / $fHkcny;
		if ($strDate)	
		{
			if ($strHKDCNY = $this->hkcny_ref->GetClose($strDate))	
			{
				if ($strUSDCNY = $this->uscny_ref->GetClose($strDate))		$fUSDHKD = floatval($strUSDCNY) / floatval($strHKDCNY);
			}
		}
		if ($fUSDHKD < 1e-10)	return false;
		return $fOldUSDHKD / $fUSDHKD;
    }
    
    function GetAdjustCny($strDate = false)
    {
		$fUSDCNY = $this->_getCnyRate($this->uscny_ref);
		if ($fUSDCNY === false)	return false;
		if ($strOldUSDCNY = $this->uscny_ref->GetClose($this->strHoldingsDate))
		{
			$fOldUSDCNY = floatval($strOldUSDCNY);
		}
		else
		{
			$fOldUSDCNY = $fUSDCNY;
		}
		
		if ($strDate)
		{
			if ($strUSDCNY = $this->uscny_ref->GetClose($strDate))		$fUSDCNY = floatval($strUSDCNY);
		}
		if ($fUSDCNY < 1e-10)	return false;
		return $fOldUSDCNY / $fUSDCNY;
    }

    function GetRateNotConfigured()
    {
    	return $this->bRateNotConfigured;
    }

    function _getStrictRef($strSymbol)
    {
    	foreach ($this->ar_holdings_ref as $ref)
		{
			if ($ref->GetSymbol() == $strSymbol)	return $ref;
		}
		return false;
	}
					
    // (x - x0) / x0 = sum{ r * (y - y0) / y0} 
    function _estNav($strDate = false, $bStrict = false)
    {
    	$this->bRateNotConfigured = false;
    	$arStrict = GetSecondaryListingArray();    	
    	$fAdjustHkd = $this->GetAdjustHkd($strDate);
		$fAdjustCny = $this->GetAdjustCny($strDate);
		if ($fAdjustHkd === false || $fAdjustCny === false)
		{
			$this->bRateNotConfigured = true;
			return false;
		}
    	
		$his_sql = GetStockHistorySql();
		$fTotalChange = 0.0;
		$fTotalRatio = 0.0;
		foreach ($this->ar_holdings_ref as $ref)
		{
			$strStockId = $ref->GetStockId();
			$fRatio = floatval($this->arHoldingsRatio[$strStockId]) / 100.0;
			$fTotalRatio += $fRatio;
			
			if ($bStrict)
			{
				$strSymbol = $ref->GetSymbol();
				if (isset($arStrict[$strSymbol]))
				{	// Hong Kong secondary listings
					if ($us_ref = $this->_getStrictRef($arStrict[$strSymbol]))
					{
						$ref = $us_ref;
						$strStockId = $ref->GetStockId();
					}
					else															DebugString('Missing '.$arStrict[$strSymbol], true);
				}
			}
			
			$strPrice = $ref->GetPrice();
			if ($strDate)
			{
				if ($str = $his_sql->GetAdjClose($strStockId, $strDate))		$strPrice = $str;
			}
			
			if ($strAdjClose = $his_sql->GetAdjClose($strStockId, $this->strHoldingsDate))
			{
				$fChange = $fRatio * floatval($strPrice) / floatval($strAdjClose);
				if ($ref->IsSymbolA())		$fChange *= $fAdjustCny;
				else if ($ref->IsSymbolH())	$fChange *= $fAdjustHkd; 
				$fTotalChange += $fChange;
			}
		}
		
		$fTotalChange -= $fTotalRatio;
		$fTotalChange *= RefGetPosition($this);

		$fNewNav = floatval($this->strNav) * (1.0 + $fTotalChange);
		if ($this->IsFundA())		$fNewNav /= $fAdjustCny;
		return $fNewNav; 
    }

    function GetNavChange()
    {
    	$f = $this->_estNav();
    	if ($f === false)	return false;
    	$fNav = floatval($this->strNav);
    	if ($fNav < 1e-10)	return false;
    	return $f / $fNav;
    }
    
    function _getEstDate()
    {
    	$strH = false;
   		foreach ($this->ar_holdings_ref as $ref)
   		{
   			if ($ref->IsSymbolH())
   			{
    			$strH = $ref->GetDate();
    			break;
   			}
   		}
   		
    	$strUS = false;
   		foreach ($this->ar_holdings_ref as $ref)
   		{
   			if ($ref->IsSymbolUS())
   			{
    			$strUS = $ref->GetDate();
    			break;
   			}
   		}
   		
   		if ($strH)
   		{
   			if (($strUS === false) || ($strH == $strUS) || (strtotime($strH) < strtotime($strUS)))		return $strH;
   		}
		return $strUS;
    }
    
    function GetOfficialDate()
    {
   		$strDate = $this->GetDate();
    	if ($this->IsFundA())
    	{
			if ($str = $this->_getEstDate())		$strDate = $str;
    		
    		if ($this->uscny_ref->GetClose($strDate) === false)
    		{   // Load last value from database
    			$fund_est_sql = $this->GetFundEstSql();
    			$strDate = $fund_est_sql->GetDatePrev($this->GetStockId(), $strDate);
    		}
    	}
    	return $strDate;
    }
    
    function GetOfficialNav($bStrict = false)
    {
    	$strDate = $this->GetOfficialDate();
    	$fNav = $this->_estNav($strDate, $bStrict);
    	if ($fNav === false)	return false;
    	$strNav = strval($fNav);
   		StockUpdateEstResult($this->GetFundEstSql(), $this->GetStockId(), $strNav, $strDate);
   		return $strNav;
    }

    function GetFairNav($bStrict = false)
    {
    	$strDate = $this->GetOfficialDate(); 
		if (($this->uscny_ref->GetDate() != $strDate) || ($this->_getEstDate() != $strDate))
		{
			$f = $this->_estNav(false, $bStrict);
			return ($f === false) ? false : strval($f);
		}
		return false;
    }

    function GetRealtimeNav()
    {
    	if ($this->IsFundA())
    	{
    		return false;
    	}
    	$f = $this->_estNav(false, true);
    	return ($f === false) ? false : strval($f);
    }
}

?>
