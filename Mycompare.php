<?php

class Lib_Mycompare {
    public $productone = array();
    public $producttwo = array();
    public $products_detail = array(); //detail
    public $compareinfo  = array();
    public $compareid   = '';
    public $scores;
    public $betters;
    public $standardvalues;
    public $betterone;
    public $relatedComparisons;
    public $popularComparisons;


  function __construct($id) {
    $this->compareid = $id;
    $this->db        =  Zend_Registry::get('db');
    $this->category = $this->db->fetchOne("select title from category where id = ".Zend_Registry::get('supercate'));
    $this->getStringWeight();
    $this->standardvalues = Lib_Product::getFullHpInfo(Zend_Registry::get('supercate'));
    $this->getCompareInfo();
    $this->parseDetails(); //seperate data into 3 parts. scrore, difference  and  all
    $this->getBetter(); //calc the score and get the bettle product
    $this->parseBetter(); // prepare the data from difference shown.
    $this->getRelatedComparisons();
    $this->getRelatedQuestions();
    //$this->getPopularComparisons();

   // var_dump($this->standardvalues);
     //var_dump($this->scores);

  }

  private function getWeightByValueId($info,$vid){
      if(preg_match("# ".$vid."_([0-9]+),#is",$info,$matches)){
          return $matches[1];  
      }  
      
      return 9999;
  }



  //difference description about different content;
  private function getWording($p1,$p2){
    $std = $this->standardvalues[$p1['section_id']][$p1['attr_id']];
    if($p1['api_type'] == 1){
        if(isset($std['range'])){
            $left  = $std['range'];
            $right = $std['difference_words'];
        } else {
            $left  = $p1['attr_name'];
            $right = '';
        }// end string part
    }else{ //number part start
        if(!isset($std['range']) ){
            $left  = $p1['attr_name'];
            $right = '';
        }else{
            $difference = sprintf('%0.2f',abs($p1['value'] - $p2['value']));
            if(strpos($std['range'],'|')){ //if is range words then
              $std_v = explode('|',$std['range']);
              $range_values = explode(',',$std_v[0]);
              $words  = explode(',',$std_v[1]);
              if($range_values[0] > $range_values[1]){  //sort it from low to high 
                  $range_values = array_reverse($range_values);
                  $words        = array_reverse($words);
              }

              if($range_values[0] != 0){  //standardize the first one.
                  array_unshift($range_values,0);  
              }
              $k = -1;
              foreach($range_values as $key => $value){
                  if($difference < $value){
                     $k = $key-1; break;  //if in the range , will chose the right words
                  }
              }
              if($k == -1){
                  $left = array_pop($words); //if beyond the range , will choose the biggest as possible. 
              }else{
                  $left = $words[$k]; 
               }

               $left .= ' '.$p1['attr_name'];

               //calc right 
               if($std['difference_calc_method'] == 0){ //exact number
                  $percentage = $difference;
                  if($p1['unit_name'] == '$'){
                      $percentage       =  $p1['unit_name'].$percentage; 
                  }  else {
                      $percentage      .= ' '.$p1['unit_name'];  
                  }
               }else{
                 //using percentage or  10X,5X
                 $percentage = sprintf('%0.2f',$difference / $p2['value']);
                 if($percentage > 1){
                    // 10x, 5x 2x mode
                    $percentage  = 1+round($percentage);
                 }else{
                    //percentage mode
                    $percentage  = round(100*$percentage).'%';
                 }
                 

                 
               }
                 if(!empty($std['difference_words'])){
                      $right = str_replace("{}",$percentage,$std['difference_words']);
                 }else{
                      //high low
                      $hl    = $p1['weight']==-1 ? 'lower' : 'better';
                      $right = 'Around {} '.$hl.' '.$p1['attr_name']; 
                      $right = str_replace("{}",$percentage,$right);
                 }
            }else{ // if not use directly
              
            }
          
        }
    }
    return array('left' => $left, 'right' => $right);
  }


  private function parseBetter(){
      $datafirst = array();
      $datasecond = array();
      foreach($this->betters as $attrname =>$items){
          $p1 = $items[$this->productone['id']];
          $p2 = $items[$this->producttwo['id']];
          if($p1['api_type'] == 1){ // string compare
             $info = $this->stringWeights[$p1['attr_id']];
             $score1 = $this->getWeightByValueId($info,$p1['myvalueid']);
             $score2 = $this->getWeightByValueId($info,$p2['myvalueid']);
             //getwording
             if($score1 < $score2){
                $datafirst[$attrname] = $items;
             }else{
                $datasecond[$attrname] = array_reverse($items,true);
             }

          }else{ // number compare
             if($p1['value'] > $p2['value']){
                if($p1['weight'] != -1){
                    $datafirst[$attrname] = $items;  
                }  else{
                    $datasecond[$attrname] = array_reverse($items,true);  
                }
             }else{
                if($p1['weight'] == -1){
                    $datafirst[$attrname] = $items;  
                }  else{
                    $datasecond[$attrname] =array_reverse($items,true);  
                }

             
             }
            
          }
          
          if(isset($datafirst[$attrname])){
             $wording = $this->getWording($p1,$p2);
             $datafirst[$attrname]['wording'] = $wording;
          }else{
             $wording = $this->getWording($p2,$p1); 
             $datasecond[$attrname]['wording'] = $wording;
          }

      }

      $this->betters = array('d1'=>$datafirst,'d2'=>$datasecond);
    
  }


  private function getBetter(){
      $cnt = count($this->scores);
      $total[$this->productone['id']] = 0;
      $total[$this->producttwo['id']] = 0;
      foreach($this->scores as $k){
            foreach($k  as $pid => $d){
                $total[$pid]+=$d['score'];
            }
      }  

          $score1 = sprintf('%0.1f',($total[$this->productone['id']]/$cnt));
          $score2 = sprintf('%0.1f',($total[$this->producttwo['id']]/$cnt));
          $width1 = intval(750*$score1/10);
          $width2 = intval(750*$score2/10);
          if($width1 > $width2){
            
              $this->betterone =   array("winner"=>$this->productone , 'score1'=>$score1, 'width1' => $width1,'score2'=>$score2, 'width2' => $width2);
          }else{
                
              $this->betterone =   array("winner"=>$this->producttwo , 'score1'=>$score1, 'width1' => $width1,'score2'=>$score2, 'width2' => $width2);
          }
  }


  private function getCompareInfo(){
     $this->compareinfo = $this->db->fetchRow("select * from compare where id = :id",array('id'=>$this->compareid));
     if(empty($this->compareinfo)) {
        showError('no such comparison');
        exit();
     }
     $this->compareinfo['title'] = preg_replace("#(.*?) (.*?) vs ([^\s]+) (.*?)$#is",'$1 <span class="blue">$2</span> vs $3 <span class="blue">$4</span>',$this->compareinfo['title']);
     
     //get products Ids
     $ids = $this->db->fetchCol("SELECT product_id FROM compare_product WHERE compare_id = :cid",array("cid"=>$this->compareid));

     //get products summary
     list($this->productone,$this->producttwo) = $this->db->fetchAll("select * from products where id in (".implode(', ',$ids).")");

     if(count($ids) > 2) showError("more that 2 items.");
     $this->products_detail = $this->getProduct($ids);


     if(empty($this->productone)  || empty($this->producttwo)) showError("products  do not exist");
     
     $this->productone['link'] = '/i/'.slugify($this->productone['title']).'-'.base64url_encode($this->productone['id']);
     $this->producttwo['link'] = '/i/'.slugify($this->producttwo['title']).'-'.base64url_encode($this->producttwo['id']);
  }


  private function  getProduct($ids){
     $result = $this->db->query("select rel.product_id,rel.value_id as cvid, al.data,al.title as altitle,v.api_type,v.value as vvalue,v.value_i ,v.percentage as vpercentage,rel.show_meter,a.field_des as fdes,u.name as unitname, s.name as section_name,rel.section_id as section_id,rel.high_low as weight,rel.display as display,a.field_type as fieldtype,a.word_type as wordtype,a.name as attr_name,a.id as attr_id, v.attr_id as source_id from pro_sec_attr as rel left join sections as s on s.id=rel.section_id left join attributes as a on a.id =rel.attr_id left join units as u on u.id = rel.unit_id left join `values` as v on v.id=rel.value_id left join attribute_links as al on al.id = rel.attr_links where rel.product_id in (".implode(",",$ids).")   order by rel.section_weight,rel.attr_weight,s.id, a.id");
     while ($attr = $result->fetch()){
          $attrlinks = empty($attr['data'])?array(): unserialize($attr['data']);
          $attrlinkstitle = $attr['altitle'];
          $value = array('api_type'=>$attr['api_type'],'percentage'=>$attr['vpercentage'],'value'=>$attr['vvalue'],'value_i'=>$attr['value_i'],'show_meter'=>$attr['show_meter']);
          $percentage = $value['percentage'];
        if ($value['api_type'] == 1) {
                $ovalue = $value = trim($value['value']);  
        }elseif($value['show_meter']){
                $ovalue = $value['value_i'];
                if($value['value_i']<2100){
                        $value = floatval($value['value_i']);
                }else{
                        $value = number_format(floatval($value['value_i']));
                }
        }else{
                $ovalue = $value['value_i'];
                $value = preg_replace('#\.[0]*$#is','',$value['value_i']);

        }

        $finaldata[$attr['section_name']][$attr['attr_name']][$attr['product_id']] = array('api_type'=>$attr['api_type'],'myvalueid'=>$attr['cvid'],'section_id'=>$attr['section_id'],'attr_name'=>$attr['attr_name'],'attr_id'=>$attr['attr_id'],'value'=>$value,'percentage'=>$percentage,'display'=>$attr['display'],'weight'=>$attr['weight'],'fieldtype'=>$attr['fieldtype'],'wordtype'=>$attr['wordtype'], 'unit_name' => $attr['unitname'],'fdes'=>$attr['fdes'],'show_meter'=>$attr['show_meter'],'attrlinks'=>$attrlinks,'attrlinkstitle'=>$attrlinkstitle,'ovalue'=>$ovalue, 'source_id' => $attr['source_id']);
 
     }
     return $finaldata;
  }
  
  private function getStringWeight(){
	    $this->stringWeights = $this->db->fetchPairs("select attr_id , group_concat(' ',concat(value_id,'_',rank)) as weights from values_rank where category_id = :cid group by attr_id",array("cid"=>Zend_Registry::get('supercate')));
  }
  
  
  private function parseDetails(){
	//3 parts  score, better, rest(all)?
	$scoretotal = 0;
	foreach($this->products_detail as $section => $attrs){
		foreach($attrs as $attr =>$d){
			if(count($d) ==2){
		        if($scoretotal <4 && $d[$this->productone['id']]['show_meter'] == 1 && $d[$this->productone['id']]['api_type'] == 2 && $d[$this->producttwo['id']]['api_type'] == 2){
					        $this->scores[$attr] = $this->getScore($d);
                 if($d[$this->productone['id']]['value'] != $d[$this->producttwo['id']]['value']){
                  $this->betters[$attr] = $d;
                 }
					//unset($this->products_detail[$section][$attr];
					        $scoretotal++; 
				    }elseif(($d[$this->productone['id']]['show_meter'] == 1 && $d[$this->productone['id']]['api_type'] == 2 && $d[$this->producttwo['id']]['api_type'] == 2) || isset($this->stringWeights[$d[$this->productone['id']]['attr_id']])){ //better parts testing

                    if($d[$this->productone['id']]['value'] != $d[$this->producttwo['id']]['value']){
					                $this->betters[$attr] = $d;
                    }
				    }
			  }
		}
	}
	
  }


  private function getScore($d){
    foreach($d as $pid => $data){
        $cvalue  =  $data['value'];
        $highlow =  $data['weight'];
        $min     =  $this->standardvalues[$data['section_id']][$data['attr_id']]['minimum'];
        $max     =  $this->standardvalues[$data['section_id']][$data['attr_id']]['maximum'];
        $totalmeter = $max - $min;
        $currentmeter = $cvalue - $min;
        $percentage = sprintf("%0.1f",5 * ($currentmeter/$totalmeter));
        if($data['weight'] == -1){
            $finalpercentage = 10 - $percentage;  
        }else{
            $finalpercentage = 5 + $percentage;  
        }
        
        $d[$pid] = array('score'=>$finalpercentage,'width'=>intval(360*$finalpercentage/10),'fdes'=>$data['fdes']);
    }

    return $d;
    
  }


  private function parseBetters(){
    
    
  }
  

  private function getRelatedComparisons(){
      //get compare ids
      $sql = "select distinct compare_id from compare_product where product_id in (".$this->producttwo['id']. ",".$this->productone['id'] .") and num_products = 2 and compare_id <> {$this->compareid} limit 15";

      $ids = $this->db->fetchCol($sql);
      if(empty($ids)) { $this->relatedComparisons = array(); return;}
      shuffle($ids);
      $ids = implode(',', $ids);

      $this->relatedComparisons = $this->db->fetchAll("select id as compare_id,title as compare_title from compare where publish = 1 and  id in (".$ids.") limit 6");
      $this->popularComparisons = $this->db->fetchAll("select id as compare_id,title as compare_title from compare where publish = 1 and  id in (".$ids.") limit 6,6");
      $pids = $this->db->fetchCol("select distinct product_id from compare_product where compare_id in (".$ids.")");
      $this->pinfos = $this->db->fetchAll("select title,img from products where id in (".implode(',',$pids).")");
      foreach($this->pinfos as $k=>$v){
          $this->pinfos[$k]['img'] =  imageResize($v['img']);  
      }

  }


  private function getPopularComparisons(){
    /*
      $where = 1;
      $sql = "SELECT distinct cp.compare_id, c.title as compare_title FROM compare_product cp LEFT JOIN compare c ON cp.compare_id = c.id WHERE c.publish = 1 AND $where AND cp.category_id IN(".$this->producttwo['id']. ",".$this->productone['id'] .") and views> 100 LIMIT 6";

    */
     // $this->popularComparisons = $this->db->fetchAll($sql);

    
  }


  public function getImgs($title,$key=0){
      $title = explode(' vs ',$title);
      $title = $title[$key];
      foreach($this->pinfos  as $info){
          if(preg_match("#".preg_quote($title)."#is",$info['title'])){
              return $info;  
          }  
      }
    
  }



  private function getRelatedQuestions(){
          $this->relatedQuestions = Lib_Question::getRelatedQuestions(0, 0, Zend_Registry::get('supercate'));
  }

  
  





}
