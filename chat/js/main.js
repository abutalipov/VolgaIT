var print_msg_me=0;
var msg_tpl='<div class="d-flex [pos] mb-4"><div class="msg_cotainer_send">[msg]<span class="msg_time_send">[name], [time]</span></div></div>'
var me_uid=null
if(ws = new WebSocket('ws://localhost:5655')){
ws.onopen = function() {
	console.log('открытие сокета');
	if(me_uid==null){
		data={type:'start'}
		send(data);
	}
}
ws.onerror = function(e) {console.log(e)}; 
ws.onclose = function() {console.log('закрытие сокета')}; 
ws.onmessage = function(e){
	console.log(e.data)
	var entry = JSON.parse(e.data);
	console.log(entry)
	switch(entry.type){
		case "ping":
		break;
		case "online_now":
			$("#online_now").html(entry.msg)
		break;
		case "uid_set":
		me_uid=entry.msg
		break;
		case "msg":
		show_message(entry);
		break;
		case "history":
		history_load(entry.data);
		break;
	}
}; 
function disconn(){
	ws.close();
}
function send(e){
	e=JSON.stringify(e);
	ws.send(e);
	console.log(e)
}
}
function history_load(e){
	var len=e.length
	for(var i=0;i<len;i++){
		arr={ uid:"0" ,name:e[i]['name'],time:e[i]['time'],msg:e[i]['msg']}
		show_message(arr)
	}
}
function show_message(entry){
	out=msg_tpl.replace(/\[(\S+)]/g,function(e,e1){
		switch(e1){
			case 'pos':
				if(entry['from']==me_uid)
					out='justify-content-start'
				else
					out='justify-content-end'
			break;

			default:
			out=entry[e1]
		}
		return out;
	})
	$('#containerMessages').append(out);
	var height = $('.chatfull').prop('scrollHeight');
	$('.chatfull').scrollTop(height);
} 

function send_input(){
	var message = $('#chat_input').text().trim(); 
	$('#chat_input').empty();
	if(message.length > 0){
		var data={type:'msg',from:me_uid, msg:message,name:me_name}
		send(data);
		$('#chat_input').focus();
	}
}