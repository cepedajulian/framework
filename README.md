# DasIntranet Framework

**Versión 1.0.1** · **PHP 8.4** · **Composer / PSR-4**

DasIntranet Framework es un microframework PHP orientado a aplicaciones web y APIs sencillas. Ofrece enrutamiento HTTP, controladores, vistas con layouts, acceso a MariaDB/MySQL mediante PDO y clientes HTTP basados en cURL.

## Novedades de la versión 1.0.1

- **`Loader` integrado en el framework**: inicializa vistas, funciones auxiliares y archivos de rutas sin necesitar un `app/loader.php` propio.
- **PSR-4**: nombres y rutas de las clases adecuados para Composer y sistemas Linux sensibles a mayúsculas/minúsculas.
- **Estilo PSR-12** aplicado a las clases.
- **Enrutamiento mejorado**: métodos `GET`, `POST`, `PUT`, `PATCH` y `DELETE`, parámetros de ruta, separación del *query string* y respuesta 404.
- **Vistas más robustas**: `render()` y `extend()`, resolución segura de rutas, soporte de notación con puntos y limpieza de búferes ante excepciones.
- **PDO**: consultas preparadas, conexión `utf8mb4`, validación de nombres de tabla/campo en `find()` y transacciones explícitas.
- **Clientes API**: verificación de certificados HTTPS, tiempos de espera, estado HTTP y un cliente cURL interno compartido.
- **Seguridad de sesiones**: `ApiFree` ya no reenvía cookies PHP automáticamente.

> **Nota de compatibilidad:** aunque se conservan las principales firmas públicas, los cambios en excepciones, transacciones y envío de cookies deben revisarse al migrar desde la versión 1.0.

## Requisitos

- PHP **8.4** (restricción de Composer: `^8.4`).
- Composer 2.
- Extensiones `json` y `pdo`.
- Extensión `pdo_mysql` para utilizar `Db` con MariaDB/MySQL.
- Extensión `curl` para utilizar `Api` y `ApiFree`.
- MariaDB o MySQL solo si tu aplicación utiliza base de datos.

## Estructura del paquete

```text
framework/
├── composer.json
├── README.md
├── src/
│   ├── App.php          # Router y despacho HTTP
│   ├── Controller.php   # Clase base opcional de controladores
│   ├── View.php         # Renderizado de vistas y layouts
│   ├── Loader.php       # Inicialización de aplicaciones
│   ├── Db.php           # Acceso a MariaDB/MySQL con PDO
│   ├── Api.php          # Cliente HTTP con token Bearer / Basic
│   ├── ApiFree.php      # Cliente HTTP sin token
│   └── HttpClient.php   # Adaptador interno de cURL
└── tests/
    └── smoke.php
```

El namespace del paquete es `Dasintranet\Framework\` y Composer lo asocia con `src/`.

## Instalación con Composer

El nombre del paquete declarado en `composer.json` es **`dasintranet/framework`**.

### Opción A: framework y aplicación en repositorios y carpetas separados

Es útil para desarrollar localmente sin duplicar el código del framework:

```text
proyectos/
├── framework/        # Repositorio de este paquete
└── dasintranet/      # Repositorio de la aplicación
```

En el `composer.json` **de la aplicación**:

```json
{
  "name": "dasintranet/ejemplo",
  "type": "project",
  "require": {
    "php": "^8.4",
    "dasintranet/framework": "^1.0"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../framework",
      "options": {
        "symlink": true,
        "versions": {
          "dasintranet/framework": "1.0.1"
        }
      }
    }
  ],
  "autoload": {
    "psr-4": {
      "App\\Controllers\\": "app/controllers/"
    }
  }
}
```

Desde la carpeta de la aplicación:

```bash
composer install
```

Composer cargará el paquete mediante `vendor/autoload.php`. **No copies manualmente `src/` a `vendor/`**.

### Opción B: instalar desde el repositorio Git del framework

Si publicás este paquete en GitHub y lo etiquetás como `v1.0.1`, la aplicación puede declarar un repositorio de tipo `vcs`:

```json
{
  "require": {
    "php": "^8.4",
    "dasintranet/framework": "^1.0"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/cepedajulian/framework"
    }
  ]
}
```

## Uso rápido: aplicación mínima

La siguiente estructura pertenece a **tu aplicación**, no al repositorio del framework:

```text
dasintranet/
├── app/
│   ├── controllers/
│   │   └── HomeController.php
│   ├── routes/
│   │   └── web.php
│   └── views/
│       ├── hello.php
│       ├── error404.php          # Opcional
│       └── layouts/
│           ├── appHead.php
│           └── appFooter.php
├── public/
│   └── index.php
├── composer.json
└── vendor/                       # Generado por Composer
```

### 1. Punto de entrada: `public/index.php`

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

try {
    \Dasintranet\Framework\Loader::run(dirname(__DIR__) . '/app');
} catch (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Internal Server Error';
}
```

`Loader::run()` registra los archivos de rutas y procesa la petición. `Loader::boot($directorio)` registra rutas, vistas y funciones auxiliares **sin despachar** una petición, por ejemplo en una herramienta CLI.

El cargador busca:

- `app/views/` para las vistas;
- `app/controllers/functions.php`, opcional, para funciones auxiliares;
- todos los archivos `app/routes/*.php`, en orden alfabético.

Los controladores se cargan mediante PSR-4 de Composer; no se incluyen manualmente con `require_once`.

### 2. Rutas: `app/routes/web.php`

```php
<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Dasintranet\Framework\App;

App::get('/', [HomeController::class, 'hello']);
App::get('/hello', [HomeController::class, 'hello']);

App::get('/api/usuarios/{id}', static fn (string $id): array => [
    'id' => $id,
]);

// También están disponibles:
// App::post('/usuarios', ...);
// App::put('/usuarios/{id}', ...);
// App::patch('/usuarios/{id}', ...);
// App::delete('/usuarios/{id}', ...);
```

El enrutador envía los parámetros de ruta al callback en su orden de aparición. Los resultados `array` y `object` se convierten a JSON; las cadenas se envían como texto. Para una ruta inexistente devuelve HTTP **404** y, si existe, incluye `app/views/error404.php`.

`HEAD` utiliza las rutas `GET` sin enviar cuerpo. El framework **no** ofrece automáticamente un sistema de middleware PSR-15, inyección de dependencias ni respuestas PSR-7.

### 3. Controlador: `app/controllers/HomeController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Dasintranet\Framework\Controller;
use Dasintranet\Framework\View;

class HomeController extends Controller
{
    public function hello(): string
    {
        return (new View())->extend('hello', [
            'titulo' => 'Hola desde DasIntranet',
            'mensaje' => 'Esta vista utiliza un encabezado y un pie comunes.',
        ]);
    }
}
```

`Controller` es una clase base vacía que podés extender. Para que Composer encuentre esta clase, el archivo y namespace deben coincidir exactamente con el mapeo `App\Controllers\` del `composer.json` de la aplicación.

### 4. Vista: `app/views/hello.php`

```php
<main>
    <h1><?= htmlspecialchars($titulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($mensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
</main>
```

El framework pone las claves del arreglo `$data` a disposición de la vista como variables PHP. Escapá siempre los datos variables al escribir HTML.

### 5. Layouts

Archivo `app/views/layouts/appHead.php`:

```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo ?? 'DasIntranet', ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
```

Archivo `app/views/layouts/appFooter.php`:

```html
</body>
</html>
```

`View::extend('hello', $data)` concatena estos tres archivos en orden: **`appHead.php` → `hello.php` → `appFooter.php`**.

Si solo necesitás el contenido de la vista, sin layouts:

```php
$html = (new \Dasintranet\Framework\View())->render('hello', [
    'titulo' => 'Hola',
    'mensaje' => 'Sin layout',
]);
```

También podés utilizar notación con puntos: `render('usuarios.detalle')` equivale a `app/views/usuarios/detalle.php`.

> `extend()` requiere que existan ambos archivos `layouts/appHead.php` y `layouts/appFooter.php`; si falta alguno, se lanza una excepción.

### 6. Ejecutar la aplicación localmente

Desde la raíz **de la aplicación** (no del framework):

```bash
composer install
php -S 127.0.0.1:8000 -t public public/index.php
```

Abrí `http://127.0.0.1:8000/` o `http://127.0.0.1:8000/hello`.

En Nginx o Apache, el directorio público (*document root*) debe ser `public/` y las rutas dinámicas deben enviarse al controlador frontal `index.php`.

## Base de datos: `Db`

`Db` trabaja con MariaDB/MySQL a través de PDO. **No lee `.env` automáticamente.** Antes de instanciar `Db`, la aplicación debe definir estas constantes:

```php
<?php

declare(strict_types=1);

// set.php, cargado por el punto de entrada ANTES de usar Db.
define('DatabaseServer', getenv('DB_HOST') ?: '127.0.0.1');
define('Database', getenv('DB_NAME') ?: 'ejemplo');
define('DatabaseUser', getenv('DB_USER') ?: '');
define('DatabasePassword', getenv('DB_PASSWORD') ?: '');
```

Configurá estas variables de entorno desde PHP-FPM, Docker o el servidor. **Si creás `set.php`, agregá** `require_once dirname(__DIR__) . '/set.php';` **al principio de `public/index.php`, antes de utilizar `Db`.** No subas credenciales reales al repositorio.

### Consultas

```php
use Dasintranet\Framework\Db;

$db = new Db();

$usuarios = $db->query(
    'SELECT id, nombre FROM usuarios WHERE activo = ?',
    [1]
); // array de objetos (PDO::FETCH_OBJ)

$usuario = $db->find('usuarios', 10);           // Busca por id; objeto o false
$otro = $db->find('usuarios', 'email', $email);  // Busca por columna

$afectadas = $db->query(
    'UPDATE usuarios SET activo = ? WHERE id = ?',
    [0, 10]
); // Número de filas afectadas (int)

$db->query(
    'INSERT INTO usuarios (nombre) VALUES (?)',
    ['Ana']
);
$id = $db->lastInsertId();
```

Se utilizan consultas preparadas con emulación deshabilitada. Los **valores** se pasan como parámetros; los nombres de tablas y columnas empleados en `find()` se validan por separado.

### Transacciones

```php
$db->transaction(static function (Db $db): void {
    $db->query(
        'UPDATE usuarios SET activo = ? WHERE id = ?',
        [1, 10]
    );

    $db->query(
        'INSERT INTO auditoria (usuario_id, accion) VALUES (?, ?)',
        [10, 'activacion']
    );
});
```

Si ocurre una excepción, el framework ejecuta `rollBack()` y vuelve a lanzar esa excepción. **No admite transacciones anidadas**. `query()` por sí solo no abre una transacción explícita.

## Clientes HTTP: `Api` y `ApiFree`

Ambas clases ejecutan una petición al pasarles una URL en el constructor. Como alternativa, podés construirlas vacías y después llamar a `exec()`.

### API con Bearer token

```php
use Dasintranet\Framework\Api;

$api = new Api(
    'https://api.ejemplo.com/usuarios',
    'GET',
    null,
    $token
);

$datos = $api->response;
$estadoHttp = $api->status;
```

También existe `Api::getAccess($url, $client_id, $client_secret)` para una solicitud GET con autenticación HTTP Basic; exige HTTPS.

### API sin autenticación

```php
use Dasintranet\Framework\ApiFree;

$api = new ApiFree('https://api.ejemplo.com/estado', 'GET');

$datos = $api->response;
$estadoHttp = $api->status;
```

Si el cuerpo de respuesta contiene un objeto o arreglo JSON válido, `response` será el objeto o arreglo decodificado; en otros casos se conserva la respuesta original como texto. Un estado HTTP distinto de 2xx queda disponible en `$status` y no implica necesariamente una excepción; los fallos de transporte sí pueden producirla.

Los clientes establecen un máximo de 5 segundos de conexión y 30 segundos por solicitud, verifican certificados TLS y **no siguen redirecciones automáticamente**.

### Compartir sesión PHP con una API interna (opcional)

Por seguridad, `ApiFree` **no envía cookies de sesión** de forma predeterminada. La opción explícita es:

```php
$api = new \Dasintranet\Framework\ApiFree();
$api->shareSession = true;
$resultado = $api->exec('https://mi-servidor.example/api/interna');
```

Solo se reenvía la cookie si existe una sesión PHP activa, la URL utiliza HTTPS y su host coincide con `$_SERVER['SERVER_NAME']`. Utilizá esta opción únicamente en endpoints propios y confiables.

## Manejo de errores y seguridad

- Las operaciones del framework pueden lanzar excepciones. La **aplicación** debe capturarlas y registrar el error sin mostrar trazas en producción.
- En las plantillas PHP, escapá los valores antes de incluirlos en HTML.
- Para PDO, usá siempre parámetros preparados para valores introducidos por usuarios.
- El framework no incluye autenticación, autorización, protección CSRF, configuración CORS ni middleware de seguridad automáticos: cada aplicación debe implementar lo que necesite.
- Para un 404, el enrutador busca `app/views/error404.php`; si no existe, muestra `404 Not Found`.
- En producción, no expongas `vendor/`, `src/` ni archivos de configuración mediante el servidor web: serví únicamente `public/`.

## Pruebas

El repositorio incluye `tests/smoke.php`, creado para comprobar componentes junto con una **versión específica de la aplicación de ejemplo**. Actualmente **no es una suite autónoma**: espera encontrar una carpeta hermana llamada `dasintranet-1.0/` con sus vistas y archivos de configuración. Si la estructura del ejemplo o su vista `hello.php` cambian, algunas comprobaciones pueden necesitar adaptación.

Para verificar la sintaxis de una clase individual, podés usar:

```bash
php -l src/App.php
php -l src/Loader.php
```

Las pruebas de integración de `Db`, `Api` y `ApiFree` requieren además los servicios y extensiones PHP correspondientes.

## Migración desde la versión 1.0

1. Sustituí los archivos anteriores del framework por los de `src/` en la versión 1.0.1.
2. Actualizá el autoload de Composer y respetá las mayúsculas/minúsculas de `App.php`, `View.php`, `Db.php`, etc.
3. Si la aplicación cargaba `app/loader.php`, sustituilo por `Loader::run($directorioApp)`.
4. Revisá el tratamiento de errores: ahora las operaciones fallidas pueden lanzar excepciones.
5. Usá `Db::transaction()` cuando varias escrituras deban ser atómicas.
6. Si dependías de cookies de sesión en `ApiFree`, revisá la nueva opción `shareSession`.
7. Comprobá el comportamiento de las vistas y layouts, y realizá las pruebas de integración antes de actualizar producción.

## Estándares y alcance

El paquete utiliza **autoloading PSR-4** y sigue convenciones de estilo **PSR-12**. No implementa PSR-7 (objetos de solicitud/respuesta), PSR-15 (middleware HTTP) ni PSR-17 (fábricas HTTP). No son requisitos para utilizar este microframework.

## Licencia

El `composer.json` declara **MIT**.