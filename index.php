<?php

    class HomeController {

        public function Index(){
            echo "HomeController====Index";
        }

        public function getUser(){
            echo "HomeController====User";
        }
    }

    class Router {

        private $_methods = [];
        private $_routers = [];
        private $_callbacks = [];
        private $_foundRouter = false;
        private $_allowMethods = ['POST', 'GET', 'DELETE','HEAD','PUT','ANY','ERROR'];
        private $_errorCallback;
        const ANY_METHOD = 'ANY';
        const COMMON_PATTERNS = [
            ':any' => '[^/]+',
            ':num' => '[0-9]+',
            ':all' => '[.*]'
        ];

        public function __call($name, $arguments) {

            $uri      = str_replace('//', '/', dirname( $_SERVER['PHP_SELF'] ) . $arguments[0]);
            $method   = strtoupper($name);
            $callback = $arguments[1];

            if ( !in_array( $method, $this->_allowMethods ) ){
                throw new \Exception('Request method "'. $method .'" is not allow.');
            }

            $this->_methods[] = $method;
            $this->_callbacks[] = $callback;
            $this->_routers[] = $uri;
        }

        public function error($callback){
            $this->_errorCallback = $callback;
        }

        public function run(){

            $uri    = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            $method = strtoupper($_SERVER['REQUEST_METHOD']);

            //如果是字符串匹配的路由
            if ( in_array( $uri, $this->_routers ) ) {

                $this->_uriMatch( $uri, $method );

            //如果是正则匹配的路由
            } else {

                $this->_regexMatch( $uri, $method );
            }

            //如果路由没有找到
            if ( $this->_foundRouter === false ){
                if ( !$this->_errorCallback ){
                    $this->_errorCallback = function(){
                        header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
                        echo '404';
                    };
                }
                call_user_func( $this->_errorCallback );
            }
        }

        protected function _uriMatch( $uri, $method ){

            $router_pos = array_keys( $this->_routers, $uri );

            foreach( $router_pos as $router ){

                if ( $this->_methods[$router] == $method || $this->_methods[$router] === self::ANY_METHOD ){

                    $this->_foundRouter = true;

                    if ( is_callable( $this->_callbacks[$router] ) ){

                        call_user_func( $this->_callbacks[$router] );

                    } elseif ( is_string( $this->_callbacks[$router] ) ){

                        $parts = explode('@', $this->_callbacks[$router]);

                        $controller = new $parts[0];
                        $method = $parts[1];
                        $controller->$method();

                    } else {
                        throw new \Exception( 'Route handle is not a callback or class name.');
                    }
                }
            }
        }

        protected function _regexMatch( $uri, $method ){

            $pos = 0;

            foreach( $this->_routers as $router ){

                $router = preg_replace_callback('#:[^/)]+#i', function( $matches ){

                    if (isset( self::COMMON_PATTERNS[$matches[0]] ) ) {

                        return self::COMMON_PATTERNS[$matches[0]];

                    } else {

                        return '([^/]+)';
                    }

                    }, $router);

                /*
                if ( strpos( $router, ':' ) !== false) {
                    $router = str_replace( array_keys(self::COMMON_PATTERNS), array_values(self::COMMON_PATTERNS), $router );
                }*/

                //uri matched
                if ( preg_match( '#^'. $router .'$#', $uri, $matched) ){

                    //request method matched
                    if ( $this->_methods[$pos] == $method || $this->_methods[$pos] == self::ANY_METHOD ){

                        $this->_foundRouter = true;

                        //echo "允许的method: ".$this->_methods[$pos].'<br />';
                        //echo "请求的method: ".$method.'<br />';

                        $matched = array_slice($matched, 1);

                        if ( is_callable( $this->_callbacks[$pos] ) ){

                            call_user_func_array( $this->_callbacks[$pos], $matched);

                        } elseif ( is_string( $this->_callbacks[$pos] ) ){

                            $parts = explode('@', $this->_callbacks[$pos]);

                            $controller = new $parts[0];
                            $method = $parts[1];

                            if ( method_exists( $controller, $method ) ){
                                call_user_func_array( [ $controller, $method ], $matched );
                            } else {
                                throw new \Exception( 'Route handle is not a found.');
                            }

                        } else {
                            throw new \Exception( 'Route handle is not a callback or class name.');
                        }
                    }
                }

                $pos++;
            }

        }
    }

    $route = new Router();


    $route->any( '/any', function(){
        echo 'xxx';
    } );

    $route->get('/router', function(){
        echo 'router';
    });

    $route->get('/', function(){
        echo "hello world";
    });

    $route->post('/', function(){
        echo "hello world";
    });

    $route->get('/class', 'HomeController@index');

    $route->get('/user', 'HomeController@getUser');

    $route->get('aaa/bbb/ccc', function(){
        echo 'aaa/bbb/ccc';
    });

    $route->get('/test/(:aaa)/(:bbb)', function( $aaa, $bbb ){
        var_dump( $aaa, $bbb );
    });

    $route->get('/user/:name', function( $name ){
        var_dump( $name );
    });


    $route->any('/(:any)', function( $value ){
        var_dump( $value );
    });

    $route->error(function(){
        echo '404 not found.';
    });

    $route->run();

