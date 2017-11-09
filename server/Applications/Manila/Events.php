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

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose 
 */
use \GatewayWorker\Lib\Gateway;
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
            ) 
            
            uid=>client_id : m_room_{$room_id}_player Hash
           
            房间状态： m_room_status_{$room_id} Strng

            array(
                'status'=>0, // 0 准备中  1 已开始
                'round'=>1 //回合数
                'turn'=>array($uid,$uid),//玩家顺序
                'now'=>0,//0~5 //当前回合玩家
                'step'=>0, 1 叫地主 2 选货物 3 设置起点 
                'price_info'=>array('uid'=>$uid,'num'=>$price),
                'give_up'=>array($uid=>0,$uid=>0),
                'captain'=>$uid,
                'goods'=>array(1=>2,2=>3,3=>1),// 1~4种货物 位置-1=>颜色
                'ship'=>array(1=>0,2=>0,3=>0),// 轮船起始位置
            )

            轮船：  m_ship_{$room_id} Hash

            shipId =>array(
                'id'=>$shipId,
                'color'=>$colorId,
                'step'=>0, //0~13
                'cell'=>array(1=>$uid,2=>$uid...),
                'status'=>0,
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
                    'time'=>date('Y-m-d H:i:s'),
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

                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
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
                    }
                }


                $new_message = array(
                    'type'=>$message_data['type'],
                    'client_id'=>$client_id, 
                    'clients_list'=>$list,
                    'time'=>date('Y-m-d H:i:s'),

                );

                Gateway::sendToGroup($room_id, json_encode($new_message));
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;

            //开始
            case 'start':
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
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
                
                $nextInfo = array(
                    'next'=>0,
                    //'next_uid'=>$uid,
                );

                $new_message = array(
                        'type'=>'callCaptain',
                        'from_client_id'=>$client_id,
                        'to_client_id'=>'all',
                        'turn'=>0,
                        'now_price'=>0,
                        'next_info'=>$nextInfo,
                        'price'=>0,
                );

                Gateway::sendToGroup($room_id ,json_encode($new_message));
                return;
            // 叫地主报价
            case 'price':
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
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
                        'from_client_id'=>$client_id,
                        'to_client_id'=>'all',
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
                            'from_client_id'=>$client_id,
                            'to_client_id'=>'all',
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
                    'from_client_id'=>$client_id,
                    'to_client_id'=>'all',
                    'next_info'=>$nextInfo,
                    'price'=>$message_data['price'],
                    'highest'=>$uid,
                );

                return Gateway::sendToGroup($room_id ,json_encode($new_message));
            //放弃队长
            case 'giveUp':
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
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
                    'from_client_id'=>$client_id,
                    'to_client_id'=>'all',
                    'give_up'=>1,
                    
                );

                if(count($turn) - count($room_status['give_up']) == 1){ //产生队长
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
            // 选择货物
            case 'chooseGoods':
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
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
                if($step != 2){
                    return;
                }

                $goodsArr = array();
                if(isset($room_status['goods'])){
                    $goodsArr = $room_status['goods'];
                }

                if(count($goodsArr) >=3){
                    return;
                }
                if(array_key_exists($goodsId,$goodsArr)){
                    return;
                }

                $room_status['goods'][] = $goodsId;

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
            // 设置轮船起点
            case 'setOutset':
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
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

            /*
                shipId =>array(
                    'id'=>$shipId,
                    'color'=>$colorId,
                    'step'=>0, //0~13
                    'cell'=>array(1=>$uid,2=>$uid...),
                    'status'=>0,
                )

            */

            // 确认轮船起点
            case 'confirmOutset':
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
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

                $ship_step = $room_status['ship'];
                $total = 0;
                foreach ($ship_step as $k => $v) {
                    if($v > 5){
                        return;
                    }
                    $total += $v;
                }
                if($total != 9){
                    return;
                }

                // 初始化轮船信息
                $ship_key = "m_ship_{$room_id}"; //轮船
                self::$rd->delete($ship_key); 
                $goodsArr = $room_status['goods'];
                //$goodsConf = self::gameConf['goods'];
                for($i=1;$i<=3;$i++){
                    if(isset($ship_step[$i])){
                        $step = $ship_step[$i];
                    }else{
                        $step = 0;
                    }
                    $goodsId = $goodsArr[$i-1];
                    $shipInfo = array(
                        'id' => $i,
                        'goods_id'=>$goodsId,
                        'step' => $step, 
                        'status'=>0,
                        'cells'=>array(),
                    );
                    self::$rd->hset($ship_key,$i,serialize($shipInfo));
                }

                $new_message = array(
                    'type'=>'setOutset', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'ship_step'=>$ship_step,
                ); 

                return Gateway::sendToGroup($room_id ,json_encode($new_message));

            // 掷骰子
            case 'playPoint':
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

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
                
                $new_message = array(
                    'type'=>'playPoint',
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'point'=>$pointArr, 
                );
                return Gateway::sendToGroup($room_id ,json_encode($new_message));

            //发言
            case 'say':

                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
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
            'from_client_id'=>$client_id,
            'to_client_id'=>'all',
            'uid'=>$uid,
            'gold'=>$gold,
            'turn'=>$turn,
        );
        return Gateway::sendToGroup($room_id ,json_encode($new_message));
   }
   //通过客户端ID 获取玩家UID
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
  
}
