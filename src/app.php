<?php
namespace Dasintranet\Framework;

class App{
    private static $routes = [];
    private static $views;

    public static function getViews(){
        return self::$views;
    }

    public static function setViews($path){
        self::$views = $path;
    }

    //Add GET routers
    public static function get($uri, $callback){
        $uri = trim($uri, '/');
        self::$routes['GET'][$uri] = $callback;
    }

    //Add POST routers
    public static function post($uri, $callback){
        $uri = trim($uri, '/');
        self::$routes['POST'][$uri] = $callback;
    }

    //Add DELETE routers
    public static function delete($uri, $callback){
        $uri = trim($uri, '/');
        self::$routes['DELETE'][$uri] = $callback;
    }

    //Add PUT routers
    public static function put($uri, $callback){
        $uri = trim($uri, '/');
        self::$routes['PUT'][$uri] = $callback;
    }

    public static function run(){
        self::$views = '../App/Views';
        
        $uri_init = $_SERVER['REQUEST_URI'];
        $uri = trim($uri_init, '/');

        $method = $_SERVER['REQUEST_METHOD'];
        
        //Find route in routes
        foreach( self::$routes[$method] as $route => $callback){
            if (strpos($route, '{') !== false){
                $route = preg_replace('#{[a-zA-Z0-9]+#', '([a-zA-Z0-9]+)', $route);
                $route = str_replace('}', '', $route);
            }

            if(preg_match("#^$route$#", $uri, $matches)){
                $params = array_slice($matches, 1);

                if(is_callable($callback)){
                    $response = $callback(...$params);
                }else{
                    if(is_array($callback)){
                        $controller = new $callback[0];
                        $response = $controller->{$callback[1]}(...$params);
                    } 
                }

                if(is_array($response) or is_object($response)){
                    header('Content-Type: application/json');
                    echo json_encode($response);
                }else{
                    echo $response;
                }

                return;
            }
        }

        http_response_code(404);
        
        if(file_exists(self::$views."/error404.php")){
            include self::$views."/error404.php";

        }else{
            echo "<br>Route Not Found! <br> URL method $method: $uri_init <br> Review /Routes/web.php file<br>";
            echo "create error404 view to customize this notice";
        }
    }
}