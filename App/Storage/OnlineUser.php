<?php

namespace App\Storage;

use App\Common\AppFunc;
use App\Model\AdminUser;
use App\Utility\Log\Log;
use EasySwoole\Component\Singleton;
use EasySwoole\Component\TableManager;
use EasySwoole\EasySwoole\ServerManager;
use Swoole\Table;

/**
 * 在线用户
 * Class OnlineUser
 * @package App\Storage
 */
class OnlineUser
{
    use Singleton;
    protected $table;  // 储存用户信息的Table

    const LIST_ONLINE = 'match:online:user:%s';
    public function __construct()
    {
        TableManager::getInstance()->add('onlineUsers', [
            'fd' => ['type' => Table::TYPE_INT, 'size' => 16],
            'nickname' => ['type' => Table::TYPE_STRING, 'size' => 128], //昵称
            'last_heartbeat' => ['type' => Table::TYPE_STRING, 'size' => 16], //最后心跳
            'match_id' => ['type' => Table::TYPE_INT, 'size' => 8], //比赛id
            'user_id' => ['type' => Table::TYPE_INT, 'size' => 8], //用户id
            'level' => ['type' => Table::TYPE_INT, 'size' => 8], //用户级别
            'type' => ['type' => Table::TYPE_INT, 'size' => 3], //1足球 2篮球  0未进直播间
        ]);

        $this->table = TableManager::getInstance()->get('onlineUsers');
    }

    /**
     * 设置用户信息
     * @param $fd
     * @param $info
     * @return mixed
     * @throws \Throwable
     */
    function set($fd, $info)
    {
        if ($info['user_id']) {
            $user = AdminUser::create()->where('id', $info['user_id'])->get();
            $user_level = $user->level;
        } else {
            $user_level = 0;
        }
        $fd = (int)$fd;
        return $this->table->set($fd, [
            'fd' => $fd,
            'nickname' => $info['nickname'],
            'user_id' => (int)$info['user_id'],
            'level' => (int)$user_level,
            'last_heartbeat' => time(),
            'match_id' => !empty($info['match_id']) ? (int)$info['match_id'] : 0,
            'type' => !empty($info['type']) ? (int)$info['type'] : 1
        ]);
    }

    /**
     * 获取一条用户信息
     * @param $fd
     * @return array|mixed|null
     */
    function get($fd)
    {

        $info = $this->table->get((int)$fd);
        return is_array($info) ? $info : null;
    }

    /**
     * 更新一条用户信息
     * @param $fd
     * @param $data
     */
    function update($fd, $data)
    {
        if ($info = $this->get((int)$fd)) {
            $info = $data + $info;
            $this->table->set($fd, $info);
        }
    }




    /**
     * 删除一条用户信息
     * @param $fd
     */
    function delete($fd)
    {
        $info = $this->get($fd);
        if ($info) {
            return $this->table->del($fd);
        }

        return false;
    }

    /**
     * 心跳检查
     * @param int $ttl
     */
    function heartbeatCheck($ttl = 60)
    {
        $server = ServerManager::getInstance()->getSwooleServer();
        foreach ($this->table as $item) {
            $connection = $server->connection_info($item['fd']);
            $time = $item['last_heartbeat'];
            if (!is_array($connection) || $connection['websocket_status'] != 3 || ($time + $ttl) < time()) {
                Log::getInstance()->info('heartbeatCheck-time-' . json_encode($item) .'-' . $ttl . '-' . time());
                if ($item['match_id']) {
                    AppFunc::userOutRoom($item['match_id'], $item['fd']);
                }
                $this->table->del($item['fd']);
            }
        }
    }

    /**
     * 心跳更新
     * @param $fd
     */
    function updateHeartbeat($fd)
    {
        $this->update($fd, [
            'last_heartbeat' => time()
        ]);
    }

    /**
     * 直接获取当前的表所有数据
     * @return Table|null
     */
    function table()
    {
        return $this->table;
    }


}