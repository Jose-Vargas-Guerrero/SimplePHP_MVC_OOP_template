<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{SITE_TITLE}}</title>
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{BASE_DIR}}/public/css/appstyle.css" />
  <script src="https://kit.fontawesome.com/{{FONT_AWESOME_KIT}}.js" crossorigin="anonymous"></script>
  {{foreach SiteLinks as SiteLink}}
  <link rel="stylesheet" href="{{~BASE_DIR}}/{{SiteLink}}" />
  {{endforeach}}
  {{foreach BeginScripts as BeginScript}}
  <script src="{{~BASE_DIR}}/{{BeginScript}}"></script>
  {{endforeach}}
</head>

<body>
  <header>
    <input type="checkbox" class="menu_toggle" id="menu_toggle" />
    <label for="menu_toggle" class="menu_toggle_icon">
      <div class="hmb dgn pt-1"></div>
      <div class="hmb hrz"></div>
      <div class="hmb dgn pt-2"></div>
    </label>
    <h1>{{SITE_TITLE}}</h1>
    <nav id="menu">
      <ul>
        <li><a href="index.php?page={{PRIVATE_DEFAULT_CONTROLLER}}"><i class="fas fa-home"></i>&nbsp;Inicio</a></li>
        {{foreach NAVIGATION as nav_item}}
            <li><a href="{{nav_item.nav_url}}">{{!nav_item.nav_label}}</a></li>
        {{endforeach}}
        <li><a href="index.php?page=sec_logout"><i class="fas fa-sign-out-alt"></i>&nbsp;Salir</a></li>
      </ul>
    </nav>
    {{if login}}
    <span class="username">{{login.userName}} <a href="index.php?page=sec_logout"><i class="fas fa-sign-out-alt"></i></a></span>
    {{endif}}
  </header>
  <main>
    {{{page_content}}}
  </main>
  <footer>
    <div>Todo los Derechos Reservados {{~CURRENT_YEAR}} &copy;</div>
  </footer>
  {{foreach EndScripts as EndScript}}
  <script src="{{~BASE_DIR}}/{{EndScript}}"></script>
  {{endforeach}}
</body>
</html>
