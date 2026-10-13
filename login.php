<?php session_start(); ?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Sistema LGPD - Login</title>

    <link rel="stylesheet"
          href="../assets/css/estilo.css">

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap"
          rel="stylesheet">

</head>

<body class="login-page">

    <div class="login-container">

        <form method="POST"
              action="processa_login.php"
              class="login-card">

            <div class="login-logo">
                <img src="favicon.png" alt="Logo" width="48" height="48">
            </div>

            <h1>Sistema LGPD</h1>

            <p class="login-desc">
                Gerencie dados pessoais com segurança,
                organização e conformidade com a LGPD.
            </p>

            <?php if (isset($_SESSION['erro_login'])): ?>

                <div class="erro-login">
                    <?= $_SESSION['erro_login']; ?>
                </div>

            <?php unset($_SESSION['erro_login']); endif; ?>

            <input type="email"
                   name="email"
                   placeholder="E-mail"
                   autocomplete="email"
                   required>

            <input type="password"
                   name="senha"
                   placeholder="Senha"
                   autocomplete="current-password"
                   required>

            <button type="submit">
                Entrar
            </button>

        </form>
        
    </div>

</body>

</html>