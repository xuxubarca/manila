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
	document.querySelectorAll('#box > div')[3].innerHTML = '<p> 大领航员（-5） </p>';
	document.querySelectorAll('#box > div')[4].innerHTML = '<p> 小领航员（-2） </p>';
	/* 保险公司坐标：92 */
	document.querySelectorAll('#box > div')[92].style.border="2px solid #FFDC35";
	document.querySelectorAll('#box > div')[92].innerHTML = '<p> 保险（+10） </p>';
	
	document.querySelectorAll('#box > div')[3].innerHTML = '<p> 6 </p>';
	document.querySelectorAll('#box > div')[4].innerHTML = '<p> 8</p>';
	
	document.querySelectorAll('#box > div')[7].innerHTML = '<p> 6 </p>';
	document.querySelectorAll('#box > div')[8].innerHTML = '<p> 8</p>';
	document.querySelectorAll('#box > div')[9].innerHTML = '<p> 15 </p>';
	
	document.querySelectorAll('#box > div')[17].innerHTML = '<p> A (-4) </p>';
	document.querySelectorAll('#box > div')[18].innerHTML = '<p> B (-3)</p>';
	document.querySelectorAll('#box > div')[19].innerHTML = '<p> C (-2) </p>';
	
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

	$('#player div').each(function(i){
		$(this).css({'background-color' : "#FFFFFF"});	
	})

	$('#user'+turn).css("background-color","#FF8C00");
}


// 更新玩家列表
function getPlayerList(client_list){
	
	for(var u in client_list){
		var turn = client_list[u]['turn'];
		$("#user"+turn).css('display','block');
		$('#username'+turn).html('<h2>'+ u +'</h2>');
		$('#userpanel'+turn).html('<h2>'+ client_list[u]['gold'] +'</h2>');					
	}
	
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

	document.querySelectorAll('#list > div')[20].style.borderColor="0";
	document.querySelectorAll('#list > div')[21].style.borderColor="0";
	document.querySelectorAll('#list > div')[22].style.borderColor="0";
	document.querySelectorAll('#list > div')[23].style.borderColor="0";
}
// 选择货物
function chooseGoods(goods){
	ws.send('{"type":"chooseGoods","goods":"'+ goods +'"}');
}
// 被选
function choosed(goodsInfo){
	var n = parseInt(goodsInfo['id']) + 19;
	document.querySelectorAll('#list > div')[n].removeAttribute("onclick");
	document.querySelectorAll('#list > div')[n].innerHTML = '<h2> √ </h2>';
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
}
// 开始选择轮船起点
function startShipOutset(){

	for(var i = 20; i < 30; i++){
		document.querySelectorAll('#box > div')[i].setAttribute("onclick","setShipOutset()");
	}

	for(var i = 40; i < 50; i++){
		document.querySelectorAll('#box > div')[i].setAttribute("onclick","setShipOutset()");
	}

	for(var i = 60; i < 70; i++){
		document.querySelectorAll('#box > div')[i].setAttribute("onclick","setShipOutset()");
	}
}

// 更新玩家金币
function moneyRefresh(turn,num){
	str = '<h2>'+ num +'</h2>'
	$('#userpanel'+turn).html(str);
}

function start(){
	ws.send('{"type":"start"}');
}

function test(){

	//document.querySelectorAll('#list > div')[20].onclick = click();
	document.querySelectorAll('#list > div')[20].setAttribute("onclick","tt()");
}
function tt(){
	//alert('!!!!!!!!!');
	//document.querySelectorAll('#list > div')[20].removeAttribute("onclick");
	//var num = $(".ship").length;
	var color = document.querySelectorAll('#list > div')[20].style.backgroundColor;
	alert(color);
}
