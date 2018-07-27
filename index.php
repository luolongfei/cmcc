<?php
/**
 * 四川移动掌上营业厅自动任务
 * @author mybsdc <mybsdc@gmail.com>
 * @date 2018/7/25
 * @time 13:40
 */

error_reporting(E_ERROR);
ini_set('display_errors', 1);

define('IS_CLI', PHP_SAPI === 'cli' ? true : false);
define('DS', DIRECTORY_SEPARATOR);
define('VENDOR_PATH', realpath('vendor') . DS);

date_default_timezone_set('Asia/Shanghai');

// Server酱微信推送url
define('SC_URL', 'https://pushbear.ftqq.com/sub');

// CMCC签到地址
define('CMCC_SIGNIN_URL', 'http://218.205.252.24:18081/scmccCampaign/signCalendar/sign.do');

// CMCC抽奖地址
define('CMCC_LOTTERY_URL', 'http://218.205.252.24:18081/scmccCampaign/dazhuanpan/dzpDraw.do');

/**
 * 定制错误处理
 */
register_shutdown_function('customize_error_handler');
function customize_error_handler()
{
    if (!is_null($error = error_get_last())) {
        system_log($error);

        $response = [
            'STATUS' => 9,
            'MESSAGE_ARRAY' => array(
                array(
                    'MESSAGE' => '程序执行出错，请稍后再试。'
                )
            ),
            'SYSTEM_DATE' => date('Y-m-d H:i:s')
        ];

        header('Content-Type: application/json');

        echo json_encode($response);
    }
}

/**
 * 记录程序日志
 * @param array|string $logContent 日志内容
 * @param string $mark LOG | ERROR | WARNING 日志标志
 */
function system_log($logContent, $mark = 'ERROR')
{
    try {
        $logPath = __DIR__ . '/logs/' . date('Y') . '/' . date('m') . '/';
        $logFile = $logPath . date('d') . '.php';

        if (!is_dir($logPath)) {
            mkdir($logPath, 0777, true);
            chmod($logPath, 0777);
        }

        $handle = fopen($logFile, 'a'); // 文件不存在则自动创建

        if (!filesize($logFile)) {
            fwrite($handle, "<?php defined('VENDOR_PATH') or die('No direct script access allowed.'); ?>" . PHP_EOL . PHP_EOL);
            chmod($logFile, 0666);
        }

        fwrite($handle, $mark . ' - ' . date('Y-m-d H:i:s') . ' --> ' . (IS_CLI ? 'CLI' : 'URI: ' . $_SERVER['REQUEST_URI'] . PHP_EOL . 'REMOTE_ADDR: ' . $_SERVER['REMOTE_ADDR'] . PHP_EOL . 'SERVER_ADDR: ' . $_SERVER['SERVER_ADDR']) . PHP_EOL . (is_string($logContent) ? $logContent : var_export($logContent, true)) . PHP_EOL); // CLI模式下，$_SERVER中几乎无可用值

        fclose($handle);
    } catch (\Exception $e) {
        // DO NOTHING
    }
}

require VENDOR_PATH . 'autoload.php';
require __DIR__ . DS . 'serverchan.php';

use Curl\Curl;

class SignIn
{
    /**
     * @var array 奖品
     */
    public static $awards = [
        1 => '100M流量',
        2 => '200M流量',
        3 => '300M流量',
        4 => '200M爱奇艺流量',
        5 => '300M爱奇艺流量',
        6 => '500M爱奇艺流量',
        7 => '谢谢参与',
        8 => '谢谢参与'
    ];

    /**
     * @var array 用户信息
     */
    public $userInfo = [
        [ // mom
            'name' => '老妈',
            'sendKey' => '2885-XXX', // 微信推送通道
            'SSOCookie' => 'XXX', // 令牌
            'smsCityCookie' => 11, // 城市代码
            'cstamp' => 1532404831157, // 登录时间
            'userAgent' => 'Mozilla/5.0 (Linux; Android 5.1; m2 Build/LMY47D; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/44.0.2403.147 Mobile Safari/537.36/3.4.4 scmcc.mobile' // 客户端
        ],
        [ // me
            'name' => '罗叔叔',
            'sendKey' => '2885-XXX', // 微信推送通道
            'SSOCookie' => 'XXX', // 令牌
            'smsCityCookie' => 27, // 城市代码
            'cstamp' => 1532404831157, // 登录时间
            'userAgent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15G77/3.4.4 scmcc.mobile' // 客户端
        ],
    ];

    /**
     * @var SignIn
     */
    protected static $instance;

    /**
     * @var int curl超时秒数
     */
    protected static $timeOut = 20;

    public function __construct()
    {
    }

    public static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * 自动签到
     * @param string $SSOCookie
     * @param int $smsCityCookie
     * @param int $cstamp
     * @param string $userAgent
     * @param string $name
     * @param string $sendKey 通道key
     * @return object|null
     * @throws \ErrorException
     * @throws \Exception
     */
    public function autoSignIn($SSOCookie, $smsCityCookie, $cstamp, $userAgent, $name, $sendKey)
    {
        $curl = new Curl();
        $curl->setUserAgent($userAgent);
        $curl->setReferrer('http://218.205.252.24:18081/scmccCampaign/signCalendar/index.html?SSOCookie=' . $SSOCookie . '&tt=110&SSCid=0e6b1897bb458c6701a74aab00db25b6b4beb775ae6d9d068d5292b63b6be3da07928386636897575cc6b0f5a503372cf8f1778776a6602c&abStr=8651');
        $curl->setHeaders([
            'Accept' => 'application/json, text/javascript, */*',
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);
        $curl->setCookies([
            'SmsNoPwdLoginCookie' => $SSOCookie,
            'smsCityCookie' => $smsCityCookie,
            'cstamp' => $cstamp
        ]);
        $curl->setTimeout(static::$timeOut);
        $curl->post(CMCC_SIGNIN_URL, [
            'SSOCookie' => $SSOCookie,
        ]);

        if ($curl->error) {
            ServerChan::send($sendKey, $name . ' - Curl 错误 - 自动签到', "具体情况如下：\n\n" . $curl->errorCode . ' - ' . $curl->errorMessage);
            throw new \Exception('Curl 错误 - 自动签到 #' . $curl->errorCode . ' - ' . $curl->errorMessage . "\n");
        }

        $curl->close();

        /**
         * 解析接口返回值
         */
        $error = '';
        $data = $curl->response->result;
        switch ($data->code) {
            case 0:
                // 签到成功
                system_log($name . ' - 签到成功 #' . $data->code, 'LOG');
                break;
            case 1:
                system_log($name . ' - SSOCookie失效了，需要重新登录获取 #' . $data->code . ' - ' . $data->info, 'WARNING');
                $error = $name . ' - SSOCookie失效了，需要重新登录获取';
                break;
            case 3:
                system_log($name . ' - 活动未开始或已结束 #' . $data->code . ' - ' . $data->info, 'LOG');
                $error = $name . ' - 活动未开始或已结束';
                break;
            case 2:
                system_log($name . ' - 重复签到，不算哦~ #' . $data->code);
                $error = $name . ' - 重复签到，不算哦~';
                break;
            default:
                system_log($name . ' - 未知错误 #' . $data->code . ' - ' . $data->info, 'WARNING');
                $error = $name . ' - 未知错误';
        }

        // 签到不成功，推送到微信
        if ($error) {
            ServerChan::send($sendKey, $error, "具体情况如下：\n\nerror code: " . $data->code . "\n\n" . ($data->info ?: 'ʅ（=ˇωˇ=）ʃ如题'));
        }

        return $curl->response;
    }

    /**
     * 自动抽奖
     * @param string $SSOCookie
     * @param int $smsCityCookie
     * @param int $cstamp
     * @param string $userAgent
     * @param string $name
     * @param string $sendKey 通道key
     * @return object|null
     * @throws \ErrorException
     * @throws \Exception
     */
    public function autoLottery($SSOCookie, $smsCityCookie, $cstamp, $userAgent, $name, $sendKey)
    {
        $curl = new Curl();
        $curl->setUserAgent($userAgent);
        $curl->setReferrer('http://218.205.252.24:18081/scmccCampaign/dazhuanpan/index.html?SSOCookie=' . $SSOCookie . '&value=isNeedLogin&tt=1&SSCid=0e6b1897bb458c6701a74aab00db25b6b4beb775ae6d9d068d5292b63b6be3da07928386636897575cc6b0f5a503372cf8f1778776a6602c&abStr=8651');
        $curl->setHeaders([
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ]);
        $curl->setCookies([
            'SmsNoPwdLoginCookie' => $SSOCookie,
            'smsCityCookie' => $smsCityCookie,
            'cstamp' => $cstamp
        ]);
        $curl->setTimeout(static::$timeOut);
        $curl->post(CMCC_LOTTERY_URL . '?t=' . mt_rand(), [
            'SSOCookie' => $SSOCookie,
        ]);

        if ($curl->error) {
            ServerChan::send($sendKey, $name . ' - Curl 错误 - 自动抽奖', "具体情况如下：\n\n" . $curl->errorCode . ' - ' . $curl->errorMessage);
            throw new \Exception('Curl 错误 - 自动抽奖 #' . $curl->errorCode . ' - ' . $curl->errorMessage . "\n");
        }

        $curl->close();

        /**
         * 解析接口返回值
         */
        $data = $curl->response->dzpDraw;
        if ($data->obj != null) { // 中奖的情况
            // 取得奖品名称
            $awardKey = $data->obj % 7;
            $awardName = isset(static::$awards[$awardKey]) ? static::$awards[$awardKey] : '未知奖品';

            if (strpos($awardName, '爱奇艺') !== false) { // 抽中了没得任何卵用的爱奇艺流量
                system_log($name . ' - 同没中奖，去你喵的没有任何卵用的' . $awardName . ' #' . $data->code . ' - ' . $data->obj . ' - ' . $data->info, 'LOG');
            } else {
                system_log($name . ' - 恭喜你抽中' . $awardName . '，发财了发财了~ #' . $data->code . ' - ' . $data->obj . ' - ' . $data->info, 'LOG');

                // 推送到微信
                ServerChan::send($sendKey, $name . ' - 恭喜你抽中' . $awardName . '，发财了发财了~', "具体情况如下：\n\ncode: " . $data->code . "\n\n" . ($data->info ?: 'ʅ（=ˇωˇ=）ʃ如题'));
            }
        } else {
            switch ($data->code) {
                case 1:
                    system_log($name . ' - SSOCookie失效了，需要重新登录获取 #' . $data->code . ' - ' . $data->info, 'WARNING');
                    break;
                case 13:
                    system_log($name . ' - 网络异常 #' . $data->code . ' - ' . $data->info, 'WARNING');
                    break;
                case 4:
                    system_log($name . ' - 今天已经参加过抽奖活动 #' . $data->code . ' - ' . $data->info, 'LOG');
                    break;
                default:
                    system_log($name . ' - 日常不中奖 #' . $data->code . ' - ' . $data->info, 'LOG');
            }
        }

        return $curl->response;
    }
}

try {
    foreach (SignIn::instance()->userInfo as $user) { // 多用户
        /**
         * 先签到
         */
        SignIn::instance()->autoSignIn(
            $user['SSOCookie'],
            $user['smsCityCookie'],
            $user['cstamp'],
            $user['userAgent'],
            $user['name'],
            $user['sendKey']
        );

        sleep(3);

        /**
         * 再抽奖
         */
        SignIn::instance()->autoLottery(
            $user['SSOCookie'],
            $user['smsCityCookie'],
            $user['cstamp'],
            $user['userAgent'],
            $user['name'],
            $user['sendKey']
        );
    }

    echo '触发成功。';
} catch (\Exception $e) {
    system_log($e->getMessage());
}