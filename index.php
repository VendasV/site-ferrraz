<?php
session_start();

// Configurações do banco SQLite
$db_file = __DIR__ . '/produtos.db';
$db = new PDO('sqlite:' . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Cria a tabela caso não exista
$db->exec("CREATE TABLE IF NOT EXISTS produtos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    preco TEXT NOT NULL,
    imagem TEXT NOT NULL
)");

// Login
$loginError = '';
if(isset($_POST['login'])){
    $user = $_POST['usuario'] ?? '';
    $pass = $_POST['senha'] ?? '';
    if($user === 'imports' && $pass === 'imports123'){
        $_SESSION['admin'] = true;
        header("Location: index.php");
        exit;
    } else {
        $loginError = 'Usuário ou senha incorretos!';
    }
}

// Logout
if(isset($_GET['logout'])){
    session_destroy();
    header("Location: index.php");
    exit;
}

// Adicionar produto
if(isset($_POST['addProduto']) && isset($_SESSION['admin'])){
    $nome = $_POST['nome'] ?? '';
    $preco = $_POST['preco'] ?? '';
    $imagem = '';

    if(isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0){
        $uploadDir = __DIR__ . '/produtos/';
        if(!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $imagem = basename($_FILES['imagem']['name']);
        move_uploaded_file($_FILES['imagem']['tmp_name'], $uploadDir . $imagem);
    }

    if($nome && $preco && $imagem){
        $stmt = $db->prepare("INSERT INTO produtos (nome, preco, imagem) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $preco, $imagem]);
        header("Location: index.php");
        exit;
    }
}

// Editar produto
if(isset($_POST['editProduto']) && isset($_SESSION['admin'])){
    $id = (int)$_POST['id'];
    $nome = $_POST['nome'] ?? '';
    $preco = $_POST['preco'] ?? '';
    
    $stmt = $db->prepare("SELECT imagem FROM produtos WHERE id=?");
    $stmt->execute([$id]);
    $imagem = $stmt->fetchColumn();

    if(isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0){
        $uploadDir = __DIR__ . '/produtos/';
        if($imagem && file_exists($uploadDir.$imagem)){
            unlink($uploadDir.$imagem);
        }
        $imagem = basename($_FILES['imagem']['name']);
        move_uploaded_file($_FILES['imagem']['tmp_name'], $uploadDir . $imagem);
    }

    $stmt = $db->prepare("UPDATE produtos SET nome=?, preco=?, imagem=? WHERE id=?");
    $stmt->execute([$nome, $preco, $imagem, $id]);
    header("Location: index.php");
    exit;
}

// Remover produto
if(isset($_GET['remove']) && isset($_SESSION['admin'])){
    $id = (int)$_GET['remove'];
    $stmt = $db->prepare("SELECT imagem FROM produtos WHERE id=?");
    $stmt->execute([$id]);
    $img = $stmt->fetchColumn();
    if($img && file_exists(__DIR__.'/produtos/'.$img)){
        unlink(__DIR__.'/produtos/'.$img);
    }
    $stmt = $db->prepare("DELETE FROM produtos WHERE id=?");
    $stmt->execute([$id]);
    header("Location: index.php");
    exit;
}

// Pega produtos
$produtos = $db->query("SELECT * FROM produtos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Links | LM Imports</title>
<link rel="icon" type="image/png" href="favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#ffffff;--text:#0b1220;--input-bg:#f4f6fb;--border:#d1d5db}
[data-theme="dark"]{--bg:#0b1220;--text:#e6eef8;--input-bg:#142029;--border:#374151}
body{margin:0;font-family:"Poppins",system-ui,-apple-system,"Segoe UI",Roboto,Arial;background:linear-gradient(180deg,#0b1220 0%,#101826 100%);color:var(--text);display:flex;justify-content:center;align-items:flex-start;min-height:100vh;transition:background .25s,color .25s;padding-top:20px;}
.container{background:var(--input-bg);border-radius:20px;width:90%;max-width:420px;padding:32px;box-shadow:0 8px 25px rgba(0,0,0,.15);text-align:center;box-sizing:border-box;animation:fadeIn .7s ease;margin-bottom:20px;}
@keyframes fadeIn{from{opacity:0;transform:translateY(15px);}to{opacity:1;transform:translateY(0);}}
img.perfil{width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--border);margin-bottom:12px;transition:transform .3s,box-shadow .3s;}
img.perfil:hover{transform:scale(1.05);box-shadow:0 0 15px rgba(255,215,0,.3);}
h2{margin:0;font-size:1.6rem;}
p{font-size:1rem;opacity:.85;margin-bottom:20px;line-height:1.4;}
.link{width:100%;padding:14px;margin-top:14px;font-size:1rem;font-weight:600;border:none;border-radius:12px;cursor:pointer;color:white;transition:transform .2s,filter .3s,box-shadow .3s;display:flex;justify-content:center;align-items:center;gap:10px;}
.link:hover{transform:translateY(-2px);filter:brightness(1.1);box-shadow:0 4px 12px rgba(0,0,0,.2);}
.link img{width:22px;height:22px;border-radius:4px;}
.catalogo{background:#0078d7;}
.facebook{background:#1877F2;}
.whatsapp{background:#25D366;}
.instagram{background:linear-gradient(45deg,#feda75,#fa7e1e,#d62976,#962fbf,#4f5bd5);}
#toggle-theme{position:fixed;top:12px;right:12px;background:transparent;border:1px solid var(--border);border-radius:8px;padding:6px 10px;cursor:pointer;font-size:1.2rem;}
#btn-admin{position:fixed;top:12px;left:12px;width:35px;height:35px;background:#0078d7;border:2px solid #000;border-radius:4px;cursor:pointer;z-index:999;text-align:center;line-height:35px;font-weight:bold;color:white;}
.catalogo-container{display:none;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-top:20px;}
.produto{background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:12px;text-align:center;box-shadow:0 3px 8px rgba(0,0,0,.08);transition:transform .2s,box-shadow .3s;}
.produto:hover{transform:scale(1.02);box-shadow:0 4px 14px rgba(0,0,0,.15);}
.produto img{width:100%;height:160px;object-fit:cover;border-radius:10px;}
.produto h3{margin:10px 0 4px;font-size:1.05rem;}
.produto p{margin:0;font-size:.95rem;}
.btn-voltar{background:#444;color:white;border:none;border-radius:10px;padding:10px 16px;cursor:pointer;font-weight:600;transition:background .3s;}
.btn-voltar:hover{background:#666;}
.admin-panel{display:none;background:var(--input-bg);padding:20px;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,.2);margin-bottom:20px;}
.admin-panel h3{margin-top:0;}
.admin-panel form{display:flex;flex-direction:column;gap:10px;}
.admin-panel input[type=text],.admin-panel input[type=file],.admin-panel input[type=password]{padding:8px;border-radius:8px;border:1px solid var(--border);}
.admin-panel button{padding:10px;border-radius:8px;border:none;background:#0078d7;color:white;cursor:pointer;font-weight:600;transition:background .3s;}
.admin-panel button:hover{background:#005bb5;}
@media(max-width:480px){.container{padding:22px;max-width:360px;}img.perfil{width:85px;height:85px;}.catalogo-container{grid-template-columns:1fr;}}
</style>
</head>
<body>

<!-- Botão admin pequeno -->
<div id="btn-admin" onclick="toggleLogin()">⚙️</div>

<div class="container" id="principal">
  <img class="perfil" src="perfil.jpg" alt="Foto de perfil">
  <h2>LM Imports</h2>
  <p>💻 Acesse Agora Nosso Catálogo Para Ver Nossos Produtos Disponiveis E Nossas Redes Sociais Para Realizar O Seu Atendimento Com Um De Nossos Atendentes!</p>

  <button class="link catalogo" onclick="abrirCatalogo()">🛍️ Ver Catálogo</button>
  <button class="link facebook" onclick="abrirLink('facebook')"><img src="produtos/facebook.png" alt="Facebook">Ver Facebook</button>
  <button class="link whatsapp" onclick="abrirChat()"><img src="produtos/whatsapp.png" alt="WhatsApp">Falar no WhatsApp</button>
  <button class="link instagram" onclick="abrirLink('instagram')"><img src="produtos/instagram.png" alt="Instagram">Ver Instagram</button>

  <small>© 2025 - FZ Produções | Todos os direitos reservados</small>
</div>

<!-- Login admin -->
<div class="container admin-panel" id="loginPanel">
  <?php if(!isset($_SESSION['admin'])): ?>
    <h3>Login de Administrador</h3>
    <?php if($loginError) echo "<p style='color:red;'>$loginError</p>"; ?>
    <form method="POST">
      <input type="text" name="usuario" placeholder="Usuário" required>
      <input type="password" name="senha" placeholder="Senha" required>
      <button type="submit" name="login">Entrar</button>
    </form>
  <?php else: ?>
    <h3>Painel de Produtos</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="text" name="nome" placeholder="Nome do produto" required>
      <input type="text" name="preco" placeholder="Preço" required>
      <input type="file" name="imagem" required>
      <button type="submit" name="addProduto">Adicionar Produto</button>
    </form>
    <h3>Produtos Atuais</h3>
    <?php foreach($produtos as $p): ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input type="text" name="nome" value="<?= $p['nome'] ?>" required>
        <input type="text" name="preco" value="<?= $p['preco'] ?>" required>
        <input type="file" name="imagem">
        <button type="submit" name="editProduto">Editar</button>
        <a href="?remove=<?= $p['id'] ?>" style="color:red;font-weight:bold;">Remover</a>
      </form>
    <?php endforeach; ?>
    <a href="?logout=1">Sair</a>
  <?php endif; ?>
</div>

<!-- Catálogo -->
<div class="container catalogo-container" id="catalogo">
  <h2>🛍️ Catálogo de Produtos</h2>
  <?php foreach($produtos as $p): ?>
    <div class="produto">
      <img src="produtos/<?= $p['imagem'] ?>" alt="<?= $p['nome'] ?>">
      <h3><?= $p['nome'] ?></h3>
      <p><?= $p['preco'] ?></p>
    </div>
  <?php endforeach; ?>
  <button class="btn-voltar" onclick="voltar()">⬅️ Voltar</button>
</div>

<script>
const links = {
  facebook: "https://www.facebook.com/profile.php?id=61572507970138",
  instagram: "https://www.instagram.com/lm_imports010"
};
function abrirLink(tipo){const url=links[tipo];if(url) window.open(url,"_blank");}
function abrirChat(){const msg=encodeURIComponent("Olá! Gostaria de saber mais sobre os produtos da LM Imports.");window.open(`https://wa.me/558597723541?text=${msg}`,"_blank");}

function abrirCatalogo(){document.getElementById("principal").style.display="none";document.getElementById("catalogo").style.display="grid";}
function voltar(){document.getElementById("catalogo").style.display="none";document.getElementById("principal").style.display="block";}

const toggle=document.getElementById('toggle-theme');const key='index-tema';
function setTheme(theme){document.documentElement.setAttribute('data-theme',theme);localStorage.setItem(key,theme);toggle.textContent=theme==='dark'?'☀️':'🌙';}
(function(){const saved=localStorage.getItem(key);if(saved){setTheme(saved);return;}const prefersDark=window.matchMedia('(prefers-color-scheme: dark)').matches;setTheme(prefersDark?'dark':'light');})();
toggle.addEventListener('click',()=>{const current=document.documentElement.getAttribute('data-theme');setTheme(current==='dark'?'light':'dark');});

// Botão admin
function toggleLogin(){
    const panel = document.getElementById('loginPanel');
    panel.style.display = panel.style.display==='none' || panel.style.display==='' ? 'block' : 'none';
}
</script>
</body>
</html>
