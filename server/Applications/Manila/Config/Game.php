<?php

return array(

	'goods'=>array(
		1=>array(
			'id'=>1,
			'color'=>'blue',
			'cells'=>array(1=>3,2=>4,3=>5),
			'gold'=>30,
			//'name'=>'丝绸',
		),
		2=>array(
			'id'=>2,
			'color'=>'yellow',
			'cells'=>array(1=>1,2=>2,3=>3),
			'gold'=>18,
			//'name'=>'人参',
		),
		3=>array(
			'id'=>3,
			'color'=>'green',
			'cells'=>array(1=>3,2=>4,3=>5,4=>5),
			'gold'=>36,
			//'name'=>'玉石',
		),
		4=>array(
			'id'=>4,
			'color'=>'red',
			'cells'=>array(1=>2,2=>3,3=>4),
			'gold'=>24,
			//'name'=>'肉豆蔻',
		),
	),
	// 玩家颜色
	'color'=>array('#00FFFF','#93FF93','#FF0080','#4F9D9D','#C2FF68','#8600FF'),
	// 股票价格
	'stock_list'=>array(0=>0,1=>5,2=>10,3=>20,4=>30),

	// 港口 1~3 修理厂 4~6
	'port'=>array(
		1=>array(
			'id'=>1,
			'price'=>4,
			'reward'=>6,
			'ship'=>1,
		),
		2=>array(
			'id'=>2,
			'price'=>3,
			'reward'=>8,
			'ship'=>2,
		),
		3=>array(
			'id'=>3,
			'price'=>2,
			'reward'=>15,
			'ship'=>3,
		),
		4=>array(
			'id'=>1,
			'price'=>4,
			'reward'=>6,
			'ship'=>1,
		),
		5=>array(
			'id'=>2,
			'price'=>3,
			'reward'=>8,
			'ship'=>2,
		),
		6=>array(
			'id'=>3,
			'price'=>2,
			'reward'=>15,
			'ship'=>3,
		),
	),

	'pilot'=>array( // 领航员
		// 小
		1=>array( 
			'id'=>1,
			'price'=>2,
			'step'=>1,
		),
		// 大
		2=>array( 
			'id'=>2,
			'price'=>5,
			'step'=>2,
		),
	),
	
	'pirate'=>5, //海盗价格
	'insurance'=>10,// 保险公司费用



);



?>