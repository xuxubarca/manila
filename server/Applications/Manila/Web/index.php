<html><head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>掷骰子</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet">
  <link href="/css/style.css" rel="stylesheet">
	
  <script type="text/javascript" src="/js/swfobject.js"></script>
  <script type="text/javascript" src="/js/web_socket.js"></script>
  <script type="text/javascript" src="/js/jquery.min.js"></script>

  <script type="text/javascript">
    if (typeof console == "undefined") {    this.console = { log: function (msg) {  } };}
    // 如果浏览器不支持websocket，会使用这个flash自动模拟websocket协议，此过程对开发者透明
    WEB_SOCKET_SWF_LOCATION = "/swf/WebSocketMain.swf";
    // 开启flash的websocket debug
    WEB_SOCKET_DEBUG = true;
    var ws, name, client_list,point_list={};
    var uid;
    // 连接服务端
    function connect() {
       // 创建websocket
       ws = new WebSocket("ws://"+document.domain+":7272");
       // 当socket连接打开时，输入用户名
       ws.onopen = onopen;
       // 当有消息时根据消息类型显示不同信息
       ws.onmessage = onmessage; 
       ws.onclose = function() {
    	  console.log("连接关闭，定时重连");
          connect();
       };
       ws.onerror = function() {
     	  console.log("出现错误");
       };
    }

    // 连接建立时发送登录信息
    function onopen()
    {
        if(!name)
        {
            show_prompt();
        }
        // 登录
        var login_data = '{"type":"login","client_name":"'+name.replace(/"/g, '\\"')+'","room_id":"<?php echo isset($_GET['room_id']) ? $_GET['room_id'] : 1?>"}';
        console.log("websocket握手成功，发送登录数据:"+login_data);
        ws.send(login_data);
    }

    // 服务端发来消息时
    function onmessage(e)
    {
        console.log(e.data);
        var data = eval("("+e.data+")");
        switch(data['type']){
            // 服务端ping客户端
            case 'ping':
                ws.send('{"type":"pong"}');
                break;;
            // 登录 更新用户列表
            case 'login':
                //{"type":"login","client_id":xxx,"client_name":"xxx","client_list":"[...]","time":"xxx"}
                say(data['client_id'], data['client_name'],  data['client_name']+' 加入了聊天室', data['time']);
                if(data['client_list'])
                {
                    client_list = data['client_list'];
                    uid = data['client_id'];
                }
                else
                {
                    client_list[data['client_id']] = data['client_name']; 
                }
                if(data['num_list']){
                    point_list = data['num_list'];
                }
                flush_client_list(data);
                console.log(data['client_name']+"登录成功");
                break;
            // 发言
            case 'say':
                //{"type":"say","from_client_id":xxx,"to_client_id":"all/client_id","content":"xxx","time":"xxx"}
                say(data['from_client_id'], data['from_client_name'], data['content'], data['time']);
                break;
            // 掷骰子
            case 'rand':
                    if(uid==data['from_client_id']){
                        play("#dice",data['num']);
                    }
                    play("#d_"+ data['from_client_id'],data['num']);

                    break;

            // 用户退出 更新用户列表
            case 'logout':
                //{"type":"logout","client_id":xxx,"time":"xxx"}
                say(data['from_client_id'], data['from_client_name'], data['from_client_name']+' 退出了', data['time']);
                delete client_list[data['from_client_id']];
                flush_client_list(data);
        }
    }

    // 输入姓名
    function show_prompt(){  
        name = prompt('输入你的名字：', '');
        if(!name || name=='null'){  
            name = '游客';
        }
    }  

    // 提交对话
    function onSubmit() {

      ws.send('{"type":"rand"}');

    }
    // 播放动画
    function play(divId,num){
          //var divId = "#"+id;
          var dice = $(divId);
          //$(".wrap").append("<div id='dice_mask'></div>");//加遮罩
          dice.attr("class","dice");//清除上次动画后的点数
          dice.css('cursor','default');
          var num = num;
          dice.animate({left: '+2px'}, 100,function(){
            dice.addClass("dice_t");
          }).delay(200).animate({top:'-2px'},100,function(){
            dice.removeClass("dice_t").addClass("dice_s");
          }).delay(200).animate({opacity: 'show'},600,function(){
            dice.removeClass("dice_s").addClass("dice_e");
          }).delay(100).animate({left:'-2px',top:'2px'},100,function(){
            dice.removeClass("dice_e").addClass("dice_"+num);
            $("#result").html("您掷得点数是<span>"+num+"</span>");
            dice.css('cursor','pointer');
            $("#dice_mask").remove();//移除遮罩
          });
    }

    // 刷新用户列表框
    function flush_client_list(){
    	var userlist_window = $("#userlist");
    	var client_list_slelect = $("#client_list");
      var point_window = $(".point");
    	userlist_window.empty();
      point_window.empty();
    	client_list_slelect.empty();
    	userlist_window.append('<h4>在线玩家</h4><ul>');
    	client_list_slelect.append('<option value="all" id="cli_all">所有人</option>');
    	for(var p in client_list){
            userlist_window.append('<li id="'+p+'">'+client_list[p]+'</li>');
            client_list_slelect.append('<option value="'+p+'">'+client_list[p]+'</option>');
            var dclass = "dice dice_1";
           if(point_list[p]){
              dclass = "dice dice_"+point_list[p];
           }
            if(uid == p){
                $(".point").append('<div class="thumbnail" style="float:left;margin-left:20px"><div class="caption" id="pointlistme"><div>' + client_list[p] +'</div><div id="d_'+ p +'" class="'+ dclass +'" style="margin-top:70px"></div>');
            }else{
                $(".point").append('<div class="thumbnail" style="float:left;margin-left:20px"><div class="caption" id="pointlist"><div>' + client_list[p] +'</div><div id="d_'+ p +'" class="'+ dclass +'" style="margin-top:70px"></div>');
            }
      }
    	$("#client_list").val(select_client_id);
      
    	userlist_window.append('</ul>');
    }

    // 发言
    function say(from_client_id, from_client_name, content, time){
    	$("#dialog").append('<div class="speech_item"><img src="http://lorempixel.com/38/38/?'+from_client_id+'" class="user_icon" /> '+from_client_name+' <br> '+time+'<div style="clear:both;"></div><p class="triangle-isosceles top">'+content+'</p> </div>');
    }

    $(function(){
    	select_client_id = 'all';
	    $("#client_list").change(function(){
	         select_client_id = $("#client_list option:selected").attr("value");
	    });
    });
  </script>


  <style type="text/css">
    .wrap{width:90px; height:90px; margin:60px auto 30px auto; position:relative}
    .dice{width:90px; height:90px; background:url(img/dice.png) no-repeat; cursor:pointer;}
    .dice_1{background-position:-5px -4px}
    .dice_2{background-position:-5px -107px}
    .dice_3{background-position:-5px -212px}
    .dice_4{background-position:-5px -317px}
    .dice_5{background-position:-5px -427px}
    .dice_6{background-position:-5px -535px}
    .dice_t{background-position:-5px -651px}
    .dice_s{background-position:-5px -763px}
    .dice_e{background-position:-5px -876px}
    p#result{text-align:center; font-size:16px}
    p#result span{font-weight:bold; color:#f30; margin:6px}
    #dice_mask{width:90px; height:90px; background:#fff; opacity:0; position:absolute; top:0; left:0; z-index:999}
</style>

<script type="text/javascript">
  $(function(){
    var dice = $("#dice");
    dice.click(function(){
      onSubmit();
    });

  });
</script>
</head>
<body onload="connect();">

      <div class="wrap">
        <div id="dice" class="dice dice_1"></div>
      </div>

      <div class="container">
        <div class="row clearfix">
            <div class="col-md-3 column">
               <div class="thumbnail">
                     <div class="caption" id="userlist"></div>
                </div>
                 
            </div>

            <div class="point" style="float:left;margin-left:20px">

            </div>


        </div>
    </div>
    <div class="container">
    </div>
</body>
</html>
