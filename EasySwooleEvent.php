<?php


namespace EasySwoole\EasySwoole;


use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use App\WebSocket\event\OnWorkStart;
use App\WebSocket\WebSocketEvents;
use App\WebSocket\WebSocketParser;
use EasySwoole\Component\Pool\PoolManager;
use EasySwoole\Socket\Dispatcher;
use EasySwoole\Utility\File;
use App\Process\HotReload;
use App\Utility\Template\Blade;
use easySwoole\Cache\Cache;
use EasySwoole\ORM\DbManager;
use EasySwoole\ORM\Db\Connection;
use App\Process\OnlineUser;
class EasySwooleEvent implements Event
{
    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');
        // 加载配置项
        self::loadConf();
        OnlineUser::getInstance();

        //redis配置
        $redisConf = Config::getInstance()->getConf('REDIS');
        $redisPoolConfig = new  \EasySwoole\Redis\Config\RedisConfig();
        $redisPoolConfig->setHost($redisConf['host']);
        $redisPoolConfig->setPort($redisConf['port']);
        $redisPoolConfig = \EasySwoole\RedisPool\RedisPool::getInstance()->register($redisPoolConfig, 'redis');
        //配置连接池连接数
        $redisPoolConfig->setMinObjectNum(10);
        $redisPoolConfig->setMaxObjectNum(50);

        //mysql配置
        $dbConf = Config::getInstance()->getConf('MYSQL');
        $config = new \EasySwoole\ORM\Db\Config();;
        $config->setDatabase($dbConf['db']);
        $config->setUser($dbConf['username']);
        $config->setPassword($dbConf['password']);
        $config->setHost($dbConf['host']);
        $config->setPort($dbConf['port']);
        $config->setCharset($dbConf['charset']);

        //连接池配置
        $config->setGetObjectTimeout(5.0); //设置获取连接池对象超时时间
        $config->setIntervalCheckTime(30*1000); //设置检测连接存活执行回收和创建的周期
        $config->setMaxIdleTime(15); //连接池对象最大闲置时间(秒)
        $config->setMinObjectNum(10); //设置最小连接池存在连接对象数量

        $config->setMaxObjectNum(50); //设置最大连接池存在连接对象数量
        $config->setAutoPing(3); //设置自动ping客户端链接的间隔
        DbManager::getInstance()->addConnection(new Connection($config));
    }

    public static function loadConf()
    {
        $files = File::scanDirectory(EASYSWOOLE_ROOT . '/App/Config');
        if (is_array($files)) {
            foreach ($files['files'] as $file) {
                $fileNameArr = explode('.', $file);
                $fileSuffix = end($fileNameArr);
                if ($fileSuffix == 'php') {
                    Config::getInstance()->loadFile($file);
                } elseif ($fileSuffix == 'env') {
                    Config::getInstance()->loadEnv($file);
                }
            }
        }
    }

    public static function mainServerCreate(EventRegister $register)
    {
        /**
         * ****************  服务热启动  ****************
         */
        $hot_reload = (new HotReload('HotReload', ['disableInotify' => false]))->getProcess();
        ServerManager::getInstance()->getSwooleServer()->addProcess($hot_reload);

        /**
         * ****************  缓存  ****************
         */
        $conf = Config::getInstance()->getConf('app.cache');
        Cache::init($conf);
//        OnlineUser::getInstance();

        /**
         * ****************  注册websocket相关  ****************
         */
        $web = new WebSocketEvents();
        $onWorkerStart = new OnWorkStart();
        $register->set(EventRegister::onWorkerStart, function (\swoole_websocket_server $server,  $workerId) use ($onWorkerStart) {
            $onWorkerStart->onWorkerStart($server, $workerId);
        });
        //注册连接事件
        $register->add(EventRegister::onOpen, function (\swoole_server $server, \swoole_http_request $request) use ($web) {
            $web::onOpen($server, $request);
        });
        $register->add(EventRegister::onClose, function (\swoole_server $server, int $fd, int $reactorId) use ($web) {
            $web::onClose($server, $fd, $reactorId);
        });
        $conf = new \EasySwoole\Socket\Config();
        $conf->setType($conf::WEB_SOCKET);
        $conf->setParser(new WebSocketParser);
        $dispatch = new Dispatcher($conf);
        $register->set(EventRegister::onMessage, function (\swoole_server $server, \swoole_websocket_frame $frame) use ($dispatch) {
            $dispatch->dispatch($server, $frame->data, $frame);
        });
        $register->add(EventRegister::onTask, function () {

        });

        /**
         * ****************  mysql 热启动  ****************
         */
        $register->add($register::onWorkerStart,function (){
            //链接预热
            DbManager::getInstance()->getConnection()->getClientPool()->keepMin();
        });
    }
}