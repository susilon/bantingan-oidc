---
name: bantingan-php-app
description: Scaffold a new PHP application based on the Bantingan framework (susilon/bantingan). Handles composer.json with custom VCS repository, directory structure, entry point, base configuration, default Home controller/views, FrankenPHP Dockerfile, and .dockerignore. Use when user wants to create, initialize, or bootstrap a Bantingan PHP app.
---

# Bantingan PHP App Skill

Create a new PHP application based on the [Bantingan Framework](https://github.com/susilon/bantingan).

## When to Use

- User asks to "create bantingan app", "init bantingan", "bootstrap bantingan project", "new bantingan php app"
- User wants to scaffold a PHP project using `susilon/bantingan` framework
- User is starting from empty directory or wants to add Bantingan to existing project

## Workflow

Execute the following steps in order. Verify each file before proceeding. Do not skip verification.

### 1. Check Target Directory

- Determine target directory: use current working directory unless user specifies a path.
- List contents: if not empty, ask user to confirm before overwriting existing `composer.json`, `index.php`, or `config/web.config.yml`.
- Ensure you have write permissions.

### 2. Create `composer.json`

Create `composer.json` at project root with the custom VCS repository. Bantingan is not on default Packagist, so `repositories` is required.

```json
{
    "name": "app/bantingan-app",
    "description": "My Bantingan App",
    "type": "project",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/susilon/bantingan.git"
        }
    ],
    "require": {
        "susilon/bantingan": "dev-php8-update8.5 || dev-dev-php8-update8.5"
    },
    "minimum-stability": "dev",
    "prefer-stable": false,
    "config": {
        "github-protocols": ["https"]
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Modules\\": "modules/"
        }
    }
}
```

**Rules:**
- Must keep `repositories` entry exactly as above.
- Version must be `dev-php8-update8.5` (Composer 2.10+ may resolve as `dev-dev-php8-update8.5`, template uses `dev-php8-update8.5 || dev-dev-php8-update8.5`) unless user explicitly requests different branch/tag.
- If `composer.json` already exists, merge: add `repositories` if missing and add `susilon/bantingan` to `require`. Do not overwrite other dependencies without asking.
- After creating/merging, instruct user to run `composer install` or `composer update` (do not run automatically unless user asks).

**Reference template:** `templates/composer.json` in this skill directory.

### 3. Create Directory Structure

Create the following folders (use `mkdir -p`):

```
app/
  controllers/
  models/
  views/
  views/Shared/
  views/Home/
config/
modules/
public/
```

Optional but recommended:
```
public/assets/
```

- If folders already exist, leave them untouched.
- Ensure `config/` and `public/` are created at root, not nested.

**Bantingan rules:**
- **Controllers:** stored in folder defined by `application_settings.Controllers` in `config/web.config.yml`. By default `app/controllers`. When creating any new controller, always place it under the folder pointed to by this setting - do not hardcode an alternate path unless the setting has been changed.
- **Models:** database context files stored in folder defined by `application_settings.Models`. By default `app/models`. Create model/context files under that folder. Models using ORM should extend `\Bantingan\Models`; non-ORM models may be plain PHP.
- **Views:** HTML/Smarty files stored in folder defined by `application_settings.Views`. By default `app/views`. Create view files under that folder. View resolution is automatic - see View Resolution below.

### 4. Create Entry Point `index.php`

Create `index.php` at project root.

Content:

```php
<?php
require_once 'vendor/autoload.php';
// require_once 'config/language/en.php';

use Bantingan\Bantingan;
use Bantingan\Settings;
use Modules\Common\Session\MongoSession;

error_reporting(E_ALL);

$basepath = __DIR__;
// load settings
Settings::LoadFromPath($basepath, '/config/web.config.yml');
// session settings
if (isset(APPLICATION_SETTINGS["Session_DB"]) && APPLICATION_SETTINGS["Session_DB"])
{
    $session = new MongoSession(); // session stored in db
} else {
    session_start();
}
// start application
new Bantingan();
```

**Rules:**
- File must be at root as `index.php`.
- Do not modify namespace imports. Keep `MongoSession` handling exactly as above.
- If `index.php` exists, ask before overwriting.

**Reference template:** `templates/index.php`

### 5. Create `config/web.config.yml`

Create `config/web.config.yml` with basic application settings:

```yaml
# Basic application settings
application_settings :
  SiteTitle : Bantingan App # Site Title variable as default Page Title, set $pageTitle viewbag at controller to override
  Controllers : app/controllers # Location of controllers file
  Models : app/models # Location of models file
  Views : app/views # Location of views file  
  Module : modules # Location of module file
  BaseUrl : # Application base path  
  DefaultController: Home # default controller
  RedBeanPHP_Freeze : false  # RedbeanPHP freeze settings, set true to disable auto create table/columns
  SharedViewFolder : Shared/ # For storing common html files for ex : error.html
  ErrorFileTemplate : error.html # HTML file to return when error occured
  Session_DB: false # Session data stored in DB
  Cookie_Secret_Key: abcdefg
  Cookie_Runtime : 
  Cookie_Domain : 
  Language : en # Default Language file to use in language directory, to override, set l in querystings, ex: ?l=id, to load id.php as language file
  ShowRoutingError : true
  AppVersion : 1.0.0
  AppDescription : My Bantingan App
```

**Rules:**
- Ensure `config/` directory exists first.
- Do not change YAML keys casing - Bantingan is case-sensitive on `application_settings`.
- If file exists, ask before overwriting.

**Reference template:** `templates/web.config.yml`

### 6. Create Default Controller `app/controllers/HomeController.php`

Create `app/controllers/HomeController.php`:

```php
<?php
/*
Copyright (c) <2021> Susilo Nurcahyo

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

namespace Controllers;

use Bantingan\Controller;

class HomeController extends Controller
{
    public function index()	
    {                           
        $this->viewBag->variableFromController1 = 'Check the app folder to see the code';
        $this->viewBag->variableFromController2 = 'Your page is generated';
        return $this->view();
    }
} 
```

**Rules:**
- Controller storage path is defined by `application_settings.Controllers` in `config/web.config.yml` - by default `app/controllers`. Always create controller files under that folder (default `app/controllers/HomeController.php`). If the setting is customized, use the configured path instead.
- Models storage path is defined by `application_settings.Models` - by default `app/models`.
- Views storage path is defined by `application_settings.Views` - by default `app/views`.
- Namespace must be `Controllers` for top-level controllers; for grouped controllers use sub-namespace e.g. `Controllers\Masters` for `app/controllers/Masters/ProvinceController.php`.
- **Controllers must extend `Bantingan\Controller`** (e.g. `use Bantingan\Controller; class HomeController extends Controller`). All controller files should extend `Bantingan\Controller`.
- **Every controller MUST have an `index()` method by default.** It is the default action when routing to `/Controller` or when no action is specified. It may either return a view (`return $this->view();`) or a simple response (`echo 'OK';`). Do not create a controller without `index()`.
- **Models should extend `\Bantingan\Models`** when using built-in ORM (RedBeanPHP). Example: `class User extends \Bantingan\Models`. If not using built-in ORM, model may be plain PHP class without extending.
- Class `HomeController` example extends `Bantingan\Controller` with `index()` returning `$this->view()`.

**View Resolution (automatic, no explicit view name needed):**
Framework resolves `$this->view()` based on controller-name/function-name. Examples:
- `HomeController::index()` → `app/views/Home/index.html` (folder `Home` = controller name without `Controller` suffix, file `index.html` = function name)
- `ProvinceController` at `app/controllers/Masters/ProvinceController.php` with `namespace Controllers\Masters;` and `function index()` → `app/views/Masters/Province/index.html` (namespace segment `Masters` becomes subfolder)
- `ProvinceController::detail()` → `app/views/Masters/Province/detail.html`
General pattern: `app/controllers/[Namespace/]FooController.php::bar()` → `app/views/[Namespace/]Foo/bar.html` where Views base is `application_settings.Views`.

- If file exists, ask before overwriting.

**Reference template:** `templates/app/controllers/HomeController.php`

### 7. Create Shared Layout `app/views/Shared/layout.html`

Create `app/views/Shared/layout.html`:

```smarty
{assign applicationSettings $smarty.const.APPLICATION_SETTINGS}
<!DOCTYPE HTML>
<html lang="en-US">
<head>
	<meta charset="UTF-8">
	<title>{$pageTitle|default:$siteTitle}</title>

{block name=htmlhead}{/block}
</head>
<body>
	<p class="main_head">Welcome</p>
	
{block name=bodycontent}{/block}
<hr>
{$variableFromController1}
<br>
	<p class="footer">{$applicationSettings.AppDescription} - v{$applicationSettings.AppVersion} Powered By Bantingan</p>

<script>
	/* main javascript block */
	var appName = '{$applicationSettings.SiteTitle}';
</script>
{block name=scriptfooter}{/block}
</body>
</html> 
```

**Rules:**
- Must preserve Smarty `{block}` definitions: `htmlhead`, `bodycontent`, `scriptfooter`.
- Keep `{/literal}` and `{$variableFromController1}` exactly as shown.
- If file exists, ask before overwriting.

**Reference template:** `templates/app/views/Shared/layout.html`

### 8. Create Home View `app/views/Home/index.html`

Create `app/views/Home/index.html`:

```smarty
{extends file="../Shared/layout.html"}
{block name=htmlhead}
<!-- this will be at head block-->
{/block}

{block name=bodycontent}
<hr>
{$variableFromController2}
<!-- this will be at body content block-->
{/block}

{block name=scriptfooter}
<!-- this will be at script footer block-->
 <script>
	alert('Welcome to ' + appName);
 </script>
{/block} 
```

**Rules:**
- Must extend `../Shared/layout.html`.
- Keep all three blocks and `{$variableFromController2}`.
- If file exists, ask before overwriting.

**Note:** This view file location `app/views/Home/index.html` follows the automatic resolution rule above. It is the view for `HomeController::index()` as `Views` default is `app/views`. For namespaced controllers (e.g. `Controllers\Masters\ProvinceController::index()`), place view at `app/views/Masters/Province/index.html`.

**Reference template:** `templates/app/views/Home/index.html`

### 9. Create Dockerfile

Create `Dockerfile` at project root (FrankenPHP 1-php8.5):

**Reference template:** `templates/Dockerfile`

Content is a multi-stage FrankenPHP image with:

- `FROM dunglas/frankenphp:1-php8.5`
- System deps: curl, git, libicu-dev, libpng-dev, libjpeg62-turbo-dev, libfreetype6-dev, libzip-dev, mariadb-client, unzip, zlib1g-dev
- PHP extensions: pdo_mysql, mysqli, gd, intl, zip, mongodb, redis, opcache
- Composer install via https://getcomposer.org/installer
- PHP ini: upload_max_filesize 10M, post_max_size 10M, memory_limit 256M
- Layer-cached `composer install` then `COPY . /app` then `composer dump-autoload`
- Writable `tmp uploads templates_c` owned by www-data
- Entrypoint `docker/app/entrypoint.sh` and Caddyfile `Caddyfile`
- Expose 80 443, healthcheck, ENTRYPOINT/CMD frankenphp

Also create supporting files if missing:

- `docker/app/entrypoint.sh` (from `templates/docker/app/entrypoint.sh`) - minimal shell entrypoint that ensures vendor/autoload.php exists
- `Caddyfile` (from `templates/Caddyfile`) - basic Caddy config with php_server on :80
- `.dockerignore` (from `templates/.dockerignore`) - excludes vendor, git, tmp, uploads, etc. for smaller build context

**Rules:**
- If `Dockerfile` exists, ask before overwriting.
- Ensure `docker/app/` directory exists before copying entrypoint.
- Make `docker/app/entrypoint.sh` executable (`chmod +x`).
- If `.dockerignore` exists, ask before overwriting.

### 10. Verification Checklist

After scaffolding, verify:

- [ ] `composer.json` exists and contains `repositories` with `https://github.com/susilon/bantingan.git` and `require.susilon/bantingan = dev-php8-update8.5 || dev-dev-php8-update8.5` (branch `dev-php8-update8.5`, Composer 2.10+ resolves as `dev-dev-php8-update8.5`)
- [ ] Directories `app/controllers`, `app/models`, `app/views`, `app/views/Shared`, `app/views/Home`, `config`, `modules`, `public` exist
- [ ] `index.php` exists at root with correct bootstrap code
- [ ] `config/web.config.yml` exists with all keys
- [ ] `app/controllers/HomeController.php` exists with correct namespace and MIT header
- [ ] `app/views/Shared/layout.html` exists with Smarty blocks
- [ ] `app/views/Home/index.html` exists and extends layout
- [ ] `Dockerfile` exists with FrankenPHP base image
- [ ] `docker/app/entrypoint.sh` exists and is executable
- [ ] `Caddyfile` exists
- [ ] `.dockerignore` exists and excludes vendor/.git/tmp
- [ ] Run `composer validate` if composer is available (warn if not installed)
- [ ] Remind user: run `composer install` then serve via `php -S localhost:8000` or configure web server to point to project root

### 11. Post-Setup Instructions

Provide user with next steps:

```bash
composer install
php -S localhost:8000
# or with docker/nginx, point document root to project root where index.php lives
# then visit http://localhost:8000/ -> should route to HomeController::index()
```

Optional follow-ups to offer:
- Setup `.htaccess` for Apache or nginx config
- Add `config/language/en.php` if needed
- Create additional controllers/models

## Templates

This skill includes templates in `templates/`:
- `templates/composer.json`
- `templates/index.php`
- `templates/web.config.yml`
- `templates/app/controllers/HomeController.php`
- `templates/app/views/Shared/layout.html`
- `templates/app/views/Home/index.html`
- `templates/Dockerfile`
- `templates/docker/app/entrypoint.sh`
- `templates/Caddyfile`
- `templates/.dockerignore`

Copy from templates when available, fallback to inline content above.

## Notes

- Bantingan requires PHP 8+ (branch `dev-php8-update8.5`).
- **Inheritance:** Controllers must extend `Bantingan\Controller`; Models should extend `\Bantingan\Models` when using built-in ORM (otherwise plain PHP allowed).
- Do not run `composer install` automatically unless user explicitly requests.
- Preserve existing files; always confirm before overwrite.
- Default controller/view are minimal working example - HomeController maps to DefaultController: Home in web.config.yml.
- Bantingan stores controllers in `application_settings.Controllers` (default `app/controllers`), models in `application_settings.Models` (default `app/models`), views in `application_settings.Views` (default `app/views`). Always respect these settings when adding files.
- Views are resolved automatically by `$this->view()` as `Views/[Controller]/[Action].html` (e.g. `HomeController::index()` → `Home/index.html`, `Masters\ProvinceController::index()` → `Masters/Province/index.html`). Do not pass explicit view path unless overriding default.
- **Views + Smarty + JavaScript:** Smarty parses `{...}` as template tags. When creating views containing JavaScript, **always add a space after every `{`** (e.g. `if (x) { console.log(...)}`, `let o = { key: 1}`, `function f() { return ...}`) so Smarty does not conflict. Alternatively wrap JS blocks with `{literal}...{/literal}`, but the space-after-`{` rule is preferred.

