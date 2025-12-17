<?php
ini_set('session.cookie_httponly', true);
session_start();
session_regenerate_id(true);
require_once '../config.php';
require_once '../function.php';
require_once '../botapi.php';
try {
    $allowed_ips = select("setting","*",null,null,"select");
} catch (Throwable $e) {
    $allowed_ips = ['iplogin' => ''];
}

$user_ip = $_SERVER['REMOTE_ADDR'];
$admin_ids = [];
try {
    $admin_ids = select("admin", "id_admin",null,null,"FETCH_COLUMN");
} catch (Throwable $e) {}
$check_ip = isset($allowed_ips['iplogin']) ? ($allowed_ips['iplogin'] == $user_ip) : true;
$texterrr = "";
$_SESSION["user"] = null;
if (isset($_POST['login'])) {
    $username = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');
    $password = htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8');
    $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
    $query->bindParam("username", $username, PDO::PARAM_STR);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);

    if ( !$result ) {
        $texterrr = 'نام کاربری یا رمزعبور وارد شده اشتباه است!';
    } else {
                       
        if ( $password == $result["password"]) {
            foreach ($admin_ids as $admin) {
                $texts = "کاربر با نام کاربری $username وارد پنل تحت وب شد";
        sendmessage($admin, $texts, null, 'html');
            }
            $_SESSION["user"] = $result["username"];
            header('Location: index.php');
        } else {
            $texterrr =  'رمز صحیح نمی باشد';
        }
    }
}
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود به پنل مدیریت</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
/* ================= BACKGROUND ================= */
body.login-body{
  margin:0;
  min-height:100vh;
  font-family:system-ui,sans-serif;
  background:linear-gradient(-45deg,#0f2027,#203a43,#2c5364,#141726);
  background-size:400% 400%;
  animation:bg 18s ease infinite;
  overflow:hidden;
}
@keyframes bg{
  0%{background-position:0% 50%}
  50%{background-position:100% 50%}
  100%{background-position:0% 50%}
}

/* ================= PARTICLES ================= */
.particles span{
  position:absolute;
  width:6px;height:6px;
  background:rgba(255,255,255,.25);
  border-radius:50%;
  animation:float 20s linear infinite;
}
@keyframes float{
  from{transform:translateY(110vh) scale(.6);opacity:0}
  20%{opacity:1}
  to{transform:translateY(-10vh) scale(1);opacity:0}
}

/* ================= CARD ================= */
.form-signin{
  position:relative;
  z-index:10;
  max-width:380px;
  margin:110px auto 20px;
  background:rgba(20,23,34,.88);
  backdrop-filter:blur(18px);
  border-radius:26px;
  padding:34px 30px 28px;
  box-shadow:0 40px 120px rgba(0,0,0,.7);
  border:1px solid rgba(255,255,255,.08);
  overflow:hidden;
}

/* --- Reactive light layer --- */
.form-signin::before{
  content:"";
  position:absolute;
  inset:-1px;
  border-radius:inherit;
  background:
    radial-gradient(
      220px circle at var(--x,50%) var(--y,50%),
      rgba(124,136,255,.45),
      transparent 60%
    );
  opacity:.9;
  pointer-events:none;
  transition:opacity .2s;
}

/* ================= LOGO ================= */
.logo{
  display:flex;
  justify-content:center;
  margin-bottom:18px;
}
.logo svg{width:86px;height:86px}
.logo circle{
  fill:none;
  stroke:#7c88ff;
  stroke-width:4;
  stroke-dasharray:260;
  stroke-dashoffset:260;
  animation:draw 2s ease forwards;
}
.logo text{
  fill:#fff;
  font-size:26px;
  font-weight:800;
  opacity:0;
  animation:fadeIn .8s ease 1.6s forwards;
}
@keyframes draw{to{stroke-dashoffset:0}}
@keyframes fadeIn{to{opacity:1}}

/* ================= INPUTS ================= */
.form-control{
  width:100%;
  background:#0f121a;
  border:1px solid #2a2f44;
  border-radius:14px;
  height:50px;
  padding:0 14px;
  color:#fff;
  margin-bottom:14px;
  font-size:15px;
}
.form-control:focus{
  outline:none;
  border-color:#7c88ff;
  box-shadow:0 0 0 3px rgba(124,136,255,.25);
}

/* ================= BUTTON ================= */
.btn-login{
  width:100%;
  height:54px;
  border:none;
  border-radius:18px;
  background:linear-gradient(135deg,#7c88ff,#9aa4ff);
  color:#fff;
  font-size:16px;
  font-weight:700;
  cursor:pointer;
  box-shadow:0 16px 40px rgba(124,136,255,.55);
  transition:.3s;
  position:relative;
  overflow:hidden;
}

/* Light sweep */
.btn-login::after{
  content:"";
  position:absolute;
  top:0;left:-120%;
  width:120%;
  height:100%;
  background:linear-gradient(120deg,transparent,rgba(255,255,255,.45),transparent);
  transition:.6s;
}
.btn-login:hover::after{left:120%}
.btn-login:hover{transform:translateY(-3px)}

/* ================= SIGNATURE ================= */
.signature{
  text-align:center;
  margin-top:30px;
  color:#cfd3ff;
}
.signature strong{
  display:block;
  font-size:22px;
  color:#fff;
  margin-bottom:6px;
}
.signature a{
  color:#00f2ff;
  font-weight:800;
  text-decoration:none;
}

/* ================= MOBILE ================= */
@media (max-width:600px){
  .form-signin{
    margin:70px 14px 20px;
    padding:28px 22px;
    border-radius:22px;
  }
  .logo svg{width:70px;height:70px}
  .btn-login{height:56px;font-size:17px}
  .signature strong{font-size:18px}
}
</style>
</head>

<body class="login-body">

<!-- PARTICLES -->
<div class="particles">
  <span style="left:10%"></span><span style="left:20%"></span>
  <span style="left:30%"></span><span style="left:40%"></span>
  <span style="left:50%"></span><span style="left:60%"></span>
  <span style="left:70%"></span><span style="left:80%"></span>
  <span style="left:90%"></span>
</div>

<!-- LOGIN -->
<form class="form-signin" id="loginCard" method="post">
  <div class="logo">
    <svg viewBox="0 0 100 100">
      <circle cx="50" cy="50" r="42"/>
      <text x="50" y="60" text-anchor="middle">H</text>
    </svg>
  </div>

  <input type="text" name="username" class="form-control" placeholder="نام کاربری">
  <input type="password" name="password" class="form-control" placeholder="کلمه عبور">

  <button type="submit" name="login" class="btn-login" id="loginBtn">
    ورود به پنل
  </button>
</form>

<!-- SIGNATURE -->
<div class="signature">
  <strong>✨ طراحی شده توسط Hosein ✨</strong>
  🚀 Telegram :
  <a href="https://t.me/killhosein" target="_blank">@killhosein</a>
</div>

<script>
/* ========= REACTIVE LIGHT (MOUSE) ========= */
const card = document.getElementById('loginCard');
document.addEventListener('mousemove', e=>{
  const r = card.getBoundingClientRect();
  const x = ((e.clientX - r.left) / r.width) * 100;
  const y = ((e.clientY - r.top) / r.height) * 100;
  card.style.setProperty('--x', x + '%');
  card.style.setProperty('--y', y + '%');
});

/* ========= HAPTIC FEEDBACK (MOBILE) ========= */
document.getElementById('loginBtn').addEventListener('click', ()=>{
  if (navigator.vibrate) {
    navigator.vibrate([20, 40, 20]); // لرزش نرم و حرفه‌ای
  }
});
</script>

</body>
</html>
