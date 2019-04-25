<html>
<head>
<meta charset="utf-8">
<link href="/css/bootstrap.css" rel="stylesheet" >
<link href="/css/main.css" rel="stylesheet" >
</head>
<body>
<div class="container">
        <div class="row justify-content-center align-items-center" style="height:100vh">
            <div class="col-4">
                <div class="card">
                    <div class="card-body">
                        <form action="/chat-access.php" method="POST" autocomplete="off">
                            <div class="form-group">
                                <input type="text" class="form-control" placeholder="Введите ваше имя"  name="name">
                            </div>
                            <input type="submit" id="sendlogin" value="Войти" class="btn btn-primary"/>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>