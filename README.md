# Single MVC

Single MVC 是一個方便使用、功能完整、單一檔案的 PHP 框架。它提供了以下功能：

* 多種目錄結構
* 基礎 MVC
* 路由覆寫
* 正確處理各種 HTTP 請求方法的資料
* 統一的方法取得輸入資料
* RESTful 方法對應
* 多國語系
* Model 自動載入
* 資料庫存取
* CURL 請求 (可多執行序)
* 效能紀錄與除錯
* JWT 編碼與解碼
* 檢查框架更新

---

## 入門

### 系統需求

為求更好的性能、更廣泛的功能，本框架需要以下環境：

```
Apache 或 IIS (啟用 URL Rewrite)
PHP 7.0+  
MySQL 5.5.3+
```

其中，PHP 需要開啟以下 Extension

```
Multibyte String
PDO MySQL
OpenSSL
cURL
```

### 目錄結構

本框架支援多種目錄結構，分別為：

**單一檔案**：加入框架後，把所有東西都寫在 index.php 。
```
網站根目錄
 ├ index.php
 └ SingleMVC.php
```

**分離檔案**：把 SingleMVC.php 更名為 index.php，並建立 source 目錄與子目錄。
```
網站根目錄
 ├ source
 │  ├ 3rd
 │  ├ controllers
 │  ├ helper
 │  ├ lang
 │  ├ models
 │  ├ views
 │  └ config.php
 └ index.php
```

**共用框架**：把 SingleMVC.php 放在其他地方讓多個 index.php 使用。
```
某個地方
 └ SingleMVC.php

網站根目錄
 ├ A應用程式
 │  └ index.php
 └ B應用程式
    └ index.php
```

**私有檔案**：除了 index.php 放在網站目錄，其餘 source 放到網站目錄以外。(注意：這種用法需要在 index.php 中定義常數 ROOT 到 source 的目錄)
```
某個地方
 └ SingleMVC.php

另一個地方
 └ A應用程式
    └ source
      ├ controllers
      ├ models
      ├ views
      └ config.php

網站根目錄
 └ index.php
```

以上結構可以混用，也就是說，你可以把一部分程式放在 index.php 中，一部分放在 source 底下，框架都可以正確讀取。

### 安裝

非常簡單，只要選定一種目錄結構，然後把 SingleMVC.php 複製到那就可以囉。

為了讓路由可以正常運作，你必須要設定 URL Rewrite，下列提供 Apache 與 IIS 的範本。

Apache：檔案 .htaccess 修改或加入以下設定。
```conf
<IfModule mod_rewrite.c>
  RewriteEngine on
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^(.*)$ ./index.php?/$1 [QSA,PT,L]
</IfModule>
```

IIS：檔案 web.config 加入 rewrite 區塊。
```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <clear />
        <rule name="MVC" stopProcessing="true">
          <match url="^(.*)$" ignoreCase="false" />
          <conditions logicalGrouping="MatchAll">
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
          </conditions>
          <action type="Rewrite" url="./index.php?/{R:1}" appendQueryString="true" />
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>
```

---

## 開發

從最簡單的 Hello World 開始吧。這裡選用單一檔案的目錄結構，假設網址為 http://localhost/welcome/ ，因此可以建立 index.php ：

```php
<?php
require 'SingleMVC.php';

// 定義控制器
class welcome extends Controller {
    public function index() {
        output('text', 'Hello World');
    }
}
?>
```

開啟網頁後，就可以看到輸出文字

```
Hello World
```

更多功能與函數請見 [Wiki](https://github.com/kouji6309/SingleMVC/wiki)

---

## 授權

這個專案使用 MIT 授權條款 ，詳細條款在 [LICENSE.md](LICENSE.md)