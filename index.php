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

class CMCC
{
    // CMCC签到地址
    const CMCC_SIGNIN_URL = 'http://218.205.252.24:18081/scmccCampaign/signCalendar/sign.do';

    // CMCC抽奖地址
    const CMCC_LOTTERY_URL = 'http://218.205.252.24:18081/scmccCampaign/dazhuanpan/dzpDraw.do';

    // CMCC奖品信息地址
    const CMCC_PRIZEINFO_URL = 'http://218.205.252.24:18081/scmccCampaign/signCalendar/queryPrizeAndDrawStatus.do';

    // 签到兑奖地址
    const CMCC_GETPRIZE_URL = 'http://218.205.252.24:18081/scmccCampaign/signCalendar/draw.do';

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
     * @var CMCC
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
        $curl->post(static::CMCC_SIGNIN_URL, [
            'SSOCookie' => $SSOCookie,
        ]);

        if ($curl->error) {
            ServerChan::send($sendKey, $name . ' - Curl 错误 - 自动签到', "具体情况如下：\n\n" . $curl->errorCode . ' - ' . $curl->errorMessage);
            throw new \Exception('Curl 错误 - 自动签到 #' . $curl->errorCode . ' - ' . $curl->errorMessage . "\n");
        }

        /**
         * 解析签到接口返回值
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

        /**
         * 取得签到奖品信息以及当前已签天数
         */
        $curl->post(static::CMCC_PRIZEINFO_URL, [
            'SSOCookie' => $SSOCookie,
        ]);

        if ($curl->error) {
            ServerChan::send($sendKey, $name . ' - Curl 错误 - 获取签到奖品以及已签天数', "具体情况如下：\n\n" . $curl->errorCode . ' - ' . $curl->errorMessage);
            throw new \Exception('Curl 错误 - 获取签到奖品以及已签天数 #' . $curl->errorCode . ' - ' . $curl->errorMessage . "\n");
        }

        $message = '';
        $dayNum = $curl->response->result->obj->dayNum; // 已签天数
        foreach ($curl->response->result->obj->prizes as $prize) {
            if ($prize->DAYCOUNT <= $dayNum && $prize->DRAWSTATUS == 0 && strpos($prize->PRIZENAME, '爱奇艺') === false) { // 满足兑奖条件，忽略无用的爱奇艺流量
                /**
                 * 兑奖
                 */
                $curl->post(static::CMCC_GETPRIZE_URL, [
                    'SSOCookie' => $SSOCookie,
                    'type' => $prize->TYPE
                ]);

                if ($curl->error) {
                    system_log($name . 'Curl 错误 - 兑奖 #' . $curl->errorCode . ' - ' . $curl->errorMessage . "\n");
                } else {
                    switch ($curl->response->result->code) {
                        case 0:
                            // 兑奖成功
                            $message .= str_replace('|', '', $prize->PRIZENAME) . "流量\n\n";
                            break;
                        case 1:
                            system_log($name . ' - SSOCookie失效了，需要重新登录获取 兑奖出错 - ' . str_replace('|', '', $prize->PRIZENAME) . '流量');
                            break;
                        case 2:
                        case 5:
                            system_log($name . ' - 服务器繁忙 - 兑奖出错 - ' . str_replace('|', '', $prize->PRIZENAME) . '流量');
                            break;
                        case 3:
                            system_log($name . ' - 活动未开始或已结束 - 兑奖出错 - ' . str_replace('|', '', $prize->PRIZENAME) . '流量');
                            break;
                        case 4:
                            system_log($name . ' - 重复兑换该奖品 - 兑奖出错 - ' . str_replace('|', '', $prize->PRIZENAME) . '流量');
                            break;
                        case 6:
                            system_log($name . ' - 该奖品被抢完 - 兑奖出错 - ' . str_replace('|', '', $prize->PRIZENAME) . '流量');
                            break;
                        case 7:
                            system_log($name . ' - 未抽中，不予兑换 - 兑奖出错 - ' . str_replace('|', '', $prize->PRIZENAME) . '流量');
                            break;
                        default:
                            system_log($name . ' - 兑奖出错 - 未知错误');
                    }
                }

                sleep(1); // 防止操作过于频繁
            }
        }

        // 推送奖品兑换通知
        if ($message) {
            ServerChan::send($sendKey, '笨笨的机器人帮你兑换了奖品哦~', '在过去的' . $dayNum . "天里，笨笨的机器人每天都有帮你签到，功夫不负有心人，终于把辛勤的汗水换成奖品啦~\n\n现在笨笨的机器人帮你兑换了如下奖品：\n\n" . $message . "\n\n\n\n(๑¯◡¯๑)快查查看吧，哈哈~");
            system_log($name . " - 通过签到兑换奖品 - \n\n" . $message);
        }

        $curl->close();

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
        $curl->post(static::CMCC_LOTTERY_URL . '?t=' . mt_rand(), [
            'SSOCookie' => $SSOCookie,
        ]);

        if ($curl->error) {
            ServerChan::send($sendKey, $name . ' - Curl 错误 - 自动抽奖', "具体情况如下：\n\n" . $curl->errorCode . ' - ' . $curl->errorMessage);
            throw new \Exception('Curl 错误 - 自动抽奖 #' . $curl->errorCode . ' - ' . $curl->errorMessage . "\n");
        }

        $curl->close();

        /**
         * 解析抽奖接口返回值
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
    if (!IS_CLI && (!isset($_GET['key']) || $_GET['key'] != 20110901)) { // 防止蜘蛛或非本人触发
        throw new \Exception('非法触发。');
    }

    $userInfo = require __DIR__ . DS . 'config.php';
    foreach ($userInfo as $user) { // 多用户
        /**
         * 先签到
         */
        CMCC::instance()->autoSignIn(
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
        CMCC::instance()->autoLottery(
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