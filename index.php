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

    // 抽奖前刷新资格
    const CMCC_LOTTERY_INIT = 'http://wap.sc.10086.cn/scmccCampaign/dzpiteration/init.do';

    // CMCC抽奖地址
//    const CMCC_LOTTERY_URL = 'http://218.205.252.24:18081/scmccCampaign/dazhuanpan/dzpDraw.do';
    const CMCC_LOTTERY_URL = 'https://wap.sc.10086.cn/scmccCampaign/dzpiteration/dzpDraw.do';


    // CMCC分享地址
    const CMCC_SHARE_URL = 'https://wap.sc.10086.cn/scmccCampaign/dzpiteration/dzpshare.do';

    // CMCC奖品信息地址
    const CMCC_PRIZEINFO_URL = 'http://218.205.252.24:18081/scmccCampaign/signCalendar/queryPrizeAndDrawStatus.do';

    // 签到兑奖地址
    const CMCC_GETPRIZE_URL = 'http://218.205.252.24:18081/scmccCampaign/signCalendar/draw.do';

    // 用户流量情况
    const CMCC_FLOW_INFO = 'http://wap.sc.10086.cn/scmccClient/action.dox?';

    /**
     * @var array 奖品
     */
    public static $awards = [
        0 => '1G定向流量',
        1 => '100M国内流量',
        2 => '500M定向流量',
        3 => '200M国内流量',
        4 => '2G定向流量',
        5 => '移动大王卡',
        6 => '300M国内流量',
        7 => '谢谢参与'
    ];

    /**
     * @var CMCC
     */
    protected static $instance;

    /**
     * @var int curl超时秒数
     */
    protected static $timeOut = 20;

    /**
     * @var array 配置文件
     */
    protected static $config;

    /**
     * @var string auth cookie
     */
    public $SSOCookie;

    /**
     * @var string 城市
     */
    public $smsCityCookie;

    /**
     * @var int 登录时间
     */
    public $cstamp;

    /**
     * @var string 客户端标识
     */
    public $userAgent;

    /**
     * @var string 用户名
     */
    public $name;

    /**
     * @var string 用户通道
     */
    public $sendKey;

    /**
     * @var string 总流量
     */
    public $totalFlow = '未知';

    /**
     * @var string 剩余流量
     */
    public $remainFlow = 0;

    /**
     * @var string 已用流量
     */
    public $usedFlow = '未知';

    /**
     * @var string 距结算日天数
     */
    public $resetDayNum = '未知';

    /**
     * @var bool 是否已取得用户详情，防止重复获取用户信息
     */
    public $hasUserInfo = false;

    public function __construct()
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . DS . 'config.php';
        }
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
     * @return null
     * @throws ErrorException
     */
    public function autoSignIn()
    {
        $curl = new Curl();
        $curl->setUserAgent($this->userAgent);
        $curl->setReferrer('http://218.205.252.24:18081/scmccCampaign/signCalendar/index.html?SSOCookie=' . $this->SSOCookie . '&tt=110&SSCid=0e6b1897bb458c6701a74aab00db25b6b4beb775ae6d9d068d5292b63b6be3da07928386636897575cc6b0f5a503372cf8f1778776a6602c&abStr=8651');
        $curl->setHeaders([
            'Accept' => 'application/json, text/javascript, */*',
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);
        $curl->setCookies([
            'SmsNoPwdLoginCookie' => $this->SSOCookie,
            'smsCityCookie' => $this->smsCityCookie,
            'cstamp' => $this->cstamp
        ]);
        $curl->setTimeout(static::$timeOut);
        $curl->post(static::CMCC_SIGNIN_URL, [
            'SSOCookie' => $this->SSOCookie,
        ]);

        if ($curl->error) {
            ServerChan::send(self::$config['errorReportSendKey'], sprintf('报告，在为%s自动签到时，Curl出错', $this->name), "详情：\n" . $curl->errorMessage);

            return false;
        }

        /**
         * 解析签到接口返回值
         */
        $error = '';
        $data = $curl->response->result;
        switch ($data->code) {
            case 0:
                // 签到成功
                system_log($this->name . '，签到成功', 'LOG');
                break;
            case 1:
                system_log($error = sprintf('报告，%s的SSOCookie失效了，需要重新登录获取', $this->name), 'WARNING');
                break;
            case 3:
                system_log($error = sprintf('报告，在为%s自动签到时，发现活动未开始或已结束', $this->name));
                break;
            case 2:
                system_log($error = sprintf('报告，%s重复签到，请检查程序重复执行的原因', $this->name));
                break;
            default:
                system_log($error = sprintf('报告，在为%s自动签到时出现未知错误', $this->name));
        }

        // 签到不成功，推送到微信
        if ($error) {
            ServerChan::send(self::$config['errorReportSendKey'], $error, "详情：\n" . ($data->info ?: '暂无详情，如题所述。'));
        }

        /**
         * 取得签到奖品信息以及当前已签天数
         */
        $curl->post(static::CMCC_PRIZEINFO_URL, [
            'SSOCookie' => $this->SSOCookie,
        ]);

        if ($curl->error) {
            ServerChan::send(self::$config['errorReportSendKey'], sprintf('在为%s获取签到奖品以及已签天数时，Curl出错', $this->name), "详情：\n" . $curl->errorMessage);

            return false;
        }

        $prizeRes = '';
        $obtainFlows = 0; // 兑奖取得流量总计
        $dayNum = $curl->response->result->obj->dayNum; // 已签天数
        foreach ($curl->response->result->obj->prizes as $prize) {
            if ($prize->DAYCOUNT <= $dayNum && $prize->DRAWSTATUS == 0 && strpos($prize->PRIZENAME, '视频') === false) { // 满足兑奖条件，忽略无用的定向流量
                /**
                 * 兑奖
                 */
                $curl->post(static::CMCC_GETPRIZE_URL, [
                    'SSOCookie' => $this->SSOCookie,
                    'type' => $prize->TYPE
                ]);

                if ($curl->error) {
                    system_log($this->name . 'Curl 错误 - 兑奖 - ' . $curl->errorMessage);
                } else {
                    $currPrize = str_replace('|', '', $prize->PRIZENAME) . '流量';
                    switch ($curl->response->result->code) {
                        case 0:
                            // 兑奖成功
                            if (preg_match('/\d+/', $prize->PRIZENAME, $obtainFlow)) {
                                $obtainFlows += $obtainFlow[1];
                            }
                            $prizeRes .= str_replace('|', '', $prize->PRIZENAME) . '流量，';
                            break;
                        case 1:
                            system_log($this->name . ' - SSOCookie失效了，需要重新登录获取 兑奖出错 - ' . $currPrize);
                            break;
                        case 2:
                        case 5:
                            system_log($this->name . ' - 服务器繁忙 - 兑奖出错 - ' . $currPrize);
                            break;
                        case 3:
                            system_log($this->name . ' - 活动未开始或已结束 - 兑奖出错 - ' . $currPrize);
                            break;
                        case 4:
                            system_log($this->name . ' - 重复兑换该奖品 - 兑奖出错 - ' . $currPrize);
                            break;
                        case 6:
                            system_log($this->name . ' - 该奖品被抢完 - 兑奖出错 - ' . $currPrize);
                            break;
                        case 7:
                            system_log($this->name . ' - 未抽中，不予兑换 - 兑奖出错 - ' . $currPrize);
                            break;
                        default:
                            system_log($this->name . ' - 兑奖出错 - 未知错误 - ' . $currPrize);
                    }
                }

                sleep(1); // 防止操作过于频繁
            }
        }

        // 推送奖品兑换通知
        if ($prizeRes) {
            system_log($this->name . " - 通过签到兑换奖品 - " . $prizeRes . '喜大普奔。');

            $this->hasUserInfo || $this->getFlowInfo($curl);
            $this->remainFlow += $obtainFlows;
            $isRemainFlow = $this->formatFlow($this->remainFlow);

            ServerChan::send(
                $this->sendKey,
                sprintf('%s，机器人为你兑换了：%s当前可用流量为：%s', $this->name, $prizeRes, $isRemainFlow),
                sprintf(
                    "详情：\n机器人替你签到**%s**天，**兑换了这些奖品：%s**have fun。\n另外\n#### 本月已用流量为：%s\n#### 剩余可用国内总流量为：%s\n距结算日%s天。流量可能不会马上到账，请在2小时后查询流量情况。\n\n流量机器人敬上",
                    $dayNum,
                    $prizeRes,
                    $this->usedFlow,
                    $isRemainFlow,
                    $this->resetDayNum
                )
            );
        }

        $response = $curl->response;
        $curl->close();

        return $response;
    }

    /**
     * 获取流量使用情况
     * @param object $curl
     * @return array|mixed
     * @throws ErrorException
     */
    public function getFlowInfo($curl)
    {
        $curl->setOpts([
            CURLOPT_HTTPHEADER => [ // 设置 HTTP 头字段的数组。格式： array('Content-type: text/plain', 'Content-length: 100')，方便直接从fiddler中复制header信息
                'Accept: */*',
                'version: 3.5.0',
                'channel: AppStore',
                'Connection: keep-alive',
                'Accept-Language: zh-Hans-CN;q=1, ja-JP;q=0.9',
//                'Accept-Encoding: gzip, deflate', // 若声明此项，返回的数据也是gzip压缩的，故不声明
                'platform: iphone',
                'Content-Type: application/x-www-form-urlencoded',
                'language: cn',
                'User-Agent: SiChuan/3.5.0 (iPhone; iOS 12.0; Scale/3.00)',
//                'Cookie: JSESSIONID=vniRU4y02GnBQPsggFTWK7iRBwVIYYI1iVkQAh06lhbEvrLmyAY3!-815831308; SmsNoPwdLoginCookie=xxx; cstamp=xxx; smsCityCookie=27 ' // 注意每个字段后是英文分号加空格，最后以一个空格结尾
            ],
//            CURLOPT_POSTFIELDS => 'auth=yes&appKey=00011&md5sign=6323D2BDABFF7D733A645D760F1F924B&internet=WiFi&sys_version=12.0&screen=1125*2001&model=iPhone&imei=xxx&deviceid=xxx&version=3.5.0&msgId=&jsonParam=%5B%7B%22dynamicURI%22:%22/queryFlow%22,%22dynamicParameter%22:%7B%22method%22:%22queryFlowLlzqNew%22%7D,%22dynamicDataNodeName%22:%20%22queryFlowLlzqNew_node%22%7D%5D', // Send raw data
        ]);
        $curl->post(self::CMCC_FLOW_INFO, [ // 此参数会覆盖CURLOPT_POSTFIELDS的值，不传此参数的话，CURLOPT_POSTFIELDS的值会被置为空
            'auth' => 'yes',
            'appKey' => '00011',
            'md5sign' => '6323D2BDABFF7D733A645D760F1F924B',
            'internet' => 'WiFi',
            'sys_version' => '12.0',
            'screen' => '1125*2001',
            'model' => 'iPhone',
            'imei' => self::$config['imei'],
            'deviceid' => self::$config['imei'],
            'version' => '3.5.0',
            'msgId' => '',
            'jsonParam' => '[{"dynamicURI":"/queryFlow","dynamicParameter":{"method":"queryFlowLlzqNew"},"dynamicDataNodeName": "queryFlowLlzqNew_node"}]',
        ]);

        if ($curl->error) {
            ServerChan::send(self::$config['errorReportSendKey'], sprintf('在获取%s的流量情况时，Curl出错', $this->name), "详情：\n" . $curl->errorMessage);

            return [];
        }

        $rt = $curl->response ? json_decode($curl->response, true) : [];

        if (isset($rt['queryFlowLlzqNew_node']['resultObj']['FlowListAllList'][0])) {
            $info = $rt['queryFlowLlzqNew_node']['resultObj']['FlowListAllList'][0];
            $this->totalFlow = $this->formatFlow($info['CurFlowTotal']);
            $this->remainFlow = $info['CurFlowRemain']; // 剩余流量可能有变，暂不格式化
            $this->usedFlow = $this->formatFlow($info['CurFlowUsed']);
            if (isset($rt['queryFlowLlzqNew_node']['resultObj']['JJSR'])) {
                $this->resetDayNum = $rt['queryFlowLlzqNew_node']['resultObj']['JJSR'];
            }

            // 防止重复获取用户信息
            $this->hasUserInfo = true;

            return $rt['queryFlowLlzqNew_node']['resultObj'];
        }

        return [];
    }

    /**
     * 格式化流量值
     * @param $orgValue
     * @return string
     */
    private function formatFlow($orgValue)
    {
        $orgValue = floor($orgValue);
        if ($orgValue >= 1024) {
            $rtVal = round($orgValue / 1024, 2) . 'G';
        } else {
            $rtVal = $orgValue . 'M';
        }

        return $rtVal;
    }

    /**
     * 自动抽奖
     * @return bool|null
     * @throws ErrorException
     */
    public function autoLottery()
    {
        $curl = new Curl();
        $curl->setUserAgent($this->userAgent);
        $curl->setReferrer('https://wap.sc.10086.cn/scmccCampaign/dzpiteration/index.html?abStr=8651');
        $curl->setHeaders([
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ]);
        $curl->setCookies([
            'SmsNoPwdLoginCookie' => $this->SSOCookie,
            'smsCityCookie' => $this->smsCityCookie,
            'cstamp' => $this->cstamp
        ]);
        $curl->setTimeout(static::$timeOut);

        // 抽奖前先刷新抽奖资格
        $curl->post(self::CMCC_LOTTERY_INIT, [
            'SSOCookie' => $this->SSOCookie,
        ]);

        $shared = false;
        while (true) {
            $curl->post(static::CMCC_LOTTERY_URL, [
                'SSOCookie' => $this->SSOCookie,
                'channel' => '',
            ]);

            if ($curl->error) {
                ServerChan::send(self::$config['errorReportSendKey'], sprintf('在为%s自动抽奖时，Curl出错', $this->name), "详情：\n" . $curl->errorMessage);

                return false;
            }

            /**
             * 处理抽奖结果
             */
            $this->handleLotteryRt($curl);

            sleep(1);

            if (!$shared) { // 每天首次分享可再获一次抽奖机会
                $curl->post(static::CMCC_SHARE_URL, [
                    'SSOCookie' => $this->SSOCookie,
                ]);
                $shared = true;

                sleep(30); // 防止操作频繁
            } else {
                break;
            }
        }

        $curl->close();

        return true;
    }

    /**
     * 处理抽奖结果
     * @param $curl
     * @throws ErrorException
     */
    private function handleLotteryRt($curl)
    {
        $rt = $curl->response->result;
        $code = (int)$rt->code;
        $obj = $rt->obj;
        if ($code === 0) { // 抽奖成功
            // 取得奖品名称
            $awardKey = isset($obj->si) ? $obj->si : '';
            if ($awardKey == 99) {
                $awardKey = 7; // 谢谢惠顾
            } else if ($awardKey == 20) {
                $awardKey = 5; // 大王卡
            }
            $awardName = isset(static::$awards[$awardKey]) ? static::$awards[$awardKey] : '未知奖品';

            if (!in_array($awardKey, [1, 3, 6])) { // 抽中了没得任何卵用东西
                system_log($this->name . ' - 同没中奖，去你喵的没有任何卵用的' . $awardName . ' - ' . $rt->info, 'LOG');
            } else {
                system_log($this->name . ' - 抽中' . $awardName . ' - ' . $rt->info, 'LOG');

                $this->hasUserInfo || $this->getFlowInfo($curl);

                if (preg_match('/\d+/', $awardName, $obtainFlow)) { // 流量不会实时更新，故先加上以确保数据准确
                    $this->remainFlow += intval($obtainFlow[0]);
                }
                $isRemainFlow = $this->formatFlow($this->remainFlow);

                // 推送到微信
                ServerChan::send(
                    $this->sendKey,
                    sprintf('%s，恭喜你抽中%s，目前可用流量为：%s', $this->name, $awardName, $isRemainFlow),
                    sprintf(
                        "详情：\n抽中%s，另外\n#### 本月已用流量为：%s\n#### 剩余可用国内总流量为：%s\n距结算日%s天。流量可能不会马上到账，请在2小时后查询流量情况。\n\n流量机器人敬上",
                        $awardName,
                        $this->usedFlow,
                        $isRemainFlow,
                        $this->resetDayNum
                    )
                );
            }
        } else {
            switch ($code) {
                case 1:
                    system_log($this->name . ' - SSOCookie失效了，需要重新登录获取 - ' . $rt->info, 'WARNING');
                    break;
                case 3:
                    system_log($this->name . ' - 活动未开始或已结束 - ' . $rt->info, 'WARNING');
                    break;
                case 4:
                    system_log($this->name . ' - 今天已经参加过抽奖活动 - ' . $rt->info, 'LOG');
                    break;
                case 5:
                    system_log($this->name . ' - 请求频繁 - ' . $rt->info, 'LOG');
                    break;
                default:
                    system_log($this->name . ' - 日常不中奖 - ' . $rt->info, 'LOG');
            }
        }
    }

    public function getConfig()
    {
        return self::$config;
    }
}

try {
    if (!IS_CLI && (!isset($_GET['key']) || $_GET['key'] != 20110901)) { // 防止蜘蛛或非本人触发
        throw new \Exception('非法触发。');
    }

    $config = CMCC::instance()->getConfig();
    foreach ($config['users'] as $user) { // 多用户
        /**
         * 先赋值
         */
        CMCC::instance()->SSOCookie = $user['SSOCookie'];
        CMCC::instance()->smsCityCookie = $user['smsCityCookie'];
        CMCC::instance()->cstamp = $user['cstamp'];
        CMCC::instance()->userAgent = $user['userAgent'];
        CMCC::instance()->name = $user['name'];
        CMCC::instance()->sendKey = $user['sendKey'];
        CMCC::instance()->hasUserInfo = false;
        CMCC::instance()->remainFlow = 0;

        /**
         * 接着签到
         */
        CMCC::instance()->autoSignIn();

        sleep(3);

        /**
         * 再抽奖
         */
        CMCC::instance()->autoLottery();
    }

    echo '执行成功。';
} catch (\Exception $e) {
    system_log($e->getMessage());
}