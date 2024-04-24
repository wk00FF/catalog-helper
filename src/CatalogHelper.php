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
	public function checkElement($elementID, $parentSectionID=0){
		$section=&$this->getSection($parentSectionID);
		if(count($section['ELEMENTS'])){
			foreach($section['ELEMENTS'] as $id=>$element){
				if($element['ID']==$elementID) return $element;
			}
		}
		if(count($section['SECTIONS'])){
			foreach($section['SECTIONS'] as $sectID=>$sect){
				$ret=$this->checkElement($elementID, $sectID);
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
	public static function getInfoblockStructure(int $infoblockId, int $parentSectionId=0, array $fields=[], array $properties=[], $price=false){
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
			$childSections=self::getInfoblockStructure($infoblockId, $sectionId, $fields, $properties, $price);
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
				$element['PRICE']=getFinalPriceInCurrency($element['ID'])['FINAL_PRICE'];
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
}