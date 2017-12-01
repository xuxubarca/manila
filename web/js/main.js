/* 初始化地图区域 */
function initLocation(){

	/* 棋盘坐标： 20~33 40~53 60~73   */
	var j = 0;
	for(var i = 20; i < 34; i++){
		document.querySelectorAll('#box > div')[i].innerHTML = '<p>'+ j +'</p>';
		document.querySelectorAll('#box > div')[i].style.border="2px solid #2894FF";
		j++;
	}
	var j = 0;
	for(var i = 40; i < 54; i++){
		document.querySelectorAll('#box > div')[i].innerHTML = '<p>'+ j +'</p>';
		document.querySelectorAll('#box > div')[i].style.border="2px solid #2894FF";
		j++;
	}
	var j = 0;
	for(var i = 60; i < 74; i++){
		document.querySelectorAll('#box > div')[i].innerHTML = '<p>'+ j +'</p>';
		document.querySelectorAll('#box > div')[i].style.border="2px solid #2894FF";
		j++;
	}
	
	/* 港口船位 17 18 19*/
	for(var i = 17; i < 20; i++){
		document.querySelectorAll('#box > div')[i].style.border="2px solid #F75000";
	}
	/* 修理船位 87 88 89*/
	for(var i = 87; i < 90; i++){
		document.querySelectorAll('#box > div')[i].style.border="2px solid #F75000";
	}
	/* 海盗坐标：6 16*/
	document.querySelectorAll('#box > div')[6].style.border="2px solid #000000";
	document.querySelectorAll('#box > div')[16].style.border="2px solid #000000";
	document.querySelectorAll('#box > div')[6].innerHTML = '<p> 小海盗（-5） </p>';
	document.querySelectorAll('#box > div')[16].innerHTML = '<p> 大海盗（-5） </p>';
	
	/* 领航员坐标：3 4 */
	document.querySelectorAll('#box > div')[3].style.border="2px solid #9AFF02";
	document.querySelectorAll('#box > div')[4].style.border="2px solid #9AFF02";
	document.querySelectorAll('#box > div')[3].innerHTML = '<p> 小领航员（-2） </p>';
	document.querySelectorAll('#box > div')[4].innerHTML = '<p> 大领航员（-5） </p>';
	/* 保险公司坐标：92 */
	document.querySelectorAll('#box > div')[92].style.border="2px solid #FFDC35";
	document.querySelectorAll('#box > div')[92].innerHTML = '<p> 保险（+10） </p>';
	

	//document.querySelectorAll('#box > div')[3].innerHTML = '<p> 6 </p>';
	//document.querySelectorAll('#box > div')[4].innerHTML = '<p> 8</p>';
	
	document.querySelectorAll('#box > div')[7].innerHTML = '<p> 6 </p>';
	document.querySelectorAll('#box > div')[8].innerHTML = '<p> 8</p>';
	document.querySelectorAll('#box > div')[9].innerHTML = '<p> 15 </p>';

	/* 港口 */
	document.querySelectorAll('#box > div')[17].innerHTML = '<p> A (-4) </p>';
	document.querySelectorAll('#box > div')[18].innerHTML = '<p> B (-3)</p>';
	document.querySelectorAll('#box > div')[19].innerHTML = '<p> C (-2) </p>';
	
	/* 修理厂 */
	document.querySelectorAll('#box > div')[89].innerHTML = '<p> A (-4) </p>';
	document.querySelectorAll('#box > div')[88].innerHTML = '<p> B (-3)</p>';
	document.querySelectorAll('#box > div')[87].innerHTML = '<p> C (-2) </p>';
	
	document.querySelectorAll('#box > div')[99].innerHTML = '<p> 6 </p>';
	document.querySelectorAll('#box > div')[98].innerHTML = '<p> 8</p>';
	document.querySelectorAll('#box > div')[97].innerHTML = '<p> 15 </p>';
				
	
	for(var i=0;i<=3;i++){
		document.querySelectorAll('#list > div')[i].innerHTML = '<p> 30 </p>';
		document.querySelectorAll('#list > div')[i+4].innerHTML = '<p> 20 </p>';
		document.querySelectorAll('#list > div')[i+8].innerHTML = '<p> 10 </p>';
		document.querySelectorAll('#list > div')[i+12].innerHTML = '<p> 5 </p>';
		document.querySelectorAll('#list > div')[i+16].innerHTML = '<p> 0 </p>';
	}
	//for(var i = 0; i < 100; i++){
		//document.querySelectorAll('#box > div')[i].innerHTML = '<p>'+ i +'</p>';
	//}
	
	document.querySelectorAll('#list > div')[16].style.background = 'blue';
	document.querySelectorAll('#list > div')[17].style.background = 'yellow';
	document.querySelectorAll('#list > div')[18].style.background = 'green';
	document.querySelectorAll('#list > div')[19].style.background = 'red';
	document.querySelectorAll('#list > div')[20].style.background = 'blue';
	document.querySelectorAll('#list > div')[21].style.background = 'yellow';
	document.querySelectorAll('#list > div')[22].style.background = 'green';
	document.querySelectorAll('#list > div')[23].style.background = 'red';

}

/* 初始化地图数据 */
function initData(){
	
}

//登录弹窗
function showPrompt() {
	PostbirdAlertBox.prompt({
		'title': '输入账号',
		'okBtn': '提交',
		onConfirm: function (data) {
			console.log("输入框内容是：" + data);
			var reg = /^[\da-zA-Z]+$/;
			if(!reg.test(data)){
				alert('不支持中文');
				location.reload();
			}else{
				uid = data;
				manila.init();
				initLocation();
			}
			
		},
		onCancel: function (data) {
			console.log("输入框内容是：" + data);
			window.opener=null;
			window.close();
		},
	});
}

// 开始游戏
function start(){
	ws.send('{"type":"start"}');
}

//叫地主
function callCaptain(nowPrice) {
	PostbirdAlertBox.prompt({
		'title': '输入金额',
		'okBtn': '提交',
		onConfirm: function (data) {
			console.log("输入框内容是：" + data);
			data = parseInt(data);
			nowPrice = parseInt(nowPrice);
			var reg = /^[1-9]*[1-9][0-9]*$/;
			if(!reg.test(data)){
				alert('必须是整数');
				callCaptain(nowPrice);
			}else{
				if(data>nowPrice){
					
					// 报价
					var price_data = '{"type":"price","price":"'+ data +'"}';
					ws.send(price_data);
					
				}else{
					alert('金额小于当前出价');
					callCaptain(nowPrice);
				}
				
			}
			
		},
		onCancel: function (data) { //放弃
			var give_up_data = '{"type":"giveUp"}';
			ws.send(give_up_data);

		},
	});
}
// 显示当前叫地主最高价
function getCaptainMessage(price,captain){

	var str = '当前最高报价：'+ captain + ' ' + price;
	$('#price').html(str);
}
// 当前回合玩家
function nowTurn(turn){

	// $('#player div').each(function(i){
	// 	$(this).css({'background-color' : "#FFFFFF"});	
	// })

	for(var i=0;i<=5;i++){
		$('#user'+i).css("background-color","#FFFFFF");
	}

	$('#user'+turn).css("background-color","#FF8C00");
}
// 买股票面板
function initBuyStockViews(){


}

// 更新玩家列表
function getPlayerList(client_list){
	
	for(var u in client_list){
		var turn = client_list[u]['turn'];
		$("#user"+turn).css('display','block');
		$('#username'+turn).html('<h2>'+ u +'</h2>');
		$('#userpanel'+turn).html('<h2>'+ client_list[u]['gold'] +'</h2>');	
		$('#username'+turn).css({'background-color' : client_list[u]['color']});	
		$('#userpanel'+turn).css({'background-color' : client_list[u]['color']});			
	}
	
}
// 显示股票购买面板
function startBuyStock(){
	$('#buystock').css('display','block');
}

function endBuyStock(){
	$('#buystock').css('display','none');
}
// 开始选货物
function startChooseGoods(isCaptain){

	if(isCaptain){
		document.querySelectorAll('#list > div')[20].setAttribute("onclick","chooseGoods(1)");
		document.querySelectorAll('#list > div')[21].setAttribute("onclick","chooseGoods(2)");
		document.querySelectorAll('#list > div')[22].setAttribute("onclick","chooseGoods(3)");
		document.querySelectorAll('#list > div')[23].setAttribute("onclick","chooseGoods(4)");
	}
	
	document.querySelectorAll('#list > div')[20].style.borderColor="#FF8000";
	document.querySelectorAll('#list > div')[21].style.borderColor="#FF8000";
	document.querySelectorAll('#list > div')[22].style.borderColor="#FF8000";
	document.querySelectorAll('#list > div')[23].style.borderColor="#FF8000";
}
// 结束选货物
function endChooseGoods(){
	document.querySelectorAll('#list > div')[20].removeAttribute("onclick");
	document.querySelectorAll('#list > div')[21].removeAttribute("onclick");
	document.querySelectorAll('#list > div')[22].removeAttribute("onclick");
	document.querySelectorAll('#list > div')[23].removeAttribute("onclick");

	document.querySelectorAll('#list > div')[20].style.borderColor="#000000";
	document.querySelectorAll('#list > div')[21].style.borderColor="#000000";
	document.querySelectorAll('#list > div')[22].style.borderColor="#000000";
	document.querySelectorAll('#list > div')[23].style.borderColor="#000000";
}
// 选择货物
function chooseGoods(goods){
	ws.send('{"type":"chooseGoods","goods":"'+ goods +'"}');
}
// 被选
function choosed(goodsInfo){
	var n = parseInt(goodsInfo['id']) + 19;
	document.querySelectorAll('#list > div')[n].removeAttribute("onclick");
	document.querySelectorAll('#list > div')[n].style.borderColor="#ADADAD";
	//document.querySelectorAll('#list > div')[n].innerHTML = '<h2> √ </h2>';
	// var color = document.querySelectorAll('#list > div')[n].style.backgroundColor;
	initShip(goodsInfo);
}
// 初始化货船
function initShip(goodsInfo){
	var num = $(".ship").length;
	if(num >=3){
		return;
	}
	num = num + 1;
	var shipId = 'ship' + num;
	var pointId = 'p' + num;
	var str = '';
	str += '<div class="ship" id="'+ shipId +'">';
	//console.log(goodsInfo['cells']);
	//return;

	var n = Object.keys(goodsInfo['cells']).length;

	if(goodsInfo['id']==3){
		for (var j = 0; j < 5; j++) {
			if(n == 0){
				str += '<div class="shipCell2" id="c'+ n +'" style="left:' + j*48 + 'px;top:' + 0 + 'px"><p>'+ goodsInfo['gold'] +'</p></div>';
			}else{
				str += '<div class="shipCell2" id="c'+ n +'" style="left:' + j*48 + 'px;top:' + 0 + 'px"><p>'+ goodsInfo['cells'][n] +'</p></div>';
			}
			n--;
		}
	}else{
		for (var j = 0; j < 4; j++) {
			if(n == 0){
				str += '<div class="shipCell" id="c'+ n +'" style="left:' + j*60 + 'px;top:' + 0 + 'px"><p>'+ goodsInfo['gold'] +'</p></div>';
			}else{
				str += '<div class="shipCell" id="c'+ n +'" style="left:' + j*60 + 'px;top:' + 0 + 'px"><p>'+ goodsInfo['cells'][n] +'</p></div>';
			}
			n--;
		}
	}
	str += '</div>';

	$('#box').append(str);
	$('#'+shipId).css("background-color",goodsInfo['color']);
	$('#'+pointId).css("background-color",goodsInfo['color']);
}
// 初始化轮船起点设置
function initShipOutset(){
	j = 0;
	for(var i = 20; i < 26; i++){
		document.querySelectorAll('#box > div')[i].setAttribute("onclick","setShipOutset(1,"+ j +")");
		document.querySelectorAll('#box > div')[i].style.background = '#9D9D9D';
		j++;
	}
	j = 0;
	for(var i = 40; i < 46; i++){
		document.querySelectorAll('#box > div')[i].setAttribute("onclick","setShipOutset(2,"+ j +")");
		document.querySelectorAll('#box > div')[i].style.background = '#9D9D9D';
		j++;
	}
	j = 0;
	for(var i = 60; i < 66; i++){
		document.querySelectorAll('#box > div')[i].setAttribute("onclick","setShipOutset(3,"+ j +")");
		document.querySelectorAll('#box > div')[i].style.background = '#9D9D9D';
		j++;
	}
	$('#confirm').css('display','block');
}
// 结束轮船起点设置
function endShipOutset(){
	for(var i = 20; i < 26; i++){
		document.querySelectorAll('#box > div')[i].removeAttribute("onclick");
		document.querySelectorAll('#box > div')[i].style.background = '#FFFFFF';
	}
	for(var i = 40; i < 46; i++){
		document.querySelectorAll('#box > div')[i].removeAttribute("onclick");
		document.querySelectorAll('#box > div')[i].style.background = '#FFFFFF';
	}
	for(var i = 60; i < 66; i++){
		document.querySelectorAll('#box > div')[i].removeAttribute("onclick");
		document.querySelectorAll('#box > div')[i].style.background = '#FFFFFF';
	}
	$('#confirm').css('display','none');
}


// 选择轮船起点
function setShipOutset(shipId,step){
	ws.send('{"type":"setOutset","shipId":"'+ shipId +'","step":"'+ step +'"}');
}
// 确认轮船起点
function confirmShipOutset(){
	ws.send('{"type":"confirmOutset"}');
}

// 移动轮船 (按格子)
function shipMove(shipId,step){
	step = parseInt(step);
	if(step > 13){
		if(portShips == 0){
			step = 16;
		}else if(portShips == 1){
			step = 18;
		}else if(portShips == 2){
			step = 20;
		}
		var n = step * 60;
		var p = n + 'px';
		$("#ship"+shipId).animate({left:p},'slow');
		shipMoveIntoPort(shipId); //进港
	}else{
		var n = step * 60;
		var p = n + 'px';
		$("#ship"+shipId).animate({left:p},'slow');
	}
	shipStep[shipId] = step;
}

// 移动轮船 (按点数)
function shipMovePoint(shipId,point){
	var nowStep = parseInt(shipStep[shipId]);
	if(nowStep > 13){ // 已经进港
		return;
	}
	step = parseInt(point) + nowStep;
	shipMove(shipId,step);
}

// 轮船进港
function shipMoveIntoPort(shipId){
	$('#ship'+shipId).animate(
		{borderSpacing:-90},
		{step: 
			function(now,fx) {
	     		$(this).css('-webkit-transform','rotate('+now+'deg)');      
				$(this).css('-moz-transform','rotate('+now+'deg)');      
				$(this).css('-ms-transform','rotate('+now+'deg)');
	    		$(this).css('-o-transform','rotate('+now+'deg)');      
				$(this).css('transform','rotate('+now+'deg)');
			},
   			duration:'slow' 
		},
		'linear');

	if(shipId == 2){
		var move = '-120px';
	}else if(shipId == 3){
		var move = '-240px';
	}
	$("#ship"+shipId).animate({top:move});
	portShips = portShips + 1;
}

// 轮船进修理厂
function shipMoveIntoRepair(shipId){

	if(repairShips == 0){
		step = 20;
	}else if(repairShips == 1){
		step = 18;
	}else if(repairShips == 2){
		step = 16;
	}
	var n = step * 60;
	var p = n + 'px';
	$("#ship"+shipId).animate({left:p},'slow');

	$('#ship'+shipId).animate(
		{borderSpacing:90},
		{step: 
			function(now,fx) {
	     		$(this).css('-webkit-transform','rotate('+now+'deg)');      
				$(this).css('-moz-transform','rotate('+now+'deg)');      
				$(this).css('-ms-transform','rotate('+now+'deg)');
	    		$(this).css('-o-transform','rotate('+now+'deg)');      
				$(this).css('transform','rotate('+now+'deg)');
			},
   			duration:'slow' 
		},
		'linear');

	if(shipId == 1){
		var move = '240px';
	}else if(shipId == 2){
		var move = '120px';
	}
	$("#ship"+shipId).animate({top:move});
	repairShips = repairShips + 1;
}

// 更新玩家金币
function moneyRefresh(turn,num){
	str = '<h2>'+ num +'</h2>'
	$('#userpanel'+turn).html(str);
}

// 更新玩家股票
function myStock(myStock){
	var stockArr = new Array();
	for(var i in myStock){
		if(stockArr[myStock[i]] > 0){
			stockArr[myStock[i]] += 1;
		}else{
			stockArr[myStock[i]] = 1;
		}
	}

	for(var i=1;i<=4;i++){
		var n = 19 + i;
		if(stockArr[i]>0){
			document.querySelectorAll('#list > div')[n].innerHTML = '<p>'+ stockArr[i] +'</p>';
		}else{
			document.querySelectorAll('#list > div')[n].innerHTML = '<p>'+ 0 +'</p>';
		}
	}
}
// 初始化游戏
function initGame(){
	/* 登船 */
	document.getElementById('ship1').setAttribute("onclick","boarding(1)");
	document.getElementById('ship2').setAttribute("onclick","boarding(2)");
	document.getElementById('ship3').setAttribute("onclick","boarding(3)");

	/* 保险 */
	document.querySelectorAll('#box > div')[92].setAttribute("onclick","insurance()");

	/* 港口 */
	document.querySelectorAll('#box > div')[17].setAttribute("onclick","port(1)");
	document.querySelectorAll('#box > div')[18].setAttribute("onclick","port(2)");
	document.querySelectorAll('#box > div')[19].setAttribute("onclick","port(3)");
	
	/* 修理厂 */
	document.querySelectorAll('#box > div')[89].setAttribute("onclick","port(4)");
	document.querySelectorAll('#box > div')[88].setAttribute("onclick","port(5)");
	document.querySelectorAll('#box > div')[87].setAttribute("onclick","port(6)");

	/* 海盗 */
	document.querySelectorAll('#box > div')[6].setAttribute("onclick","pirate()");
	document.querySelectorAll('#box > div')[16].setAttribute("onclick","pirate()");

	/* 领航员 */
	document.querySelectorAll('#box > div')[3].setAttribute("onclick","pilot(1)");
	document.querySelectorAll('#box > div')[4].setAttribute("onclick","pilot(2)");

}
// 
function endGameChoose(){
	/* 登船 */
	document.getElementById('ship1').removeAttribute("onclick");
	document.getElementById('ship2').removeAttribute("onclick");
	document.getElementById('ship3').removeAttribute("onclick");

	/* 保险 */
	document.querySelectorAll('#box > div')[92].removeAttribute("onclick");

	/* 港口 */
	document.querySelectorAll('#box > div')[17].removeAttribute("onclick");
	document.querySelectorAll('#box > div')[18].removeAttribute("onclick");
	document.querySelectorAll('#box > div')[19].removeAttribute("onclick");
	/* 修理厂 */
	document.querySelectorAll('#box > div')[89].removeAttribute("onclick");
	document.querySelectorAll('#box > div')[88].removeAttribute("onclick");
	document.querySelectorAll('#box > div')[87].removeAttribute("onclick");

	/* 海盗 */
	document.querySelectorAll('#box > div')[6].removeAttribute("onclick");
	document.querySelectorAll('#box > div')[16].removeAttribute("onclick");

	/* 领航员 */
	document.querySelectorAll('#box > div')[3].removeAttribute("onclick");
	document.querySelectorAll('#box > div')[4].removeAttribute("onclick");
}

// 工人上船
function boarding(shipId){
	ws.send('{"type":"setWorker","action":"boarding","shipId":"'+ shipId +'"}');
}

// 押港口或修理厂
function port(portId){
	ws.send('{"type":"setWorker","action":"port","portId":"'+ portId +'"}');
}

// 领航员
function pilot(pilotId){
	ws.send('{"type":"setWorker","action":"pilot","pilotId":"'+ pilotId +'"}');
}

// 当海盗
function pirate(){
	ws.send('{"type":"setWorker","action":"pirate"}');
}

// 保险公司
function insurance(){
	ws.send('{"type":"setWorker","action":"insurance"}');
}

// 队长买股票
function buystock(stockId){
	ws.send('{"type":"buystock","stockId":"'+ stockId +'"}');
}

// 登船展示
function showBoarding(data){
	var cell = data['cell'];
	var color = data['user_info']['color'];
	var next = data['next'];
	var shipId = data['ship_id'];
	var play = data['play'];
	var goodsId = data['goods_id'];

	if(goodsId == 3){
		var n = 4 - cell;
	}else{
		var n = 3 - cell;
	}
	
	document.querySelectorAll('#ship'+ shipId +' > div')[n].style.background = color;
	if(captain==1 && play==1){
		startPlayPoint();
	}
	if(data['pilot']['turn']){
		if(my_turn == data['pilot']['turn']){
			startPilotChoose(data['pilot']['id']); 
		}
		nowTurn(data['pilot']['turn']);
	}else{
		nowTurn(next);
	}
}


// 海盗登船展示
function showPirateBoarding(data){
	
	var color = data['user_info']['color'];
	var action = data['action'];
	var next = data['next'];
	var shipId = data['ship_id'];
	var pirate = data['pirate'];
	var goodsId = data['goods_id'];
	nowTurn(next);
	console.log(action);
	console.log(next);
	console.log(color);
	console.log(shipId);
	console.log(goodsId);
	//console.log(action);
	if(action == 'boarding'){
		var cell = data['cell'];
		if(goodsId == 3){
			var n = 4 - cell;
		}else{
			var n = 3 - cell;
		}
		document.querySelectorAll('#ship'+ shipId +' > div')[n].style.background = color;
		// if(captain==1 && pirate==0){
		// 	startPlayPoint();
		// }
	}else if(action == 'robbery'){
		var cell = 1;
		if(goodsId == 3){
			var n = 4 - cell;
			var m = 4;
		}else{
			var n = 3 - cell;
			var m = 3;
		}

		var shipColor = document.querySelectorAll('#ship'+ shipId +' > div')[m].style.backgroundColor;
		
		for(var i=0;i<n;i++){
			document.querySelectorAll('#ship'+ shipId +' > div')[i].style.background = shipColor;
		}

		document.querySelectorAll('#ship'+ shipId +' > div')[n].style.background = color;
	}
	
	

}

// 港口&修理厂展示
function showPort(data){
	var portId = data['port_id'];
	var color = data['user_info']['color'];
	var next = data['next'];
	var play = data['play'];
	
	if(portId<=3){
		var n = 16 + parseInt(portId);
	}else{
		var n = 93 - parseInt(portId);
	}
	
	document.querySelectorAll('#box > div')[n].style.background = color;

	if(captain==1 && play==1){
		startPlayPoint();
	}
	if(data['pilot']['turn']){
		if(my_turn == data['pilot']['turn']){
			startPilotChoose(data['pilot']['id']); 
		}
		nowTurn(data['pilot']['turn']);
	}else{
		nowTurn(next);
	}
}

// 领航员
function showPilot(data){
	var pilotId = data['pilot_id'];
	var color = data['user_info']['color'];
	var next = data['next'];
	var play = data['play'];
	
	var n = 2 + parseInt(pilotId);
	document.querySelectorAll('#box > div')[n].style.background = color;

	if(captain==1 && play==1){
		startPlayPoint();
	}
	if(data['pilot']['turn']){
		if(my_turn == data['pilot']['turn']){
			startPilotChoose(data['pilot']['id']); 
		} 
		nowTurn(data['pilot']['turn']);
	}else{
		nowTurn(next);
	}
}

// 海盗
function showPirate(data){
	var pirateId = data['pirate_id'];
	var color = data['user_info']['color'];
	var next = data['next'];
	var play = data['play'];
	if(pirateId == 1){
		var n = 16;
	}else if(pirateId == 2){
		var n = 6;
	}
	document.querySelectorAll('#box > div')[n].style.background = color;

	if(captain==1 && play==1){
		startPlayPoint();
	}
	if(data['pilot']['turn']){
		if(my_turn == data['pilot']['turn']){
			startPilotChoose(data['pilot']['id']); 
		}
		nowTurn(data['pilot']['turn']);
	}else{
		nowTurn(next);
	}
}
// 保险
function showInsurance(data){
	var color = data['user_info']['color'];
	var next = data['next'];
	var play = data['play'];
	
	document.querySelectorAll('#box > div')[92].style.background = color;

	if(captain==1 && play==1){
		startPlayPoint();
	}
	if(data['pilot']['turn']){
		if(my_turn == data['pilot']['turn']){
			startPilotChoose(data['pilot']['id']); 
		}
		nowTurn(data['pilot']['turn']);
	}else{
		nowTurn(next);
	}
}
// 开始掷骰子
function startPlayPoint(){
	document.getElementById('point').setAttribute("onclick","getServerPoint()");
	document.getElementById('dice1').style.borderColor = '#FF8000';
	document.getElementById('dice2').style.borderColor = '#FF8000';
	document.getElementById('dice3').style.borderColor = '#FF8000';
}
// 结束掷骰子
function endPlayPoint(){
	document.getElementById('point').removeAttribute("onclick");
	document.getElementById('dice1').style.borderColor = '#FFFFFF';
	document.getElementById('dice2').style.borderColor = '#FFFFFF';
	document.getElementById('dice3').style.borderColor = '#FFFFFF';
}
// 获得点数
function getServerPoint(){
	ws.send('{"type":"playPoint","num":"3"}');
}

// 海盗登船
function pirateBoarding(flag,shipId){
	if(flag == 1){
		ws.send('{"type":"pirateBoarding","ship_id":"'+ shipId +'"}');
	}else if(flag == 2){
		ws.send('{"type":"pirateBoarding","action":"give_up"}');
	}
}

function startPirateBoarding(){

	document.getElementById('ship1').setAttribute("onclick","pirateBoarding(1,1)");
	document.getElementById('ship2').setAttribute("onclick","pirateBoarding(1,2)");
	document.getElementById('ship3').setAttribute("onclick","pirateBoarding(1,3)");
	$("#pirateGiveUp").css('display','block');
}

function endPirateBoarding(){

	document.getElementById('ship1').removeAttribute("onclick");
	document.getElementById('ship2').removeAttribute("onclick");
	document.getElementById('ship3').removeAttribute("onclick");
	$("#pirateGiveUp").css('display','none');
}
// 海盗选择轮船是否进港 
function pirateChoose(action){
	if(nowShip == 0){
		return;
	}
	ws.send('{"type":"pirateChoose","ship_id":"'+ nowShip +'","action":"'+ action +'"}');
}

function startPirateChoose(){
	$("#gotoPort").css('display','block');
	$("#gotoRepair").css('display','block');
}

function endPirateChoose(){
	$("#gotoPort").css('display','none');
	$("#gotoRepair").css('display','none');
}
// 提示消息
function showMsg(msg){

	document.getElementById('msg').innerHTML = '<p>'+ msg +'</p>';
}

function startPilotChoose(pilotId){

	var n = 0;
	var m = 0;
	var j = 0;

	if(pilotId == 1){
		var step = 1;
	}else if(pilotId == 2){
		var step = 2;
	}

	for(var i=1;i<=3;i++){

		if(shipStep[i] > 13){
			continue;
		}

		n = 20 * i;
		if(shipStep[i] > 0){
			n = n + shipStep[i];
		}
		if(shipStep[i] - step >=0){
			j = n - step;
		}else{
			j = 0;
		}
		if(shipStep[i] + step <=14){
			m = n + step;
		}else{
			m = 14;
		}
		for(j=j;j<=m;j++){
			var x = j - n;
			document.querySelectorAll('#box > div')[j].setAttribute("onclick","pilotChoose("+ i +","+ x +","+ pilotId +")");
			document.querySelectorAll('#box > div')[j].style.background = '#9D9D9D';
		}
	}

}

function endPilotChoose(play){

	for(var i = 20; i < 34; i++){
		document.querySelectorAll('#box > div')[i].removeAttribute("onclick");
		document.querySelectorAll('#box > div')[i].style.background = '#FFFFFF';
	}
	for(var i = 40; i < 54; i++){
		document.querySelectorAll('#box > div')[i].removeAttribute("onclick");
		document.querySelectorAll('#box > div')[i].style.background = '#FFFFFF';
	}
	for(var i = 60; i < 74; i++){
		document.querySelectorAll('#box > div')[i].removeAttribute("onclick");
		document.querySelectorAll('#box > div')[i].style.background = '#FFFFFF';
	}

	if(captain==1 && play==1){
		startPlayPoint();
	}

}

function pilotChoose(shipId,step,pilotId){
	ws.send('{"type":"pilotChoose","ship_id":"'+ shipId +'","step":"'+ step +'","pilot_id":"'+ pilotId +'"}');
}

// 股票面板

function stockPriceChange(stockList){

	// var stockList = new Array();
	// stockList[1] = 2;
	// stockList[2] = 1;
	// stockList[3] = 0;
	// stockList[4] = 4;

	for(var i=1;i<=4;i++){
		var color = document.querySelectorAll('#list > div')[i+15].style.background;
		if(stockList[i] == 1){
			document.querySelectorAll('#list > div')[i+11].style.background = color;
		}else if(stockList[i] == 2){
			document.querySelectorAll('#list > div')[i+11].style.background = color;
			document.querySelectorAll('#list > div')[i+7].style.background = color;
		}else if(stockList[i] == 3){
			document.querySelectorAll('#list > div')[i+11].style.background = color;
			document.querySelectorAll('#list > div')[i+7].style.background = color;
			document.querySelectorAll('#list > div')[i+3].style.background = color;
		}else if(stockList[i] == 4){
			document.querySelectorAll('#list > div')[i+11].style.background = color;
			document.querySelectorAll('#list > div')[i+7].style.background = color;
			document.querySelectorAll('#list > div')[i+3].style.background = color;
			document.querySelectorAll('#list > div')[i-1].style.background = color;
		}
	}


}


function test(){
	//document.querySelectorAll('#list > div')[20].onclick = click();
	//document.querySelectorAll('#list > div')[20].setAttribute("onclick","tt()");
	ws.send('{"type":"test"}');
}
function tt(){
	alert('!!!!!!!!!');
	//document.querySelectorAll('#list > div')[20].removeAttribute("onclick");
	//var num = $(".ship").length;
	//var color = document.querySelectorAll('#list > div')[20].style.backgroundColor;
	//alert(color);
}
