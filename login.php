<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta charset="UTF-8">
    <title>Yönetici Girişi</title>
     <style>
      
    body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background-color: #0f172a;
    height: 100vh;
    overflow: hidden;
}

#particles-js {
    position: fixed;
    width: 100%;
    height: 100%;
    z-index: 0;
}

.login-container {
    position: relative;
    z-index: 1;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-box {
    background: rgba(255, 255, 255, 0.05);
    padding: 40px;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    box-shadow: 0 0 30px rgba(255,255,255,0.05);
    width: 100%;
    max-width: 400px;
    color: white;
    text-align: center;
    box-sizing: border-box;
}

.login-box img {
    max-width: 140px;
    margin-bottom: 25px;
}

.login-box h2 {
    margin-bottom: 20px;
    font-weight: 600;
    font-size: 22px;
}

.login-box input {
    width: 100%;
    padding: 14px;
    margin-bottom: 15px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    box-sizing: border-box;
}

.login-box button {
    width: 100%;
    padding: 14px;
    background-color: #3b82f6;
    color: white;
    font-weight: bold;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    box-sizing: border-box;
}

.login-box button:hover {
    background-color: #2563eb;
}

.error-message {
    color: #f87171;
    font-size: 14px;
    margin-bottom: 10px;
}

@media screen and (max-width: 480px) {
    .login-container {
        align-items: flex-start;
        padding-top:100px;
    }

    .login-box {
        padding: 30px 20px;
        max-width: 90%;
    }

    .login-box img {
        max-width: 120px;
        margin-bottom: 20px;
    }

    .login-box h2 {
        font-size: 18px;
    }

    .login-box input,
    .login-box button {
        font-size: 15px;
        padding: 12px;
    }
}


    </style>
</head>
<body>

<div id="particles-js"></div>

<div class="login-container">
    <div class="login-box">
        <img src="https://rideandgoo.com/wp-content/uploads/2024/09/RideAndGoo-MainLogo-Mobil.webp" alt="Logo">
        <h2>🔐 Yönetici Girişi</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <p class="error-message"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        <?php endif; ?>
        <form action="login_process.php" method="POST">
            <input type="text" name="username" placeholder="Kullanıcı Adı" required>
            <input type="password" name="password" placeholder="Şifre" required>
            <button type="submit">Giriş Yap</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
particlesJS("particles-js", {
  "particles": {
    "number": { "value": 60, "density": { "enable": true, "value_area": 800 } },
    "color": { "value": "#60a5fa" },
    "shape": { "type": "circle" },
    "opacity": { "value": 0.4 },
    "size": { "value": 4 },
    "line_linked": { "enable": true, "distance": 150, "color": "#60a5fa", "opacity": 0.2, "width": 1 },
    "move": { "enable": true, "speed": 2 }
  },
  "interactivity": {
    "detect_on": "canvas",
    "events": {
      "onhover": { "enable": true, "mode": "repulse" },
      "onclick": { "enable": true, "mode": "push" }
    },
    "modes": {
      "repulse": { "distance": 100 },
      "push": { "particles_nb": 4 }
    }
  },
  "retina_detect": true
});
</script>
</body>
</html>