<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);


use \GatewayWorker\Lib\Gateway;
use \Workerman\Lib\Timer;
//use \GatewayWorker\Lib\Db;

class Events
{
   

    /**
     * 新建一个类的静态成员，用来保存数据库实例
     */
    public static $rd = null;
    public static $gameConf;
    /**
     * 进程启动后初始化数据库连接
     */
    public static function onWorkerStart($worker)
    {
        self::$rd = new Redis();
        self::$rd ->connect('127.0.0.1', 6379);
        self::$gameConf = require_once __DIR__."/Config/Game.php";
    }



   /**
    * 有消息时
    * @param int $client_id
    * @param mixed $message
    */
   public static function onMessage($client_id, $message)
   {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:".json_encode($_SESSION)." onMessage:".$message."\n";
        // 客户端传递的是json数据
        $message_data = json_decode($message, true);
        if(!$message_data)
        {
            return;
        }
        /*
            马尼拉 rd结构

            房间信息:  m_room_{$room_id} Hash

            uid =>  array(
                'gold'=>30,
                'name'=>$uid,
                'stock'=>array(0=>1, 1=>3,),  //1~4 四种股票
                'worker'=>3, 工人数量
                'client_id'=>$client_id,
                'uid'=>$uid,
                //'color'=>1,
                'ready'=>0,
            ) 
            
            uid=>client_id : m_room_{$room_id}_player Hash
           
            房间状态： m_room_status_{$room_id} Strng

            array(
                'status'=>0, // 0 准备中  1 已开始
                'round'=>1 //回合数
                'turn'=>array($uid,$uid),//玩家顺序
                'now'=>0,//0~5 //当前回合玩家
                'step'=>0, 1 叫地主 2 买股票 3 选货物 
                'play'=>1, // 1 掷骰子
                'price_info'=>array('uid'=>$uid,'num'=>$price),
                'give_up'=>array($uid=>0,$uid=>0),
                'captain'=>$uid,
                'goods'=>array(1=>2,2=>3,3=>1),// 1~4种货物 位置-1=>颜色
                'ship'=>array(1=>0,2=>0,3=>0),// 轮船起始位置
                'stock_list'=>array(1=>0,2=>1,3=>1,4=>3), 
            )

            轮船：  m_ship_{$room_id} Hash

            shipId =>array(
                'id'=>$shipId,
                'color'=>$colorId,
                'step'=>0, //0~13
                'cell'=>array(1=>$uid,2=>$uid...),
                'status'=>0, 0 正常 1 入港 2 入修理厂
                'pirate'=>$uid, // 轮船被劫持 海盗uid
            )

            港口&修理厂:  m_port_{$room_id} 
            array(
                portId=>$uid,
            )



        */

        // 根据类型执行不同的业务
        switch($message_data['type'])
        {
            // 心跳
            case 'pong':
                return;

            // 登录
            case 'login':
                // 判断是否有房间号
                if(!isset($message_data['room_id']))
                {
                    throw new \Exception("\$message_data['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
                }

                $goToNewRoom = false;
                $saveInfo = false;
                $room_id = $message_data['room_id'];
                $uid = $message_data['uid'];
                $room_key = "m_room_{$room_id}";//房间信息
                $playerList = self::$rd -> hgetall($room_key);
                if(count($playerList)>=6){//最多6人玩
                    $goToNewRoom = true;
                }

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));
                $player_key = "m_room_{$room_id}_player"; // uid和client_id对应表
                self::$rd -> hset($player_key,$uid,$client_id);
                if(isset($playerList[$uid])){ // TODO 玩家重新登录 地图信息同步

                    


                }else{ //玩家首次进入

                     if(isset($room_status['status'])&&$room_status['status']==1){ //比赛已开始
                        $goToNewRoom = true;
                     }else{
                        $saveInfo = true;
                     }
                    
                }

                if($goToNewRoom){ //TODO 去新房间

                     $new_message = array(
                        'type'=>'newRoom',
                        'client_id'=>$client_id, 
                        'uid'=>$uid,
                    );
                     Gateway::sendToCurrentClient(json_encode($new_message));
                    return;
                }


                //$client_name = htmlspecialchars($message_data['client_name']);
                $client_name = $uid;
                $_SESSION['room_id'] = $room_id;
                $_SESSION['client_name'] = $client_name;

                // 获取房间内所有用户列表 
                $clients_list = Gateway::getClientSessionsByGroup($room_id);
                foreach($clients_list as $tmp_client_id=>$item)
                {
                    $clients_list[$tmp_client_id] = $item['client_name'];
                }
                $clients_list[$client_id] = $client_name;

                if($saveInfo){ // 初始化玩家信息 保存到房间

                    $room_status['turn'][] = $uid;
                    self::$rd ->set($room_status_key,serialize($room_status));
                    $userInfo = array(
                        'gold'=>30,
                        'name'=>$client_name,
                        'stock'=>array(),  //1~4 四种股票
                        'worker'=>3, //工人数量
                        'client_id'=>$client_id,
                        'uid'=>$uid,
                    );
                    self::$rd -> hset($room_key,$uid,serialize($userInfo));
                }

                
                $new_message = array(
                    'type'=>$message_data['type'], 
                    'uid'=>$uid,
                    'client_id'=>$client_id, 
                    'client_name'=>htmlspecialchars($client_name), 
                    //'time'=>date('Y-m-d H:i:s'),
                );

                Gateway::sendToGroup($room_id, json_encode($new_message));
                Gateway::joinGroup($client_id, $room_id);
                $my_turn = array_search($uid,$room_status['turn']);
                $new_message['my_id'] = $client_id;

                $room_status['status'] = 0;
                if(isset($room_status['status'])){
                    $new_message['start'] = $room_status['status'];
                }
                
                $new_message['my_turn'] = $my_turn;
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;
            // 拉取当前场上数据  TODO 地图信息尚未加入
            case 'map':

                if(!isset($_SESSION['room_id'])){
                    return;
                }

                $room_id = $_SESSION['room_id'];


                // 获取房间内所有用户列表 
                $clients_list = Gateway::getClientSessionsByGroup($room_id);
                foreach($clients_list as $tmp_client_id=>$item)
                {
                    $clients_list[$tmp_client_id] = $item['client_name'];
                }
                //$clients_list[$client_id] = $client_name;


                $room_key = "m_room_{$room_id}";//房间信息
                $player_key = "m_room_{$room_id}_player"; //uid client_id 对应表

                $uid_to_clients = self::$rd -> hgetall($player_key);
                $playerList = self::$rd -> hgetall($room_key);
                $list = array();
                foreach($playerList as $k=>$v){
                    $c_id = $uid_to_clients[$k];
                    if(isset($clients_list[$c_id])){
                        $list[$k] =  unserialize($v);
                    }
                }

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));


                $turn = $room_status['turn'];
                foreach($turn as $k=>$v){
                    if(isset($list[$v])){
                        $list[$v]['turn'] = $k;
                        $list[$v]['color'] = self::$gameConf['color'][$k];
                    }
                   
                }
                $uid = self::getUid($room_id,$client_id);
                $my_turn = array_search($uid,$room_status['turn']);
                $new_message = array(
                    'type'=>$message_data['type'],
                    'client_id'=>$client_id, 
                    'clients_list'=>$list,
                    'time'=>date('Y-m-d H:i:s'),
                );

                Gateway::sendToGroup($room_id, json_encode($new_message));
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;

            // 开始 （测试版）
            case 'start':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));

                if(isset($room_status['status'])&&$room_status['status']==1){
                    return;
                }else{
                    $room_status['status'] = 1;
                    $room_status['step'] = 1;
                    $room_status['now'] = 0;

                    self::$rd->set($room_status_key,serialize($room_status));
                }
                $clients_list = Gateway::getClientSessionsByGroup($room_id);
                //$playerNum = count($room_status['turn']);
                $stockArr = self::dealStock($room_id);

                foreach($room_status['turn'] as $k=>$uuid){
                    $sInfo = $stockArr['playerStock'][$k];
                    $c_id = self::getClientId($room_id,$uuid);
                    self::sendStockMsg($c_id,$sInfo);
                }
                // foreach($clients_list as $k=>$v){
                //     self::sendStockMsg($k,);
                // }
                

                $nextInfo = array(
                    'next'=>0,
                    //'next_uid'=>$uid,
                );

                $new_message = array(
                    'type'=>'callCaptain',
                    'turn'=>0,
                    'now_price'=>0,
                    'next_info'=>$nextInfo,
                    'price'=>0,
                );

                Gateway::sendToGroup($room_id ,json_encode($new_message));


                return;
            /* 叫地主报价 */
            case 'price':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));

                $step = 0;
                if(isset($room_status['step'])){
                    $step = $room_status['step'];
                }
                if($step != 1){
                    return;
                }

                $now_price = 0;
                $captain = null;
                if(isset($room_status['price_info'])){
                    $now_price = $room_status['price_info']['num'];
                    $captain = $room_status['price_info']['uid'];
                }


                if($message_data['price']<=$now_price){ //报价不高于当前最高价
                    $new_message = array(
                        'type'=>'callCaptain',
                        'message'=>'money_not_enough',
                        'price'=>$now_price,
                    );
                    Gateway::sendToCurrentClient(json_encode($new_message));
                    return;
                }else{
                    //$player_key = "m_room_{$room_id}_player"; //uid client_id 对应表
                    //$uid_to_clients = self::$rd -> hgetall($player_key);
                    //$uid = array_search($client_id, $uid_to_clients);

                    $uid = self::getUid($room_id,$client_id);

                    $myGold = self::getMoney($uid,$room_id);
                    if($myGold<$message_data['price']){
                        $new_message = array(
                            'type'=>'callCaptain',
                            'message'=>'no_money',
                            'price'=>$now_price,
                        );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        return;
                    }

                    if($uid==$captain){ //检查是不是已经产生队长
                        
                    }else{
                        $room_status['price_info']['num'] = $message_data['price'];
                        $room_status['price_info']['uid'] = $uid;

                        $turn = $room_status['turn'];
                        $now = $room_status['now'];
                        $playerNum = count($turn);
                        $give_up = null;
                        if(!empty($room_status['give_up'])){
                            $give_up = $room_status['give_up'];
                        }
                        
                        if(isset($room_status['give_up'][$uid])){//已放弃
                            return;
                        }

                        if($turn[$now] != $uid){
                            return;
                        }
                        $nextInfo = self::getNextPlayer($now,$turn,$give_up);


                        if($nextInfo){
                            $next = $nextInfo['next'];
                            $nextUid = $nextInfo['next_uid'];
                            $room_status['now'] = $next;
                            self::$rd->set($room_status_key,serialize($room_status));
                        }else{// TODO 成为队长



                        }

                    }

                }
                

                $new_message = array(
                    'type'=>'callCaptain',
                    'next_info'=>$nextInfo,
                    'price'=>$message_data['price'],
                    'highest'=>$uid,
                );

                return Gateway::sendToGroup($room_id ,json_encode($new_message));
            /* 放弃队长 */
            case 'giveUp':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                // $player_key = "m_room_{$room_id}_player"; //uid client_id 对应表
                // $uid_to_clients = self::$rd -> hgetall($player_key);
                // $uid = array_search($client_id, $uid_to_clients);
                $uid = self::getUid($room_id,$client_id);

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));

                $step = 0;
                if(isset($room_status['step'])){
                    $step = $room_status['step'];
                }
                if($step != 1){
                    return;
                }

                $turn = $room_status['turn'];
                $now = $room_status['now'];

                $now_price = 0;
                $captain = null;
                if(isset($room_status['price_info'])){
                    $now_price = $room_status['price_info']['num'];
                    $captain = $room_status['price_info']['uid'];
                }


                $playerNum = count($turn);
                $give_up = null;
                if(!empty($room_status['give_up'])){
                    $give_up = $room_status['give_up'];
                }

                if($turn[$now] != $uid){
                    return;
                }

                if(isset($room_status['give_up'][$uid])){//已放弃
                    return;
                }
                
                $room_status['give_up'][$uid] = 0;

                $new_message = array(
                    'type'=>'callCaptain',
                    'give_up'=>1,
                );

                if(count($turn) - count($room_status['give_up']) == 1){ //产生队长  TODO 暂不考虑都放弃的情况
                    //扣钱
                    $res = self::addMoney($captain,$room_id,$now_price,2);

                    if($res){
                        // 初始化数据 进入下一阶段
                        $captain_turn = array_search($captain, $turn);
                        $room_status['now'] = $captain_turn; 
                        $room_status['give_up'] = array(); 
                        $room_status['price'] = 0; 
                        $room_status['captain'] = $captain;
                        $room_status['step'] = 2;
                        $room_status['ship'][1] = 0;
                        $room_status['ship'][2] = 0;
                        $room_status['ship'][3] = 0;
                        self::$rd->set($room_status_key,serialize($room_status));

                        $new_message['captain']['uid'] = $captain;   
                        $new_message['captain']['num'] = $now_price;
                        $new_message['captain']['turn'] = $captain_turn;
                    }else{
                        // TODO 报错

                    } 
                }else{
                    $nextInfo = self::getNextPlayer($now,$turn,$give_up);   
                    $next = $nextInfo['next'];
                    $room_status['now'] = $next;
                    self::$rd->set($room_status_key,serialize($room_status));

                    $new_message['next_info'] = $nextInfo;
                    $new_message['price'] = $now_price;
                }


               
                return Gateway::sendToGroup($room_id ,json_encode($new_message));


            /* 队长购买股票 */

            case 'buystock':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));
                $captain = $room_status['captain'];
                $uid = self::getUid($room_id,$client_id);
                if($captain != $uid){
                    return;
                }
                if(!isset($message_data['stockId'])){
                    return;
                }

                $step = 0;
                if(isset($room_status['step'])){
                    $step = $room_status['step'];
                }
                if($step != 2){
                    return;
                }

                $stockId = $message_data['stockId'];
                if($stockId == 0){ // 放弃购买
                    $room_status['step'] = 3;
                    self::$rd->set($room_status_key,serialize($room_status));

                    $new_message = array(
                        'type'=>'buystock',
                        'give_up'=>1,
                    );
                    return Gateway::sendToGroup($room_id ,json_encode($new_message));
                }else{

                    $lastStock = $room_status['last_stock'];
                    if($lastStock[$stockId] <= 0){ // 卖完
                        $new_message = array(
                            'type'=>'buystock',
                            'message'=>'sold_out',
                        );
                        return;
                    }

                    $price = self::getStockPrice($stockId,$room_status);
                    if($price <= 0){
                        $price = 5;
                    }

                    $myGold = self::getMoney($uid,$room_id);
                    if($myGold < $price){
                        $new_message = array(
                            'type'=>'buystock',
                            'message'=>'no_money',
                        );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        return;
                    }
                    $res = self::addMoney($uid,$room_id,$price,2);
                    if($res){
                        $room_key = "m_room_{$room_id}";//房间信息
                        $userInfo = unserialize(self::$rd -> hget($room_key,$uid));
                        $userInfo['stock'][] = intval($stockId);

                        self::$rd -> hset($room_key,$uid,serialize($userInfo));

                        $room_status['step'] = 3;
                        $room_status['last_stock'][$stockId] -= 1;
                        self::$rd->set($room_status_key,serialize($room_status));

                        $new_message = array(
                            'type'=>'buystock',
                            'stockId'=>$stockId,
                        );

                        Gateway::sendToGroup($room_id ,json_encode($new_message));
                        $new_message['my_stock'] = $userInfo['stock'];

                        return Gateway::sendToCurrentClient(json_encode($new_message));
                    }else{
                        //todo 报错
                    }
                    
                }


            /*   选择货物   */

            case 'chooseGoods':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));
                $captain = $room_status['captain'];
                $uid = self::getUid($room_id,$client_id);
                if($captain != $uid){
                    return;
                }
                if(isset($message_data['goods'])){
                    $goodsId = $message_data['goods'];
                }else{
                    return;
                }
                $step = 0;
                if(isset($room_status['step'])){
                    $step = $room_status['step'];
                }
                if($step != 3){
                    return;
                }

                $goodsArr = array();
                if(isset($room_status['goods'])){
                    $goodsArr = $room_status['goods'];
                }

                if(count($goodsArr) >=3){
                    return;
                }
                if(in_array($goodsId,$goodsArr)){
                    return;
                }

                
                if(empty($room_status['goods'])){
                    $room_status['goods'][1] = $goodsId;
                }else{
                    $room_status['goods'][] = $goodsId;
                }

                self::$rd->set($room_status_key,serialize($room_status));

                $goodsInfo = self::$gameConf['goods'][$goodsId];

                $new_message = array(
                    'type'=>'chooseGoods', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'goods_info'=>$goodsInfo,
                ); 

                if(count($room_status['goods']) >= 3){ //选完
                    $new_message['finish'] = 1;
                }

                
                return Gateway::sendToGroup($room_id ,json_encode($new_message));


            /*  设置轮船起点 */

            case 'setOutset':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                
                if(isset($message_data['step'])){
                    $step = $message_data['step'];
                }else{
                    return;
                }
                if(isset($message_data['shipId'])&&$message_data['shipId']<=3){
                    $shipId = $message_data['shipId'];
                }else{
                    return;
                }

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));
                $captain = $room_status['captain'];
                $uid = self::getUid($room_id,$client_id);
                if($captain != $uid){
                    return;
                }

                if($step>5){ // 最多设置第五格
                    return;
                }

                $room_step = 0;
                if(isset($room_status['step'])){
                    $room_step = $room_status['step'];
                }
                if($room_step != 3){
                    return;
                }

                $room_status['ship'][$shipId] = $step;
                self::$rd->set($room_status_key,serialize($room_status));
                $new_message = array(
                    'type'=>'setOutset', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'ship_id'=>$shipId,
                    'step'=>$step,
                ); 

                return Gateway::sendToGroup($room_id ,json_encode($new_message));


            /*  确认轮船起点  */

            case 'confirmOutset':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];
                
                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));
                $captain = $room_status['captain'];

                $uid = self::getUid($room_id,$client_id);
                if($captain != $uid){
                    return;
                }

                $room_step = 0;
                if(isset($room_status['step'])){
                    $room_step = $room_status['step'];
                }
                if($room_step != 3){
                    return;
                }

                $ship_step = array();
                if(isset($room_status['ship'])){
                    $ship_step = $room_status['ship'];
                }
                
                $total = 0;
                foreach ($ship_step as $k => $v) {
                    if($v > 5){
                        return;
                    }
                    $total += $v;
                }
                if($total != 9){
                    $new_message = array(
                        'type'=>'confirmOutset',
                        'message'=>'not_nine',
                    );
                    return Gateway::sendToCurrentClient(json_encode($new_message));
                }

                // 初始化轮船信息
                $ship_key = "m_ship_{$room_id}"; //轮船
                self::$rd->delete($ship_key); 
                $goodsArr = $room_status['goods'];
                //$goodsConf = self::$gameConf['goods'];
                for($i=1;$i<=3;$i++){
                    if(isset($ship_step[$i])){
                        $step = $ship_step[$i];
                    }else{
                        $step = 0;
                    }
                    $goodsId = $goodsArr[$i];
                    $shipInfo = array(
                        'id' => $i,
                        'goods_id'=>$goodsId,
                        'step' => $step, 
                        'status'=>0,
                        'cells'=>array(),
                    );
                    self::$rd->hset($ship_key,$i,serialize($shipInfo));
                }

                $room_status['round'] = 1; // 回合
                $room_status['step'] = 4;
                self::$rd->set($room_status_key,serialize($room_status));
                $new_message = array(
                    'type'=>'confirmOutset', 
                    'ship_step'=>$ship_step,
                ); 

                return Gateway::sendToGroup($room_id ,json_encode($new_message));


            /*  安排工人  */

            case 'setWorker':

                if(!isset($_SESSION['room_id'])){
                   return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];
                

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));

                $room_step = 0;
                if(isset($room_status['step'])){
                    $room_step = $room_status['step'];
                }
                if($room_step != 4){
                    return;
                }

                if(isset($room_status['play']) && $room_status['play'] == 1){ // 掷骰子的时间
                    return;
                }

                if(isset($room_status['pirate'])){ // 海盗回合
                    return;
                }

                if(isset($room_status['pilot'])){ // 领航员回合
                    return;
                }

                // 回合错误
                if(isset($room_status['round']) && $room_status['round']>3){
                    return;
                }

                $uid = self::getUid($room_id,$client_id);
                $turn = $room_status['turn'];
                $now = $room_status['now'];
                 
                if($turn[$now] != $uid){ // 不是自己回合
                    return;
                }

                $captain = $room_status['captain']; 
                $captain_turn = array_search($captain, $turn);
                $my_turn = array_search($uid, $turn);    
                $nextInfo = self::getNextPlayer($now,$turn);   
                $next = $nextInfo['next'];

                if($message_data['action'] == 'boarding'){ // 上船

                    if(isset($message_data['shipId'])){
                        $shipId = $message_data['shipId'];
                    }else{
                        return;
                    }
                    $ship_key = "m_ship_{$room_id}"; // 轮船
                    $shipInfo = unserialize(self::$rd->hget($ship_key,$shipId));

                    if($shipInfo['status'] == 1){ // 已进港
                        return;
                    }
                    $goodsId = $shipInfo['goods_id'];
                    $shipCells = $shipInfo['cells'];
                    $goodConf = self::$gameConf['goods'];
                    $cellsNum = count($goodConf[$goodsId]['cells']);
                    $shipWorker = count($shipCells);

                    if($shipWorker >= $cellsNum){ // 船满员
                        return; 
                    }

                    if(empty($shipCells)){
                        $shipInfo['cells'][1] = $uid;
                    }else{
                        $shipInfo['cells'][] = $uid;
                    }

                    $n = count($shipInfo['cells']);
                    $price = $goodConf[$goodsId]['cells'][$n];

                    $myGold = self::getMoney($uid,$room_id);
                    if($myGold < $price){
                        $new_message = array(
                            'type'=>'boarding',
                            'message'=>'no_money',
                        );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        return;
                    }
                    $res = self::addMoney($uid,$room_id,$price,2);
                    if($res){
                        self::$rd->hset($ship_key,$shipId,serialize($shipInfo));
                        
                        $room_status['now'] = $next;
                        $play = 0;
                        $pilot = array();
                        if($next == $captain_turn){ // 放置工人结束

                            if($room_status['round'] == 3){ // 最后一轮掷骰子之前  领航员活动
                                $pilot_key = "m_pilot_{$room_id}"; // 领航员
                                $pilotInfo = unserialize(self::$rd->get($pilot_key));
                                if(!empty($pilotInfo)){
                                    // $room_status['pilot'] = 1; // 领航员回合
                                    if(isset($pilotInfo[1])){
                                        $pilotUid = $pilotInfo[1]['uid'];
                                        $pilotId = 1;
                                    }else{
                                        if(isset($pilotInfo[2])){
                                            $pilotUid = $pilotInfo[2]['uid'];
                                            $pilotId = 2;
                                        }else{
                                            $room_status['play'] = 1; // 开始掷骰子
                                            $play = 1;
                                        }
                                    }    
                                    
                                    if(!$play){
                                        $pilotTurn = array_search($pilotUid, $turn);
                                        $room_status['pilot'] = 1; // 领航员回合
                                        $pilot['uid'] = $pilotUid;
                                        $pilot['turn'] = $pilotTurn;
                                        $pilot['id'] = $pilotId;
                                    }
                                   
                                }else{
                                    $room_status['play'] = 1; // 开始掷骰子
                                    $play = 1;
                                }
                            }else{
                                $room_status['play'] = 1; // 开始掷骰子
                                $play = 1;
                            }
                        }
                        self::$rd -> set($room_status_key,serialize($room_status));
                        $userInfo = array(
                            'uid'=>$uid,
                            'turn'=>$my_turn,
                            'color'=>self::$gameConf['color'][$my_turn],
                        );
                        $new_message = array(
                            'type'=>'boarding', 
                            'cell'=>$n,
                            'play'=>$play,
                            // 'pilot'=>$pilot,
                            'user_info'=>$userInfo,
                            'next'=>$next,
                            'ship_id'=>$shipId,
                            'goods_id'=>$goodsId,
                        ); 

                    }else{
                        return;
                    }


                }elseif($message_data['action'] == 'port'){// 押港口或修理厂

                    if(isset($message_data['portId'])){
                        $portId = $message_data['portId'];
                    }else{
                        return;
                    }

                    $port_key = "m_port_{$room_id}"; // 港口&修理厂
                    $portInfo = unserialize(self::$rd->get($port_key));

                    if(isset($portInfo[$portId])){ // 已经有人
                        return;
                    }

                    $portConf = self::$gameConf['port'];
                    if(isset($portConf[$portId])){
                        $portMsg = $portConf[$portId];
                    }else{
                        return;
                    }

                    $price = $portMsg['price'];

                    $myGold = self::getMoney($uid,$room_id);
                    if($myGold < $price){
                        $new_message = array(
                            'type'=>'boarding',
                            'message'=>'no_money',
                        );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        return;
                    }
                    $res = self::addMoney($uid,$room_id,$price,2);
                    if($res){

                        $portInfo[$portId] = $uid;
                        self::$rd->set($port_key,serialize($portInfo));

                        $room_status['now'] = $next;
                        $play = 0;
                        $pilot = array();
                        if($next == $captain_turn){ // 放置工人结束

                            if($room_status['round'] == 3){ // 最后一轮掷骰子之前  领航员活动
                                $pilot_key = "m_pilot_{$room_id}"; // 领航员
                                $pilotInfo = unserialize(self::$rd->get($pilot_key));
                                if(!empty($pilotInfo)){
                                    // $room_status['pilot'] = 1; // 领航员回合
                                    if(isset($pilotInfo[1])){
                                        $pilotUid = $pilotInfo[1]['uid'];
                                        $pilotId = 1;
                                    }else{
                                        if(isset($pilotInfo[2])){
                                            $pilotUid = $pilotInfo[2]['uid'];
                                            $pilotId = 2;
                                        }else{
                                            $room_status['play'] = 1; // 开始掷骰子
                                            $play = 1;
                                        }
                                    }    
                                    
                                    if(!$play){
                                        $pilotTurn = array_search($pilotUid, $turn);
                                        $room_status['pilot'] = 1; // 领航员回合
                                        $pilot['uid'] = $pilotUid;
                                        $pilot['turn'] = $pilotTurn;
                                        $pilot['id'] = $pilotId;
                                    }
                                }else{
                                    $room_status['play'] = 1; // 开始掷骰子
                                    $play = 1;
                                }
                            }else{
                                $room_status['play'] = 1; // 开始掷骰子
                                $play = 1;
                            }
                        }
                        self::$rd -> set($room_status_key,serialize($room_status));
                        $userInfo = array(
                            'uid'=>$uid,
                            'turn'=>$my_turn,
                            'color'=>self::$gameConf['color'][$my_turn],
                        );
                        $new_message = array(
                            'type'=>'port', 
                            'play'=>$play,
                            // 'pilot'=>$pilot,
                            'user_info'=>$userInfo,
                            'next'=>$next,
                            'port_id'=>$portId,
                        ); 

                    }else{
                        return;
                    }


                }elseif($message_data['action'] == 'pilot'){ // 领航员

                    if(isset($message_data['pilotId'])){
                        $pilotId = $message_data['pilotId'];
                    }else{
                        return;
                    }

                    $pilot_key = "m_pilot_{$room_id}"; // 领航员
                    $pilotInfo = unserialize(self::$rd->get($pilot_key));

                    if(isset($pilotInfo[$pilotId])){ // 已经有人
                        return;
                    }

                    $pilotConf = self::$gameConf['pilot'];
                    if(isset($pilotConf[$pilotId])){
                        $pilotMsg = $pilotConf[$pilotId];
                    }else{
                        return;
                    }

                    $price = $pilotMsg['price'];

                    $myGold = self::getMoney($uid,$room_id);
                    if($myGold < $price){
                        $new_message = array(
                            'type'=>'boarding',
                            'message'=>'no_money',
                        );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        return;
                    }
                    $res = self::addMoney($uid,$room_id,$price,2);

                    if($res){
                        $pilotInfo[$pilotId]['uid'] = $uid;
                        $pilotInfo[$pilotId]['status'] = 0;
                        self::$rd->set($pilot_key,serialize($pilotInfo));

                        $room_status['now'] = $next;
                        $play = 0;
                        $pilot = array();
                        if($next == $captain_turn){ // 放置工人结束

                            if($room_status['round'] == 3){ // 最后一轮掷骰子之前  领航员活动
                                // $pilot_key = "m_pilot_{$room_id}"; // 领航员
                                // $pilotInfo = unserialize(self::$rd->get($pilot_key));
                                if(!empty($pilotInfo)){
                                    if(isset($pilotInfo[1])){
                                        $pilotUid = $pilotInfo[1]['uid'];
                                        $nextPilotId = 1;
                                    }else{
                                        if(isset($pilotInfo[2])){
                                            $pilotUid = $pilotInfo[2]['uid'];
                                            $nextPilotId = 2;
                                        }else{
                                            $room_status['play'] = 1; // 开始掷骰子
                                            $play = 1;
                                        }
                                    }    
                                    
                                    if(!$play){
                                        $pilotTurn = array_search($pilotUid, $turn);
                                        $room_status['pilot'] = 1; // 领航员回合
                                        $pilot['uid'] = $pilotUid;
                                        $pilot['turn'] = $pilotTurn;
                                        $pilot['id'] = $nextPilotId;
                                    }
                                }else{
                                    $room_status['play'] = 1; // 开始掷骰子
                                    $play = 1;
                                }
                            }else{
                                $room_status['play'] = 1; // 开始掷骰子
                                $play = 1;
                            }

                            
                        }
                        self::$rd -> set($room_status_key,serialize($room_status));
                        $userInfo = array(
                            'uid'=>$uid,
                            'turn'=>$my_turn,
                            'color'=>self::$gameConf['color'][$my_turn],
                        );
                        $new_message = array(
                            'type'=>'pilot', 
                            'play'=>$play,
                            // 'pilot'=>$pilot,
                            'user_info'=>$userInfo,
                            'next'=>$next,
                            'pilot_id'=>$pilotId,
                        ); 

                    }else{
                        return;
                    }


                }elseif($message_data['action'] == 'pirate'){// 海盗

                    $pirate_key = "m_pirate_{$room_id}"; // 海盗
                    $pirateInfo = unserialize(self::$rd->get($pirate_key));

                    if(count($pirateInfo) >= 2){ // 海盗已满
                        return;
                    }
                    $price = self::$gameConf['pirate'];
                    $myGold = self::getMoney($uid,$room_id);
                    if($myGold < $price){
                        $new_message = array(
                            'type'=>'boarding',
                            'message'=>'no_money',
                        );
                        Gateway::sendToCurrentClient(json_encode($new_message));
                        return;
                    }
                    $res = self::addMoney($uid,$room_id,$price,2);

                    if($res){
                        $pirateInfo[] = array(
                            'uid' => $uid,
                            'status' => 0,
                        );
                        self::$rd->set($pirate_key,serialize($pirateInfo));
                        $pirateId = count($pirateInfo);

                        $room_status['now'] = $next;
                        $play = 0;
                        $pilot = array();
                        if($next == $captain_turn){ // 放置工人结束

                            if($room_status['round'] == 3){ // 最后一轮掷骰子之前  领航员活动
                                $pilot_key = "m_pilot_{$room_id}"; // 领航员
                                $pilotInfo = unserialize(self::$rd->get($pilot_key));
                                if(!empty($pilotInfo)){
                                    if(isset($pilotInfo[1])){
                                        $pilotUid = $pilotInfo[1]['uid'];
                                        $pilotId = 1;
                                    }else{
                                        if(isset($pilotInfo[2])){
                                            $pilotUid = $pilotInfo[2]['uid'];
                                            $pilotId = 2;
                                        }else{
                                            $room_status['play'] = 1; // 开始掷骰子
                                            $play = 1;
                                        }
                                    }    
                                    
                                    if(!$play){
                                        $pilotTurn = array_search($pilotUid, $turn);
                                        $room_status['pilot'] = 1; // 领航员回合
                                        $pilot['uid'] = $pilotUid;
                                        $pilot['turn'] = $pilotTurn;
                                        $pilot['id'] = $pilotId;
                                    }
                                }else{
                                    $room_status['play'] = 1; // 开始掷骰子
                                    $play = 1;
                                }
                            }else{
                                $room_status['play'] = 1; // 开始掷骰子
                                $play = 1;
                            }
                        }
                        self::$rd -> set($room_status_key,serialize($room_status));
                        $userInfo = array(
                            'uid'=>$uid,
                            'turn'=>$my_turn,
                            'color'=>self::$gameConf['color'][$my_turn],
                        );
                        $new_message = array(
                            'type'=>'pirate', 
                            'play'=>$play,
                            'user_info'=>$userInfo,
                            'next'=>$next,
                            'pirate_id'=>$pirateId,
                        ); 
                    }else{
                        return;
                    }

                }elseif($message_data['action'] == 'insurance'){ // 保险公司
                    $insurance_key = "m_insurance_{$room_id}"; // 保险
                    $insuranceInfo = unserialize(self::$rd->get($insurance_key));   
                    if(!empty($insuranceInfo)){ // 有人
                        return;
                    }

                    $price = self::$gameConf['insurance'];
                    $myGold = self::getMoney($uid,$room_id);

                    $res = self::addMoney($uid,$room_id,$price,1); // 加钱

                    if($res){
                        $insuranceInfo[] = $uid;
                        self::$rd->set($insurance_key,serialize($insuranceInfo));

                        $room_status['now'] = $next;
                        $play = 0;
                        $pilot = array();
                        if($next == $captain_turn){ // 放置工人结束

                            if($room_status['round'] == 3){ // 最后一轮掷骰子之前  领航员活动
                                $pilot_key = "m_pilot_{$room_id}"; // 领航员
                                $pilotInfo = unserialize(self::$rd->get($pilot_key));
                                if(!empty($pilotInfo)){
                                    if(isset($pilotInfo[1])){
                                        $pilotUid = $pilotInfo[1]['uid'];
                                        $pilotId = 1;
                                    }else{
                                        if(isset($pilotInfo[2])){
                                            $pilotUid = $pilotInfo[2]['uid'];
                                            $pilotId = 2;
                                        }else{
                                            $room_status['play'] = 1; // 开始掷骰子
                                            $play = 1;
                                        }
                                    }    
                                    
                                    if(!$play){
                                        $pilotTurn = array_search($pilotUid, $turn);
                                        $room_status['pilot'] = 1; // 领航员回合
                                        $pilot['uid'] = $pilotUid;
                                        $pilot['turn'] = $pilotTurn;
                                        $pilot['id'] = $pilotId;
                                    }
                                }else{
                                    $room_status['play'] = 1; // 开始掷骰子
                                    $play = 1;
                                }
                            }else{
                                $room_status['play'] = 1; // 开始掷骰子
                                $play = 1;
                            }
                        }
                        self::$rd -> set($room_status_key,serialize($room_status));
                        $userInfo = array(
                            'uid'=>$uid,
                            'turn'=>$my_turn,
                            'color'=>self::$gameConf['color'][$my_turn],
                        );
                        $new_message = array(
                            'type'=>'insurance', 
                            'play'=>$play,
                            'user_info'=>$userInfo,
                            'next'=>$next,
                        ); 
                    }else{
                        return;
                    }
                }elseif($message_data['action'] == 'giveUp'){ // 放弃放置工人
                    $room_status['now'] = $next;
                    $play = 0;
                    $pilot = array();
                    if($next == $captain_turn){ // 放置工人结束
                        if($room_status['round'] == 3){ // 最后一轮掷骰子之前  领航员活动
                            $pilot_key = "m_pilot_{$room_id}"; // 领航员
                            $pilotInfo = unserialize(self::$rd->get($pilot_key));
                            if(!empty($pilotInfo)){
                                if(isset($pilotInfo[1])){
                                    $pilotUid = $pilotInfo[1]['uid'];
                                    $pilotId = 1;
                                }else{
                                    if(isset($pilotInfo[2])){
                                        $pilotUid = $pilotInfo[2]['uid'];
                                        $pilotId = 2;
                                    }else{
                                        $room_status['play'] = 1; // 开始掷骰子
                                        $play = 1;
                                    }
                                }    
                                
                                if(!$play){
                                    $pilotTurn = array_search($pilotUid, $turn);
                                    $room_status['pilot'] = 1; // 领航员回合
                                    $pilot['uid'] = $pilotUid;
                                    $pilot['turn'] = $pilotTurn;
                                    $pilot['id'] = $pilotId;
                                }
                            }else{
                                $room_status['play'] = 1; // 开始掷骰子
                                $play = 1;
                            }
                        }else{
                            $room_status['play'] = 1; // 开始掷骰子
                            $play = 1;
                        }
                    }
                    self::$rd -> set($room_status_key,serialize($room_status));

                    $new_message = array(
                        'type'=>'workerGiveUp', 
                        'play'=>$play,
                        'next'=>$next,
                    ); 
                }
                
                if(!empty($pilot)){
                    $new_message['pilot'] = $pilot;
                }


                return Gateway::sendToGroup($room_id ,json_encode($new_message));


            /*  掷骰子  */

            case 'playPoint':
                if(!isset($_SESSION['room_id'])){
                   return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];


                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));

                $room_step = 0;
                if(isset($room_status['step'])){
                    $room_step = $room_status['step'];
                }
                if($room_step != 4){
                    return;
                }
                if($room_status['round'] > 3){ // 三回合结束
                    return;
                }
                $last = 0;
                if($room_status['round'] == 3){
                    $last = 1; // 最后一次掷骰子
                }

                $captain = $room_status['captain'];
                $uid = self::getUid($room_id,$client_id);
                if($captain != $uid){
                    return;
                }

                $turn = $room_status['turn'];
                $now = $room_status['now'];
                 
                if($turn[$now] != $uid){ // 不是自己回合
                    return;
                }
                if($room_status['play'] != 1){ // 没到掷骰子的时间
                    return;
                }

                $num = $message_data['num'];
                if($num >0){
                    $pointArr = array();
                    for($i=1;$i<=$num;$i++){
                        $rand = mt_rand(1,6);
                        $pointArr[$i] = $rand;
                    }

                }else{
                    return;
                }
                
                // $pointArr[1] = 3;
                // $pointArr[2] = 3;
                // $pointArr[3] = 1;
                $pirateRound = 0; // 是否是海盗回合
                $ship_key = "m_ship_{$room_id}"; //轮船
                for($i=1;$i<=$num;$i++){
                    $shipInfo = unserialize(self::$rd->hget($ship_key,$i));
                    $shipStep = $shipInfo['step'];
                    if($shipStep > 13){ // 已进港
                        unset($pointArr[$i]);
                        continue;
                    }
                    $shipInfo['step'] = $shipStep + $pointArr[$i];
                    if($shipInfo['step'] > 13){ // 进港
                        $shipInfo['status'] = 1;
                    }elseif($shipInfo['step'] == 13){
                        $pirateRound = 1;
                    }

                    self::$rd->hset($ship_key,$i,serialize($shipInfo));
                }
                $pirate = array();
                if($pirateRound){
                    $pirate_key = "m_pirate_{$room_id}"; // 海盗
                    $pirateInfo = unserialize(self::$rd->get($pirate_key));
                    if(!empty($pirateInfo)){
                        $turn = $room_status['turn'];
                        $pirateUid = $pirateInfo[0]['uid'];
                        $pirateTurn = array_search($pirateUid, $turn);
                        $room_status['pirate'] = 1; // 海盗回合

                        $pirate['uid'] = $pirateUid;
                        $pirate['turn'] = $pirateTurn;
                    }
                }
                $room_status['play'] = 0;
                $room_status['round'] += 1; // 回合
                self::$rd -> set($room_status_key,serialize($room_status));
                $new_message = array(
                    'type'=>'playPoint',
                    'point'=>$pointArr, 
                );
                if(!empty($pirate)){
                    $new_message['pirate'] = $pirate;
                }else{
                    if($last){ // 本次航行结束
                        $balanceInfo = self::balance($room_id);
                        self::initRound($room_id); // 初始化
                        $new_message['result'] = $balanceInfo;
                    }
                }
                return Gateway::sendToGroup($room_id ,json_encode($new_message));


            /*  海盗登船  */

            case 'pirateBoarding':

                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];
                
                if(isset($message_data['ship_id'])){
                    $shipId = $message_data['ship_id'];
                }

                $giveUp = false;
                if(isset($message_data['action']) && $message_data['action']=='give_up'){
                    $giveUp = true;
                }

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));

                $room_step = 0;
                if(isset($room_status['step'])){
                    $room_step = $room_status['step'];
                }
                if($room_step != 4){
                    return;
                }

                if($room_status['play'] == 1){ // 掷骰子的时间
                    return;
                }

                $round = $room_status['round']; 
                if($round<3 || $round>4){
                    return;
                }

                if($room_status['pirate'] != 1){ // 不是海盗回合
                    return;
                }

                if(!$giveUp){
                    $ship_key = "m_ship_{$room_id}"; //轮船
                    $shipInfo = unserialize(self::$rd->hget($ship_key,$shipId));
                    if($shipInfo['step'] != 13){
                        return;
                    }
                }
                
                $uid = self::getUid($room_id,$client_id);
                $pirate_key = "m_pirate_{$room_id}"; // 海盗
                $pirateInfo = unserialize(self::$rd->get($pirate_key));

                $turn = $room_status['turn'];
                $my_turn = array_search($uid, $turn);   
                $userInfo = array(
                    'uid'=>$uid,
                    'turn'=>$my_turn,
                    'color'=>self::$gameConf['color'][$my_turn],
                );

                $pirateId = null;
                for($i=0;$i<=1;$i++){
                    if($pirateInfo[$i]['status'] != 1 && !isset($pirateInfo[$i]['round'][$round])){
                        $pirateUid = $pirateInfo[$i]['uid'];
                        if($pirateUid != $uid){
                            return;
                        }
                        $pirateId = $i;
                        break;
                    }
                }

                if($pirateId === null){
                    return;
                }else{

                    if($giveUp){ // 放弃登船
                        $pirateInfo[$pirateId]['round'][$round] = 1; 
                        self::$rd->set($pirate_key,serialize($pirateInfo));

                        $nextPirate = $pirateId + 1;
                        if(isset($pirateInfo[$nextPirate])){
                            $nextUid = $pirateInfo[$nextPirate]['uid'];
                            $next = array_search($nextUid, $turn);
                            $pirate = 1;
                        }else{
                            $next = $room_status['now'];
                            $pirate = 0;
                        }

                        $new_message = array(
                            'type'=>'pirateBoarding',
                            'action'=>'giveup',
                            'next'=>$next,
                            'pirate'=>$pirate,
                        );
                    }else{ // 登船
                        $otherShip = false;
                        $allShip = self::$rd->hgetall($ship_key);
                        foreach($allShip as $k=>$v){
                           $ship = unserialize($v);
                           if($ship['step'] == 13 && $k != $shipId){
                                $otherShip = true;
                                break;
                           }
                        }

                        if($otherShip){
                            $nextPirate = $pirateId + 1;
                            if(isset($pirateInfo[$nextPirate])){
                                $nextUid = $pirateInfo[$nextPirate]['uid'];
                                $next = array_search($nextUid, $turn);
                                $pirate = 1;
                                //$room_status['pirate'] = 1;// 海盗回合
                            }else{
                                $next = $room_status['now'];
                                $pirate = 0;
                                unset($room_status['pirate']);
                            }
                        }else{
                            $next = $room_status['now'];
                            $pirate = 0;
                            unset($room_status['pirate']);
                        }

                        self::$rd->set($room_status_key,serialize($room_status));
                        $goodsId = $shipInfo['goods_id'];
                        if($round == 3){ // 上船
                            $shipCells = $shipInfo['cells'];
                            $goodConf = self::$gameConf['goods'];
                            $cellsNum = count($goodConf[$goodsId]['cells']);
                            $shipWorker = count($shipCells);

                            if($shipWorker >= $cellsNum){ // 船满员
                                $new_message = array(
                                    'type'=>'pirateBoarding',
                                    'action'=>'boarding',
                                    'message'=>'full',
                                );
                                Gateway::sendToCurrentClient(json_encode($new_message));
                                return; 
                            }

                            if(empty($shipCells)){
                                $shipInfo['cells'][1] = $uid;
                            }else{
                                $shipInfo['cells'][] = $uid;
                            }

                            $cell = count($shipInfo['cells']);
                            $pirateInfo[$pirateId]['status'] = 1; 
                            self::$rd->set($pirate_key,serialize($pirateInfo));
                            self::$rd->hset($ship_key,$shipId,serialize($shipInfo));

                            $new_message = array(
                                'type'=>'pirateBoarding',
                                'action'=>'boarding',
                                'ship_id'=>$shipId,
                                'user_info'=>$userInfo,
                                'cell'=>$cell,
                                'next'=>$next,
                                'pirate'=>$pirate, // 下回合还是海盗回合
                                'goods_id'=>$goodsId,
                            );

                        }elseif($round == 4){ // 劫船

                            unset($shipInfo['cells']);
                            $shipInfo['cells'][1] = $uid;
                            $shipInfo['pirate'] = $uid;
                            $shipInfo['pirate_id'] = $pirateId;


                            $pirateInfo[$pirateId]['status'] = 1; 
                            self::$rd->set($pirate_key,serialize($pirateInfo));
                            self::$rd->hset($ship_key,$shipId,serialize($shipInfo));

                            $new_message = array(
                                'type'=>'pirateBoarding',
                                'action'=>'robbery',
                                'ship_id'=>$shipId,
                                'user_info'=>$userInfo,
                                'next'=>$next,
                                'pirate'=>$pirate,
                                'goods_id'=>$goodsId,
                            );

                        }

                    }

                }

                return Gateway::sendToGroup($room_id ,json_encode($new_message));


            /*   海盗选择进港或去修理厂  */

            case 'pirateChoose':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                if(isset($message_data['ship_id'])){
                    $shipId = $message_data['ship_id'];
                }else{
                    return;
                }

                if(isset($message_data['action'])){
                    $action = $message_data['action'];
                }else{
                    return;
                }

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));

                $room_step = 0;
                if(isset($room_status['step'])){
                    $room_step = $room_status['step'];
                }
                if($room_step != 4){
                    return;
                }
                $round = $room_status['round']; 
                if($round != 4){
                    return;
                }

                $ship_key = "m_ship_{$room_id}"; //轮船
                $shipInfo = unserialize(self::$rd->hget($ship_key,$shipId));
                if(!isset($shipInfo['pirate'])){ // 轮船没有被劫持
                    return;
                }

                $uid = self::getUid($room_id,$client_id);
                if($shipInfo['pirate'] != $uid){ // 不是海盗
                    return;
                }

                if($action == 1){ // 进港
                    $shipInfo['status'] = 1;
                }elseif($action == 2){ // 进修理厂
                    $shipInfo['status'] = 2;
                }
                self::$rd->hset($ship_key,$shipId,serialize($shipInfo));

                $otherShip = false;
                $allShip = self::$rd->hgetall($ship_key);
                foreach($allShip as $k=>$v){
                   $ship = unserialize($v);
                   if($ship['step'] == 13 && $k != $shipId){
                        $otherShip = true;
                        break;
                   }
                }
                $turn = $room_status['turn'];
                if($otherShip){
                    $pirate_key = "m_pirate_{$room_id}"; // 海盗
                    $pirateInfo = unserialize(self::$rd->get($pirate_key));
                    $pirateId = $shipInfo['pirate_id'];
                    $nextPirate = $pirateId + 1;
                    if(isset($pirateInfo[$nextPirate])){
                        $nextUid = $pirateInfo[$nextPirate]['uid'];
                        $turn = $room_status['turn'];
                        $next = array_search($nextUid, $turn);
                        $pirate = 1;
                        //$room_status['pirate'] += 1;// 海盗回合
                    }else{
                        $next = $room_status['now'];
                        $pirate = 0;
                        unset($room_status['pirate']);
                    }
                }else{
                    $next = $room_status['now'];
                    $pirate = 0;
                    unset($room_status['pirate']);
                }
                self::$rd->set($room_status_key,serialize($room_status));
                $new_message = array(
                    'type'=>'pirateChoose',
                    'action'=>$action,
                    'next'=>$next,
                    'pirate'=>$pirate,
                    'ship_id'=>$shipId,

                );

                if(!$pirate){
                    $balanceInfo = self::balance($room_id);
                    self::initRound($room_id); // 初始化
                    $new_message['result'] = $balanceInfo;
                }
                return Gateway::sendToGroup($room_id ,json_encode($new_message));


            /*  领航员  */
            case 'pilotChoose':
                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];
                
                if(isset($message_data['pilot_id'])){
                    $pilotId = $message_data['pilot_id'];
                }else{
                    return;
                }

                if(isset($message_data['ship_id'])){
                    $shipId = $message_data['ship_id'];
                }else{
                    return;
                }

                if(isset($message_data['step'])){
                    $step = abs($message_data['step']);
                    $move = $message_data['step'];
                    $sign = $step / $move;
                }else{
                    return;
                }
                if($step == 0){
                    return;
                }
                $giveUp = false;
                if(isset($message_data['action']) && $message_data['action']=='give_up'){
                    $giveUp = true;
                }

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));

                $room_step = 0;
                if(isset($room_status['step'])){
                    $room_step = $room_status['step'];
                }
                // if(isset($room_status['step_type'])){
                //     $step_type = $room_status['step_type'];
                // }else{
                //     return;
                // }

                if($room_step != 4){
                    return;
                }

                if($room_status['play'] == 1){ // 掷骰子的时间
                    return;
                }

                $round = $room_status['round']; 
                if($round != 3){
                    return;
                }

                if($room_status['pilot'] != 1){ // 不是领航员回合
                    return;
                }

                if($giveUp){
                    if($pilotInfo[$pilotId]['status'] == 2){
                        return;
                    }
                }


                $pilot_key = "m_pilot_{$room_id}"; // 领航员
                $pilotInfo = unserialize(self::$rd->get($pilot_key));

                if($pilotId == 1){ // 小领航
                    if($pilotInfo[$pilotId]['status'] != 0){
                        return;
                    }
                    $max_step = 1;
                    $step = $max_step;
                    $finish = 1;
                }elseif($pilotId == 2){ // 大领航
                    if(isset($pilotInfo[$pilotId]['status']) && $pilotInfo[$pilotId]['status'] == 1){
                        return;
                    }
                    if(isset($pilotInfo[1]['status']) && $pilotInfo[1]['status'] == 0){
                        return;
                    }
                    $max_step = 2;
                    if($pilotInfo[$pilotId]['status'] == 2){
                        $step = 1;
                        $finish = 1; 
                    }else{
                        if($step>=2){
                            $step = 2;
                            $finish = 1;
                        }else{
                            $finish = 0;
                        }
                    }

                }



                $uid = self::getUid($room_id,$client_id);
                $turn = $room_status['turn'];
                if($pilotInfo[$pilotId]['uid'] != $uid){
                    return;
                }
                $ship_key = "m_ship_{$room_id}"; //轮船
                $shipInfo = unserialize(self::$rd->hget($ship_key,$shipId));
                $shipInfo['step'] += $step * $sign;
                // if($step_type == 1){
                //     $shipInfo['step'] += $step;
                // }elseif($step_type == 2){
                //     $shipInfo['step'] -= $step;
                // }

                if($shipInfo['step'] > 13){ // 进港
                    $shipInfo['status'] = 1;
                }

                self::$rd->hset($ship_key,$shipId,serialize($shipInfo));
                $pilot = 0;
                $play = 0;
                if($pilotId == 1){
                    $haveShip = false;
                    $allShip = self::$rd->hgetall($ship_key);
                    foreach($allShip as $k=>$v){
                       $ship = unserialize($v);
                       if($shipInfo['status'] == 0){
                            $haveShip = true;
                            break;
                       }
                    }

                    if($haveShip && isset($pilotInfo[2])){
                        $pilot = 1;
                        $nextUid = $pilotInfo[2]['uid'];
                        $turn = $room_status['turn'];
                        $next = array_search($nextUid, $turn);
                    }else{
                        $next = $room_status['now'];
                        $play = 1;
                        $room_status['play'] = 1;
                        unset($room_status['pilot']);
                        self::$rd->set($room_status_key,serialize($room_status));
                    }

                    $pilotInfo[$pilotId]['status'] = 1;

                }else{
                    if($finish){
                        $next = $room_status['now'];
                        $play = 1;
                        unset($room_status['pilot']);
                        $room_status['play'] = 1;
                        self::$rd->set($room_status_key,serialize($room_status));

                        $pilotInfo[$pilotId]['status'] = 1;
                        self::$rd->set($pilot_key,serialize($pilotInfo));
                    }else{
                        $pilot = 1;
                        $next = array_search($uid, $turn);
                        $pilotInfo[$pilotId]['status'] = 2;

                    }
                }
               self::$rd->set($pilot_key,serialize($pilotInfo));

                $new_message = array(
                    'type'=>'pilotChoose',
                    // 'action'=>$action,
                    'next'=>$next,
                    'pilot'=>$pilot,
                    'ship_id'=>$shipId,
                    'step'=>$shipInfo['step'],
                    'finish'=>$finish,
                    'play'=>$play,

                );
                return Gateway::sendToGroup($room_id ,json_encode($new_message));

            case 'ready':

                if(!isset($_SESSION['room_id'])){
                   return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                $uid = self::getUid($room_id,$client_id);

                $room_key = "m_room_{$room_id}";//房间信息 
                $userInfo = unserialize(self::$rd->hget($room_key,$uid));
                $userInfo['ready'] = 1;
                self::$rd->hset($room_key,$uid,serialize($userInfo));

                $allReady = self::getUserReadyInfo($room_id);
                $new_message = array(
                    'type'=>'ready',
                    'all_ready'=>$allReady,

                );
                return Gateway::sendToGroup($room_id ,json_encode($new_message));

            //发言
            case 'say':

                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];
                
                // 私聊
                if($message_data['to_client_id'] != 'all')
                {
                    $new_message = array(
                        'type'=>'say',
                        'from_client_id'=>$client_id, 
                        'from_client_name' =>$client_name,
                        'to_client_id'=>$message_data['to_client_id'],
                        'content'=>"<b>对你说: </b>".nl2br(htmlspecialchars($message_data['content'])),
                        'time'=>date('Y-m-d H:i:s'),
                    );
                    Gateway::sendToClient($message_data['to_client_id'], json_encode($new_message));
                    $new_message['content'] = "<b>你对".htmlspecialchars($message_data['to_client_name'])."说: </b>".nl2br(htmlspecialchars($message_data['content']));
                    return Gateway::sendToCurrentClient(json_encode($new_message));
                }
                
                $new_message = array(
                    'type'=>'say', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'content'=>nl2br(htmlspecialchars($message_data['content'])),
                    'time'=>date('Y-m-d H:i:s'),
                );
                return Gateway::sendToGroup($room_id ,json_encode($new_message));

            case 'test':

                if(!isset($_SESSION['room_id'])){
                    return;
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                $arr = self::dealStock(5);
                $new_message = array(
                    'type'=>'test', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'arr'=>$arr,
                    
                );
                return Gateway::sendToGroup($room_id ,json_encode($new_message));
        }
   }
   
   /**
    * 当客户端断开连接时
    * @param integer $client_id 客户端id
    */
   public static function onClose($client_id)
   {
       // debug
       echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";
       // 从房间的客户端列表中删除
       if(isset($_SESSION['room_id']))
       {
           $room_id = $_SESSION['room_id'];
           $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
           Gateway::sendToGroup($room_id, json_encode($new_message));
       }
   }

   // 下一回合操作的玩家
   public static function getNextPlayer($now,$turn,$give_up=null){

        $playNum = count($turn) - 1; //从0开始
        $my = $now;
        $info = array();
        if($give_up){
            while(1){
                if($now>=$playNum){
                    $next = 0;
                }else{
                    $next = $now + 1;
                }

                if($next==$my){
                    return false;
                }

                $nextUid = $turn[$next];
                if(isset($give_up[$nextUid])){
                    $now = $next;
                }else{

                    $info['next'] = $next;
                    $info['next_uid'] = $nextUid;
                    return $info;
                }

            }  
        }else{
            if($now>=$playNum){
                $next = 0;
            }else{
                $next = $now + 1;
            }

            $nextUid = $turn[$next];
            $info['next'] = $next;
            $info['next_uid'] = $nextUid;
            return $info;
        }
     

   }
   // 发送抽取的股票信息
   public static function sendStockMsg($client_id,$myStock){
        $new_message = array(
            'type'=>'stockMsg',
            'myStock'=>$myStock,
        );
        return Gateway::sendToClient($client_id,json_encode($new_message));

   }

   /*

    房间信息:  m_room_{$room_id} Hash
    uid => array(
        'gold'=>30,
        'name'=>$uid,
        'stock'=>array(0=>1, 1=>3,),  //1~4 四种股票
        'worker'=>3, 工人数量
        'client_id'=>$client_id,
        'uid'=>$uid,
    ) 

   */
   // 加减金币
   public static function addMoney($uid,$room_id,$num,$type){
        if($num>0){
            $room_key = "m_room_{$room_id}";//房间信息
            $userInfo = unserialize(self::$rd->hget($room_key,$uid));
            $gold = $userInfo['gold'];
            if($type==1){ //加
                $gold += $num;
            }elseif($type==2){ //减
                $gold -= $num;
            }
            if($gold<0){
                return false;
            }
            $userInfo['gold'] = $gold;
            self::$rd->hset($room_key,$uid,serialize($userInfo));
            self::moneyRefresh($uid,$room_id);
            return true;
        }else{
            return false;
        }

   }
   // 获取当前金币
   public static function getMoney($uid,$room_id){

        $room_key = "m_room_{$room_id}";//房间信息
        $userInfo = unserialize(self::$rd->hget($room_key,$uid));

        $gold = $userInfo['gold'];

        return $gold;

   }
   // 刷新玩家金币显示
   public static function moneyRefresh($uid,$room_id){

        $gold = self::getMoney($uid,$room_id);
        $room_status_key = "m_room_status_{$room_id}";//房间状态
        $room_status = unserialize(self::$rd->get($room_status_key));
        $turn = array_search($uid,$room_status['turn']);
        $new_message = array(
            'type'=>'moneyRefresh',
            'uid'=>$uid,
            'gold'=>$gold,
            'turn'=>$turn,
        );
        return Gateway::sendToGroup($room_id ,json_encode($new_message));
   }
   // 发牌
   public static function dealStock($room_id){

        $room_status_key = "m_room_status_{$room_id}";//房间状态
        $room_status = unserialize(self::$rd->get($room_status_key));
        $playerNum = count($room_status['turn']);
        $stock = array();
        for($i=1;$i<=5;$i++){
            $stock[] = 1;
            $stock[] = 2;
            $stock[] = 3;
            $stock[] = 4;
        }
       
        shuffle($stock);
        $playerStock = array();
        for($i=0;$i<$playerNum;$i++){
            $random_keys=array_rand($stock,2);
            $playerStock[$i][] = $stock[$random_keys[0]]; 
            $playerStock[$i][] = $stock[$random_keys[1]];
            unset($stock[$random_keys[0]]);
            unset($stock[$random_keys[1]]);
        }
        

        $lastStock = array();
        foreach($stock as $k=>$v){
            if(!isset($lastStock[$v])){
                $lastStock[$v] = 0;
            }
            $lastStock[$v] += 1;
        }

        $room_key = "m_room_{$room_id}";//房间信息
        foreach($playerStock as $k=>$v){
            $userStock = $v;
            $userUid = $room_status['turn'][$k];
            $userInfo = unserialize(self::$rd -> hget($room_key,$userUid));
            $userInfo['stock'] = $userStock;
            $userInfo = self::$rd -> hset($room_key,$userUid,serialize($userInfo));
        }

        $room_status['last_stock'] = $lastStock;
        self::$rd -> set($room_status_key,serialize($room_status));
        $arr['playerStock'] = $playerStock;
        $arr['lastStock'] = $lastStock;
        return $arr;

   }
   // 购买股票
   public static function buyStock(){

   }
   // 获取股票当前价格
   public static function getStockPrice($stockId,$room_status=null){

        $priceConf = self::$gameConf['stock_list'];
        if($room_status){
            
            if(isset($room_status['stock_list'][$stockId])){
                $stock_list = $room_status['stock_list'][$stockId];
                $price = $priceConf[$stock_list];
            }else{
                $price = 0;
            }
        }

        return $price;
   }
   // 通过客户端ID 获取玩家UID
    public static function getUid($room_id,$client_id){

        $player_key = "m_room_{$room_id}_player"; //uid client_id 对应表
        $uid_to_clients = self::$rd -> hgetall($player_key);
        $uid = array_search($client_id, $uid_to_clients);
        if($uid){
            return $uid;
        }else{
            return false;
        }
    }

    // 通过UID 获取玩家客户端ID
    public static function getClientId($room_id,$uid){

        $player_key = "m_room_{$room_id}_player"; //uid client_id 对应表
        $clients_id = self::$rd -> hget($player_key,$uid);
        if($clients_id){
            return $clients_id;
        }else{
            return false;
        }
    }
    // 结算
    public static function balance($room_id){

       $room_status_key = "m_room_status_{$room_id}";//房间状态
       $ship_key = "m_ship_{$room_id}"; // 轮船
       $port_key = "m_port_{$room_id}"; // 港口&修理厂
      
       // $pirate_key = "m_pirate_{$room_id}"; // 海盗
       // $pilot_key = "m_pilot_{$room_id}"; // 领航员
       $insurance_key = "m_insurance_{$room_id}"; // 保险


        
       $room_status = unserialize(self::$rd->get($room_status_key));
       $turn = $room_status['turn'];
       $userList = array(); // 结算金钱
       foreach($turn as $k=>$v){
           $userList[$v] = 0;
       }
       $allShip = self::$rd -> hgetall($ship_key);
       $allShipInfo = array();
       foreach($allShip as $k=>$v){
           $allShipInfo[$k] = unserialize($v);
       }
       // $pilotInfo = unserialize(self::$rd -> get($pilot_key));
       // $pirateInfo = unserialize(self::$rd -> get($pirate_key));
       $allPortInfo = unserialize(self::$rd -> get($port_key));  
       $insuranceInfo = unserialize(self::$rd -> get($insurance_key));

       
       $portShip = 0;
       $repairShip = 0;
       $portGoods = array(); // 到港货物
       $shipInRoute = array();

       //  轮船结算
       if(!empty($allPortInfo)){
           foreach($allShipInfo as $shipId=>$shipInfo){

               $goodsId = $shipInfo['goods_id'];
               $shipGold = self::$gameConf['goods'][$goodsId]['gold'];
               if($shipInfo['status'] == 1){
                    $portGoods[] = $goodsId;
                    $portShip += 1;
                    if(isset($shipInfo['pirate'])){ // 被劫船只
                        $pirateUid = $shipInfo['pirate'];
                        $userList[$pirateUid] += $shipGold;
                    }else{
                        $workers = 0;
                        if(isset($shipInfo['cells'])){
                            $workers = count($shipInfo['cells']);
                        }
                        if($workers > 0){
                            $gold = $shipGold / $workers;
                            foreach ($shipInfo['cells'] as $workerUid) {
                                $userList[$workerUid] += $gold;
                            }
                        }
                    }
               }else{
                    $repairShip += 1;
                    if(isset($shipInfo['pirate'])){ // 被劫船只
                        $pirateUid = $shipInfo['pirate'];
                        $userList[$pirateUid] += $shipGold;
                    }

                    if($shipInfo['status'] == 0){
                        $shipInRoute[] = $shipId;
                    }
               }

           }
       }

       // 港口 修理厂 结算
       if(!empty($allPortInfo)){

            foreach($allPortInfo as $portId=>$portUid){
                // $portUid = $portInfo['uid'];
                $ships = self::$gameConf['port'][$portId]['ship'];
                $gold = self::$gameConf['port'][$portId]['reward'];
                if($portId <= 3){ // 港口
                    if($portShip >= $ships){
                        $userList[$portUid] += $gold;
                    }
                }else{ // 修理厂
                    if($repairShip >= $ships){
                        $userList[$portUid] += $gold;
                    }
                }
            }

       }
       

       // 保险赔偿
       if(!empty($insuranceInfo) && $repairShip > 0){
            
            $insuranceUid = $insuranceInfo[0];
            for($i=4;$i<=$repairShip+3;$i++){
                 if(!isset($allPortInfo[$i]) || $allPortInfo[$i]==$insuranceUid){
                    continue;
                 }
                 $gold = self::$gameConf['port'][$i]['reward'];
                 $userList[$insuranceUid] -= $gold;
            }


       }

       // 发钱
       foreach($userList as $uid=>$gold){
            if($gold>0){
                self::addMoney($uid,$room_id,abs($gold),1);
            }elseif($gold<0){
                self::addMoney($uid,$room_id,abs($gold),2);
            }
       }

       $end = 0;
       // 股价提升
       if(!empty($portGoods)){
            foreach($portGoods as $k=>$stockId){
                if(isset($room_status['stock_list'][$stockId])){
                    $room_status['stock_list'][$stockId] += 1;
                }else{
                    $room_status['stock_list'][$stockId] = 1;
                }
                
                if($room_status['stock_list'][$stockId] >= 4){
                    $end = 1;
                }
            }

            self::$rd ->set($room_status_key,serialize($room_status));

       }
       
       $balance['gold_list'] = $userList;
       $balance['stock_list'] = $room_status['stock_list'];
       $balance['ship_route'] = $shipInRoute;
       $balance['end'] = $end;

       // 整场结束
       if($end){
            $list = array();
            $stockList = $room_status['stock_list'];
            $stockPriceList = self::$gameConf['stock_list'];
            $room_key = "m_room_{$room_id}";//房间信息
            $allUserInfo = self::$rd -> hgetall($room_key);
            foreach($allUserInfo as $uid=>$v){
                $myStockPrice = 0;
                $userInfo = unserialize($v);
                $stock = $userInfo['stock'];
                foreach($stock as $stockId){
                    $stockLv = $stockList[$stockId];
                    $stockPrice = $stockPriceList[$stockLv];
                    $myStockPrice += $stockPrice;
                }
                $list[$uid]['gold'] = $userInfo['gold'];
                $list[$uid]['stock'] = $myStockPrice;
            }
            $balance['finally'] = $list;
       }

       return $balance;

    }


    /*

        array(
            'status'=>0, // 0 准备中  1 已开始
            'round'=>1 //回合数
            'turn'=>array($uid,$uid),//玩家顺序
            'now'=>0,//0~5 //当前回合玩家
            'step'=>0, 1 叫地主 2 买股票 3 选货物 
            'play'=>1, // 1 掷骰子
            'price_info'=>array('uid'=>$uid,'num'=>$price),
            'give_up'=>array($uid=>0,$uid=>0),
            'captain'=>$uid,
            'goods'=>array(1=>2,2=>3,3=>1),// 1~4种货物 位置-1=>颜色
            'ship'=>array(1=>0,2=>0,3=>0),// 轮船起始位置
            'stock_list'=>array(1=>0,2=>1,3=>1,4=>3),  // 股票价格
        )

    */


    // 初始化 进入下回合
    public static function initRound($room_id){

        $room_status_key = "m_room_status_{$room_id}";//房间状态
        $ship_key = "m_ship_{$room_id}"; // 轮船
        $port_key = "m_port_{$room_id}"; // 港口&修理厂
          
        $pirate_key = "m_pirate_{$room_id}"; // 海盗
        $pilot_key = "m_pilot_{$room_id}"; // 领航员
        $insurance_key = "m_insurance_{$room_id}"; // 保险

        self::$rd -> delete($ship_key);
        self::$rd -> delete($port_key);
        self::$rd -> delete($pirate_key);
        self::$rd -> delete($pilot_key);
        self::$rd -> delete($insurance_key);

        $room_status = unserialize(self::$rd->get($room_status_key));
        $room_status['give_up'] = array(); 
        $room_status['price'] = 0; 
        $room_status['step'] = 1;
        $room_status['play'] = 0;
        $room_status['round'] = 0;

        unset($room_status['captain']);
        unset($room_status['price_info']);
        unset($room_status['goods']);
        unset($room_status['ship']);
        unset($room_status['pirate']);
        unset($room_status['pilot']);

        self::$rd->set($room_status_key,serialize($room_status));
    }

    public static function getUserReadyInfo($room_id){

        $room_key = "m_room_{$room_id}";//房间信息 
        $playerList = self::$rd -> hgetall($room_key);
        $allReady = 1;
        foreach($playerList as $k=>$v){
            $playInfo = unserialize($v);
            if(isset($playInfo['ready']) && $playInfo['ready'] == 1){
                continue;
            }else{
                $allReady = 0;
                break;
            }
        }

        return $allReady;

    }

}
