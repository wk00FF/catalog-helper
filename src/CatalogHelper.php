<?
namespace wk00FF;
class CatalogHelper{
	private $goods;
	private $id;
	private $directory;
	public function __construct($className=''){
		$this->goods=[];
		$this->id=0;
		$this->directory=$className;
		return $this;
	}
	public function getGoods(){
		return $this->goods;
	}
	public function setGoods($goods){
		$this->goods=$goods;
	}
	public function setFinished(){																																					// выставить флаги, что завершено
		$this->goods['FINIFHED']=true;
		file_put_contents($_SERVER['DOCUMENT_ROOT'].'/upload/'.$this->directory.'/'.$this->directory.'.flg', '1');
	}
	public function addSection($name, $url, $sectionID, $parentID=0){
		$section=&$this->getSection($parentID);
		if($section['SECTIONS'][$this->id+1]) return ++$this->id;																													// если такая секция уже есть, просто вернуть ее ID
		$section['SECTIONS'][++$this->id]=['NAME'=>$name, 'ID'=>$sectionID, 'URL'=>$url];
		_vardump($this->id, 'f', false);
		return $this->id;
	}
	public function addElement($elementName, $elementURL, $elementID, $elementCode, $elementPrice, $elementArticle, $parentID=0){
		$section=&$this->getSection($parentID);
		$section['ELEMENTS'][$elementID]=[
			'NAME'   =>$elementName,
			'ID'     =>$elementID,
			'CODE'   =>$elementCode,
			'PRICE'  =>$elementPrice,
			'ARTICLE'=>$elementArticle,
			'URL'    =>$elementURL,
		];
		return $this->id;
	}
	// проверить наличие элемента в дереве, начиная с $parentID
	// возвращает элемент, если элемент есть
	public function getElementByID($elementID, $parentSectionID=0){
		$section=&$this->getSection($parentSectionID);
		if(count((array)$section['ELEMENTS'])){
			foreach($section['ELEMENTS'] as $id=>$element){
				if($element['ID']==$elementID) return $element;
			}
		}
		if(count((array)$section['SECTIONS'])){
			foreach($section['SECTIONS'] as $sectID=>$sect){
				$ret=$this->getElementByID($elementID, $sectID);
				if($ret) return $ret;
			}
		}
		return $ret;
	}
	// возвращает массив с ID вложенных родительских секций элемента
	public function getParentSectionsOfElement(int $elementID, int $parentSectionID=0){
		$section=&$this->getSection($parentSectionID);
		foreach($section['ELEMENTS'] as $elemID=>$elem){
			if($elemID==$elementID){
				return [$parentSectionID];
			}
		}
		foreach($section['SECTIONS'] as $sectID=>$sect){
			$ret=$this->getParentSectionsOfElement($elementID, $sectID, $parents);
			if(is_array($ret)){
//				$ret[]=$parentSectionID;
				array_unshift($ret, $parentSectionID);
				return $ret;
			}
		}
		return $ret;
	}
	public static function getParentSectionsByElementID($elementID){
		$element=\Bitrix\Iblock\ElementTable::getRow([
			'select'=>['IBLOCK_SECTION_ID'],
			'filter'=>['=ID'=>$elementID],
		]);
		if($element!==null){
			$parentSections=[];
			$parentSectionIterator=\Bitrix\Iblock\SectionTable::getList([
				'select' =>[
					'SECTION_ID'       =>'SECTION_SECTION.ID',
					'IBLOCK_SECTION_ID'=>'SECTION_SECTION.IBLOCK_SECTION_ID',
					'NAME'             =>'SECTION_SECTION.NAME',
					'CODE'             =>'SECTION_SECTION.CODE',
					'ACTIVE'           =>'SECTION_SECTION.ACTIVE'
				],
				'filter' =>[
					'=ID'=>$element['IBLOCK_SECTION_ID'],
				],
				'runtime'=>[
					'SECTION_SECTION'=>[
						'data_type'=>'\Bitrix\Iblock\SectionTable',
						'reference'=>[
							'=this.IBLOCK_ID'    =>'ref.IBLOCK_ID',
							'>=this.LEFT_MARGIN' =>'ref.LEFT_MARGIN',
							'<=this.RIGHT_MARGIN'=>'ref.RIGHT_MARGIN',
						],
						'join_type'=>'inner'
					],
				],
			]);
			while($parentSection=$parentSectionIterator->fetch()){
				$parentSections[]=$parentSection;
			}
			return $parentSections;
		}
		else return false;
	}
	private function contentFileName($URL){																																			// todo тут надо как то с обработкой URL разобраться. Они для каждого сайта свои
		if(preg_match('/\.html$/', $URL)){
			if(preg_match('/category_(\d+)\.html$/', $URL, $matches)){																						// это секция
				$fileName='section_'.$matches[1].'.dat';
			}
			elseif(preg_match('/product_(\d+)\.html$/', $URL, $matches)){																					// нет, это блять товар
				$fileName='product_'.$matches[1].'.dat';
			}
			elseif(preg_match('/catalogue\.html$/', $URL)){																											// это root от 4x4sport
				$fileName='index.dat';
			}
		}
		elseif(preg_match('/index.php\?categoryID=(\d+).+?offset=(\d+)/', $URL, $matches)){																// пагинация bazashop
			$fileName='section_'.$matches[1].'_offset_'.$matches[2].'.dat';
		}
		elseif(preg_match('/\?gr=(\d+).+?page=(\d+)/', $URL, $matches)){																					// пагинация 4x4sport
			$fileName='section_'.$matches[1].'_page_'.$matches[2].'.dat';
		}
		elseif(preg_match('/\?gr=(\d+)/', $URL, $matches)){																								// секция 4x4sport
			$fileName='section_'.$matches[1].'.dat';
		}
		elseif(substr_count($URL, '/')>3){
			preg_match('/\/\/(.+)/', trim($URL, '/'), $matches);
			//			$fileName=$matches[1];
			$fileName=preg_replace('/\//', '-', $matches[1]).'.dat';
		}
		else{
			$fileName='index.dat';
		}
		$fileName=$_SERVER['DOCUMENT_ROOT'].'/upload/'.$this->directory.'/'.$fileName;
		return $fileName;
	}
	public function addPageData($URL, $pageContent){
		$fileName=$this->contentFileName($URL);
		file_put_contents($fileName, $pageContent);
	}
	public function getPageData($URL){
		$fileName=$this->contentFileName($URL);
		if(file_exists($fileName)){
			return file_get_contents($fileName);
		}
		else{
			return false;
		}
	}
	public function getCurrentID(){
		return $this->id;
	}
	public function setCurrentID($id){
		$this->id=$id;
	}
	public function &getSection($ID=0, &$start=null){																																// получить указатель на секцию в массиве данных
		if(!$start) $start=&$this->goods;
		if(!$ID) return $start;
		foreach($start['SECTIONS'] as $id=>&$section){
			if($id==$ID) return $section;
			if($section['SECTIONS']){
				$subsection=&$this->getSection($ID, $section);
				if($subsection) return $subsection;
			}
		}
		unset($section);
		return false;
	}
	public function &getSectionByName($path='', &$parentSection=null){
		if(!$parentSection) $parentSection=&$this->goods;
		$path=explode('::', $path);
		foreach($path as $item){
			array_shift($path);
			foreach($parentSection['SECTIONS'] as $id=>&$section){
				if($section['NAME']==$item){
					if(!count($path)){
						return $section;
					}
					else{
						$subsection=$this->getSectionByName( implode('::', $path), $section);
						if($subsection) return $subsection;
					}
				}
			}
			unset($section);
		}
		return false;
	}
	public function getElementByName($path='', $name){
		$section=empty($path)?$this->goods:$this->getSectionByName($path);
		if($section){
			if(count($section['ELEMENTS'])){
				foreach($section['ELEMENTS'] as $element){
					if($element['NAME']==$name){
						return $element;
					}
				}
			}
		}
		return false;
	}
	public function getElementsByProps(array $props, &$parentSection=null){
		if(!$parentSection) $parentSection=&$this->goods;
		foreach($parentSection['SECTIONS'] as &$section){
			$subsection=$this->getElementsByProps($props, $section);
			if($subsection) return $subsection;
		}
		$res=[];
		foreach($parentSection['ELEMENTS'] as $element){
			$matched=true;
			foreach($props as $code=>$value){
				if($element['PROPERTIES'][$code]){
					if(is_array($element['PROPERTIES'][$code])){
						$matched1=false;
						foreach($element['PROPERTIES'][$code] as $val){
							if($val==$value){
								$matched1=true;
								break;
							}
						}
						if(!$matched=$matched1){
							break;
						}
					}
					else{
						if($element['PROPERTIES'][$code]!=$value){
							$matched=false;
							break;
						}
					}
				}
			}
			if($matched){
				$res[]=$element;
			}
		}
		return count($res)?$res:false;
	}
	/**
	 * Возвращает ID инфоблока по символьному коду NEW
	 * @param      $sIBlockCode
	 * @param bool $bRefreshCache
	 * @return int
	 */
	public static function getIBlockIDByCode_($sIBlockCode, $bRefreshCache=false){
		if($bRefreshCache){
			$obCache=new \CPHPCache;
			$iReturnId=false;
			$CACHE_ID='getIBlockIDByCode';
			$iCacheTime=10800; //3 часа
			if($obCache->InitCache($iCacheTime, $sIBlockCode, $CACHE_ID)){
				$vars=$obCache->GetVars();
				$iReturnId=$vars['result'];
			}
			elseif($obCache->StartDataCache($iCacheTime, $sIBlockCode, $CACHE_ID)){
				$iReturnId=\Bitrix\Iblock\IblockTable::getList(['select'=>['ID'], 'filter'=>['CODE'=>$sIBlockCode]])->Fetch()["ID"];
				$obCache->EndDataCache(['result'=>$iReturnId]);
			}
			return $iReturnId;
		}
		else{
			return \Bitrix\Iblock\IblockTable::getList(['select'=>['ID'], 'filter'=>['CODE'=>$sIBlockCode]])->Fetch()["ID"];
		}
	}
	//Возвращает ID секции по символьному коду NEW.
	//Параметры:
	//$section_id - id секции
	//$section_code - символьный код секции
	//$arFilter - массив свойств для фильтрации (необязательный). Но для ускорения процесса поиска можно передать id инфоблока, в котором лежит элемент: array("IBLOCK_ID" => №).
	public static function getSectionIDByCode_($section_code, $bRefreshCache=false, $section_id='', $arFilter=''){
		if($bRefreshCache){
			$obCache=new \CPHPCache;
			$iReturnId=false;
			$CACHE_ID='getSectionIdByCode';
			$iCacheTime=10800; //3 часа
			if($obCache->InitCache($iCacheTime, $section_code, $CACHE_ID)){
				$vars=$obCache->GetVars();
				$iReturnId=$vars['result'];
			}
			elseif($obCache->StartDataCache($iCacheTime, $section_code, $CACHE_ID)){
				$iReturnId=\CIBlockFindTools::GetSectionID($section_id, $section_code, $arFilter);
				$obCache->EndDataCache(['result'=>$iReturnId]);
			}
			return $iReturnId;
		}
		else{
			return \CIBlockFindTools::GetSectionID($section_id, $section_code, $arFilter);
		}
	}
	public static function getSectionIDByName($iblockID, $sSectionName, $sSectionParentID=false, $bRefreshCache=false){
		$obCache=new \CPHPCache;
		$iReturnId=false;
		$CACHE_ID='getSectionIdByCode'.$sSectionName;
		$iCacheTime=10800; //3 часа
		$iCacheTime=2;
		if($obCache->StartDataCache($iCacheTime, $CACHE_ID) || $bRefreshCache){
			$arFilter=['IBLOCK_ID'=>$iblockID, 'NAME'=>$sSectionName, 'SECTION_ID'=>$sSectionParentID];
			$db_list=\CIBlockSection::GetList([], $arFilter, false, ["ID"]);
			if($ar_result=$db_list->GetNext()) $iReturnId=$ar_result['ID'];
			$obCache->EndDataCache($iReturnId);
		}
		else $iReturnId=$obCache->GetVars();
		unset($obCache);
		return $iReturnId;
	}
	function getAllElementsFromSectionID($sectionID, $select=['ID', 'NAME',], $bRefreshCache=false){
		$returnEls=[];
		if(!in_array('ID', $select)){
			$select[]='ID';
		}
		\Bitrix\Main\Loader::includeModule('iblock');
		$section=\Bitrix\Iblock\SectionTable::getList([
			'filter'=>[
				'=ID'=>(int)$sectionID,
			],
			'select'=>['LEFT_MARGIN', 'RIGHT_MARGIN'],
		])->fetch();
		$dbItems=\Bitrix\Iblock\ElementTable::getList([
			'select'=>$select,
			'filter'=>[
				'=ACTIVE'                      =>'Y',
				'>=IBLOCK_SECTION.LEFT_MARGIN' =>$section['LEFT_MARGIN'],
				'<=IBLOCK_SECTION.RIGHT_MARGIN'=>$section['RIGHT_MARGIN'],
			],
		]);
		while($arItem=$dbItems->fetch()){
			foreach(self::getParentSectionsByElementID($arItem['ID']) as $sect){
				if($sect['ACTIVE']=='N'){
					continue 2;
				}
			}
			$returnEls[$arItem['ID']]=$arItem;
		}
		return $returnEls;
	}

	//Возвращает id элемента символьному коду NEW.
	//Параметры:
	//$element_id - если передать id элемента, то он и вернётся
	//$element_code - символьный код элемента
	//$section_id - id секции, в которой лежит элемент (необязательный)
	//$section_code - символьный код секции, в которой лежит элемент (необязательный)
	//$arFilter - массив свойств для фильтрации (необязательный). Для ускорения процесса поиска можно передать id инфоблока, в котором лежит элемент: array("IBLOCK_ID" => №).
	public static function getElementIDByCode_($element_code, $bRefreshCache=false, $element_id='', $section_id='', $section_code='', $arFilter=''){
		if($bRefreshCache){
			$obCache=new \CPHPCache;
			$iReturnId=false;
			$CACHE_ID='getSectionIdByCode';
			$iCacheTime=10800; //3 часа
			if($obCache->InitCache($iCacheTime, $element_code, $CACHE_ID)){
				$vars=$obCache->GetVars();
				$iReturnId=$vars['result'];
			}
			elseif($obCache->StartDataCache($iCacheTime, $element_code, $CACHE_ID)){
				$iReturnId=\CIBlockFindTools::GetElementID($element_id, $element_code, $section_id, $section_code, $arFilter);
				$obCache->EndDataCache(['result'=>$iReturnId]);
			}
			return $iReturnId;
		}
		else{
			return \CIBlockFindTools::GetElementID($element_id, $element_code, $section_id, $section_code, $arFilter);
		}
	}
	public static function getInfoblockStructure(int $infoblockId, int $parentSectionId=0, array $fields=[], array $properties=[], $price=false, $stock=false){
		$result=[];
		$sections=\Bitrix\Iblock\SectionTable::getList([
			'filter'=>[
				'=IBLOCK_ID'        =>$infoblockId,
				'=IBLOCK_SECTION_ID'=>$parentSectionId,
				'=ACTIVE'           =>'Y',
			],
			'select'=>[
				'ID',
				'IBLOCK_SECTION_ID',
				'NAME',
			],
		])->fetchAll();
		foreach($sections as $section){
			$sectionId=$section['ID'];
			$parentId=$section['IBLOCK_SECTION_ID'];
			$sectionName=$section['NAME'];
			$childSections=self::getInfoblockStructure($infoblockId, $sectionId, $fields, $properties, $price, $stock);
			$result['SECTIONS'][$sectionId]=$childSections;
			$result['SECTIONS'][$sectionId]['ID']=$sectionId;
			$result['SECTIONS'][$sectionId]['NAME']=$sectionName;
		}
		$select=array_merge([
			'ID',
			'NAME',
		], $fields);
		$elements=\Bitrix\Iblock\ElementTable::getList([
			'filter'=>[
				'=IBLOCK_ID'        =>$infoblockId,
				'=IBLOCK_SECTION_ID'=>$parentSectionId,
				'=ACTIVE'           =>'Y',
			],
			'select'=>$select,
		])->fetchAll();
		foreach($elements as $element){
			if(count($properties)){
				$element['PROPERTIES']=self::getPropertiesByID($infoblockId, $element['ID'], $properties, true);
			}
			if($price){
				$element['PRICE']=self::getFinalPriceInCurrency($element['ID'])['FINAL_PRICE'];
			}
			if($stock){
				$element['STOCK']=self::getStockQuantity($element['ID']);
			}
			$result['ELEMENTS'][$element['ID']]=$element;
		}
		return $result;
	}

	public static function getPropertiesByID(int $iblockID, int $elementID, array $selectCodes=[], bool $valueOnly=false){
		if(count($selectCodes)){
			foreach($selectCodes as $selectCode){
				$dbProperty=\CIBlockElement::getProperty($iblockID, $elementID, false, false, ['CODE'=>$selectCode]);
				while($arProperty=$dbProperty->GetNext()){
					if($valueOnly){
						if(!$arPropertyes[$arProperty['CODE']]) $arPropertyes[$arProperty['CODE']]=$arProperty['VALUE'];
						if($arProperty['VALUE'] || $arProperty['VALUE_ENUM']){
							if($arProperty['PROPERTY_TYPE']=='L'){
								if(!empty($arPropertyes[$arProperty['CODE']]) && is_string($arPropertyes[$arProperty['CODE']])){
									$arPropertyes[$arProperty['CODE']]=str_split($arPropertyes[$arProperty['CODE']], strlen($arPropertyes[$arProperty['CODE']])+1);
									array_shift($arPropertyes[$arProperty['CODE']]);
								}
								$arPropertyes[$arProperty['CODE']][]=$arProperty['VALUE_ENUM'];
							}elseif($arProperty['MULTIPLE']=='Y'){
								if(!empty($arPropertyes[$arProperty['CODE']]) && is_string($arPropertyes[$arProperty['CODE']])){
									$arPropertyes[$arProperty['CODE']]=str_split($arPropertyes[$arProperty['CODE']], strlen($arPropertyes[$arProperty['CODE']])+1);
									array_shift($arPropertyes[$arProperty['CODE']]);
								}
								$arPropertyes[$arProperty['CODE']][]=$arProperty['VALUE'];
							}
						}
					}
					else{
						if(!$arPropertyes[$arProperty['CODE']]) $arPropertyes[$arProperty['CODE']]=$arProperty;
						if($arProperty['VALUE'] || $arProperty['VALUE_ENUM']){
							if($arProperty['PROPERTY_TYPE']=='L'){
								if(!empty($arPropertyes[$arProperty['CODE']]['VALUE']) && is_string($arPropertyes[$arProperty['CODE']]['VALUE'])){
									$arPropertyes[$arProperty['CODE']]['VALUE']=str_split($arPropertyes[$arProperty['CODE']]['VALUE'], strlen($arPropertyes[$arProperty['CODE']]['VALUE'])+1);
									array_shift($arPropertyes[$arProperty['CODE']]['VALUE']);
								}
								$arPropertyes[$arProperty['CODE']]['VALUE'][]=$arProperty['VALUE_ENUM'];
							}elseif($arProperty['MULTIPLE']=='Y'){
								if(!empty($arPropertyes[$arProperty['CODE']]['VALUE']) && is_string($arPropertyes[$arProperty['CODE']]['VALUE'])){
									$arPropertyes[$arProperty['CODE']]['VALUE']=str_split($arPropertyes[$arProperty['CODE']]['VALUE'], strlen($arPropertyes[$arProperty['CODE']]['VALUE'])+1);
									array_shift($arPropertyes[$arProperty['CODE']]['VALUE']);
								}
								$arPropertyes[$arProperty['CODE']]['VALUE'][]=$arProperty['VALUE'];
							}
						}
					}
				}
			}
		}
		else{
			$arPropertyes=[];
			$propertes=\Bitrix\Iblock\PropertyTable::getList([
				'filter'=>['IBLOCK_ID'=>$iblockID, 'ACTIVE'=>'Y'],
			])->fetchAll();
			foreach($propertes as $prop){
				$arPropertyes=array_merge($arPropertyes, self::getPropertiesByID($iblockID, $elementID, [$prop['CODE']]));
			}
		}
		return $arPropertyes;
	}
	public static function getFinalPriceInCurrency($item_id, $cnt=1, $getName="N", $sale_currency='RUB'){
//		CModule::IncludeModule("iblock");
//		CModule::IncludeModule("catalog");
//		CModule::IncludeModule("sale");
		global $USER;
		if(\CCatalogSku::IsExistOffers($item_id)){																														// Проверяем, имеет ли товар торговые предложения?
			$res=\CIBlockElement::GetByID($item_id);																															// Пытаемся найти цену среди торговых предложений
			if($ar_res=$res->GetNext()){
				$productName=$ar_res["NAME"];
				if(isset($ar_res['IBLOCK_ID']) && $ar_res['IBLOCK_ID']){
					$offers=\CIBlockPriceTools::GetOffersArray([																											// Ищем все тогровые предложения
						'IBLOCK_ID'         =>$ar_res['IBLOCK_ID'],
						'HIDE_NOT_AVAILABLE'=>'Y',
						'CHECK_PERMISSIONS' =>'Y',
					], [$item_id], null, null, null, null, null, null, ['CURRENCY_ID'=>$sale_currency]);
					foreach($offers as $offer){
						$price=\CCatalogProduct::GetOptimalPrice($offer['ID'], $cnt, $USER->GetUserGroupArray(), 'N');
						if(isset($price['PRICE'])){
							$final_price=$price['PRICE']['PRICE'];
							$currency_code=$price['PRICE']['CURRENCY'];
							$arDiscounts=\CCatalogDiscount::GetDiscountByProduct($item_id, $USER->GetUserGroupArray(), "N");							// Ищем скидки и высчитываем стоимость с учетом найденных
							if(is_array($arDiscounts) && sizeof($arDiscounts)>0){
								$final_price=\CCatalogProduct::CountPriceWithDiscount($final_price, $currency_code, $arDiscounts);
							}
							break;
						}
					}
				}
			}
		}else{
			$price=\CCatalogProduct::GetOptimalPrice($item_id, $cnt, [1, 2, 3, 4, 5,], 'N');												// Простой товар, без торговых предложений (для количества равному $cnt)
			if(!$price || !isset($price['PRICE'])){
				return false;
			}
			if(isset($price['CURRENCY'])){																																			// Меняем код валюты, если нашли
				$currency_code=$price['CURRENCY'];
			}
			if(isset($price['PRICE']['CURRENCY'])){
				$currency_code=$price['PRICE']['CURRENCY'];
			}
			$final_price=$price['PRICE']['PRICE'];																																	// Получаем итоговую цену
			$arDiscounts=\CCatalogDiscount::GetDiscountByProduct($item_id, [1, 2, 3, 4, 5,], "N", 2);																				// Ищем скидки и пересчитываем цену товара с их учетом
			if(is_array($arDiscounts) && sizeof($arDiscounts)>0){
				$final_price=\CCatalogProduct::CountPriceWithDiscount($final_price, $currency_code, $arDiscounts);
			}
			if($getName=="Y"){
				$res=\CIBlockElement::GetByID($item_id);
				$ar_res=$res->GetNextElement();
				$arFields=$ar_res->GetFields();
				$arProps=$ar_res->GetProperties([], ['CODE'=>'CONF_HEADING']);
				if($arProps['CONF_HEADING']['VALUE']){
					$productName=is_array($arProps['CONF_HEADING']['VALUE'])?$arProps['CONF_HEADING']['VALUE'][0]:$arProps['CONF_HEADING']['VALUE'];
				}else
					$productName=$arFields["NAME"];
			}
		}
		if($currency_code!=$sale_currency){																																			// Если необходимо, конвертируем в нужную валюту
			$final_price=\CCurrencyRates::ConvertCurrency($final_price, $currency_code, $sale_currency);
		}
		$arRes=[
			'PRICE'      =>$price['PRICE']['PRICE'],
			'FINAL_PRICE'=>$final_price,
			'CURRENCY'   =>$sale_currency,
			'DISCOUNT'   =>$arDiscounts,
		];
		if($productName!="") $arRes['NAME']=$productName;
		return $arRes;
	}
	public static function getStockQuantity($productId){
		$arStocks=[];
		$arStocks['TOTAL']=0;
		$productData=\Bitrix\Catalog\ProductTable::getList([
			'filter'=>['=ID'=>$productId],
			'select'=>['ID', 'QUANTITY',],
		])->fetch();

		// Получаем остатки товара на складах
		$storeProducts=\Bitrix\Catalog\StoreProductTable::getList([
			'filter'=>['=PRODUCT_ID'=>$productId],
			'select'=>['STORE_ID', 'AMOUNT',],
		]);
		while($storeProduct=$storeProducts->fetch()){
			$storeId=$storeProduct['STORE_ID'];
			$quantity=$storeProduct['AMOUNT'];

			// Получаем название склада
			$storeData=\Bitrix\Catalog\StoreTable::getList([
				'filter'=>['=ID'=>$storeId],
				'select'=>['ID', 'TITLE',],
			])->fetch();
			if($storeData){
				$arStocks[$storeData['ID']]=[
					'ID'=>$storeData['ID'],
					'NAME'=>$storeData['TITLE'],
					'QUANTITY'=>$quantity,
				];
				$arStocks['TOTAL']+=$quantity;
			}
		}
		return $arStocks;
	}
}