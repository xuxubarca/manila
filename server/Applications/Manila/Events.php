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
use \GatewayWorker\Lib\Db;

class Events
{
   

    /**
     * 新建一个类的静态成员，用来保存数据库实例
     */
    public static $rd = null;
    /**
     * 进程启动后初始化数据库连接
     */
    public static function onWorkerStart($worker)
    {
        self::$rd = new Redis();
        self::$rd ->connect('127.0.0.1', 6379);
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
            return ;
        }
        // 根据类型执行不同的业务
        switch($message_data['type'])
        {
            //心跳
            case 'pong':
                return;
            //登录
            case 'login':
                // 判断是否有房间号
                if(!isset($message_data['room_id']))
                {
                    throw new \Exception("\$message_data['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
                }
                
                
                $room_id = $message_data['room_id'];
                $client_name = htmlspecialchars($message_data['client_name']);
                $_SESSION['room_id'] = $room_id;
                $_SESSION['client_name'] = $client_name;
              
                // 获取房间内所有用户列表 
                $clients_list = Gateway::getClientSessionsByGroup($room_id);
                foreach($clients_list as $tmp_client_id=>$item)
                {
                    $clients_list[$tmp_client_id] = $item['client_name'];
                }
                $clients_list[$client_id] = $client_name;
                
                $new_message = array(
                    'type'=>$message_data['type'], 
                    'client_id'=>$client_id, 
                    'client_name'=>htmlspecialchars($client_name), 
                    'time'=>date('Y-m-d H:i:s'),
                );
                $db = Db::instance('user');
                //$name = $db->select('name')->from('test')->where('id= 2')->single();
                $row = $db->select('*')->from('play')->where("id ='".$client_id."'")->query();
                if(count($row)==0){
                    $db->insert('play')->cols(array('id'=>$client_id, 'name'=>$client_name))->query();
                }
                $num_list = array();
                foreach($clients_list as $k=>$v){
                    $num = 1;
                    $checkNum = $db->select('num')->from('play')->where("id ='".$k."'")->single();
                    if($checkNum>0){
                         $num = $checkNum;
                    }
                    $num_list[$k] = $num;
                }
                $new_message['num_list'] = $num_list;

                Gateway::sendToGroup($room_id, json_encode($new_message));
                Gateway::joinGroup($client_id, $room_id);


                //$autoid = $db->select('autoid')->from('play')->where("id ='".$client_id."'")->single();
                //$new_message['autoid'] = $autoid;
                // 给当前用户发送用户列表 
                $new_message['client_list'] = $clients_list;
                
                Gateway::sendToCurrentClient(json_encode($new_message));
                return;

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
                    'step'=>0, 1 叫地主 2 选货物 
                    'price_info'=>array('uid'=>$uid,'num'=>$price),
                    'give_up'=>array($uid=>0,$uid=>0),
                )



         
            */

            // 马尼拉 登录
            case 'manilaLogin':
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
                if(isset($playerList[$uid])){ //玩家重新登录

                    


                }else{ //玩家首次进入

                     if($room_status['status']==1){ //比赛已开始
                        $goToNewRoom = true;
                     }else{
                        $saveInfo = true;
                     }
                    
                }

                if($goToNewRoom){ //去新房间

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
            //拉取当前场上数据
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
            // 马尼拉 掷骰子
            case 'manilaPoint':
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
                        'type'=>'manilaPoint',
                        'from_client_id'=>$client_id,
                        'from_client_name' =>$client_name,
                        'to_client_id'=>'all',
                        'point'=>$pointArr, 
                );
                return Gateway::sendToGroup($room_id ,json_encode($new_message));

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

                $now_price = 0;
                $caption = null;
                if(isset($room_status['price_info'])){
                    $now_price = $room_status['price_info']['num'];
                    $caption = $room_status['price_info']['uid'];
                }

                if($message_data['price']<=$now_price){
                    return;
                }else{
                    $player_key = "m_room_{$room_id}_player"; //uid client_id 对应表
                    $uid_to_clients = self::$rd -> hgetall($player_key);
                    $uid = array_search($client_id, $uid_to_clients);
                    if($uid==$caption){ //检查是不是已经产生队长
                        
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
                        //file_put_contents("/mylog.log",$now.'====='.json_encode($turn)."\r\n\r\n",FILE_APPEND);
                        $nextInfo = self::getNextPlayer($now,$turn,$give_up);


                        if($nextInfo){
                            $next = $nextInfo['next'];
                            $nextUid = $nextInfo['next_uid'];
                            $room_status['now'] = $next;
                            self::$rd->set($room_status_key,serialize($room_status));
                        }else{//成为队长



                        }

                    }

                }
                



                $new_message = array(
                    'type'=>'callCaptain',
                    'from_client_id'=>$client_id,
                    'to_client_id'=>'all',
                    'next_info'=>$nextInfo,
                    'price'=>$message_data['price'],
                );

                return Gateway::sendToGroup($room_id ,json_encode($new_message));
            //放弃地主
            case 'giveUp':
                if(!isset($_SESSION['room_id']))
                {
                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
                }
                $room_id = $_SESSION['room_id'];
                $client_name = $_SESSION['client_name'];

                $player_key = "m_room_{$room_id}_player"; //uid client_id 对应表
                $uid_to_clients = self::$rd -> hgetall($player_key);
                $uid = array_search($client_id, $uid_to_clients);

                $room_status_key = "m_room_status_{$room_id}";//房间状态
                $room_status = unserialize(self::$rd->get($room_status_key));
                $turn = $room_status['turn'];
                $now = $room_status['now'];

                $now_price = 0;
                $caption = null;
                if(isset($room_status['price_info'])){
                    $now_price = $room_status['price_info']['num'];
                    $caption = $room_status['price_info']['uid'];
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


                if(count($turn) - count($give_up) == 1){ //产生队长
                     $new_message['caption']['uid'] = $caption;   
                     $new_message['caption']['num'] = $now_price;  
                     //扣钱
                     

                }else{
                    $nextInfo = self::getNextPlayer($now,$turn,$give_up);   
                    $next = $nextInfo['next'];
                    $room_status['now'] = $next;
                    self::$rd->set($room_status_key,serialize($room_status));

                    $new_message['next_info'] = $nextInfo;
                    $new_message['price'] = $now_price;
                }


               
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
                //file_put_contents("/mylog.log",json_encode($new_message)."----------\r\n",FILE_APPEND);
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
       $db = Db::instance('user');
       $db->delete('play')->where("id ='".$client_id."'")->query();
       // 从房间的客户端列表中删除
       if(isset($_SESSION['room_id']))
       {
           $room_id = $_SESSION['room_id'];
           $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
           Gateway::sendToGroup($room_id, json_encode($new_message));
       }
   }

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
  
}