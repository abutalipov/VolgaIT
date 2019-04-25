<?php
$name=$_POST['name'];
?><html>
<head>
<link href="/css/bootstrap.css" rel="stylesheet" >
<link href="/css/main.css" rel="stylesheet" >
<script>var me_name='<?php echo $name; ?>'</script>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
<script src="/js/main.js"></script>
</head>
<body>
<div class="container">
<div class="row" id="informer"><div class="col-md-6">Онлайн:</div><div class="col-md-6" id="online_now"></div></div>
<div id="chatbox">
	<div id="dialog_box">
		<div class="chatfull">
			<div class="card-body msg_card_body" id="containerMessages">
				
			</div>
		</div> 
		<div class="input-group send_msg">
		  <div class="input-group-prepend">
			<span class="input-group-text"><button type="button" class="btn btn-success" onclick="send_input()"> Отправить </button></span>
		  </div>
		   <div tabindex="0" contenteditable="true" id="chat_input" class="form-control"> </div>
		</div>
	</div>
</div>
</div>
</body>
</html>