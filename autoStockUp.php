<?php
/**
 *  对于物流类型是郑州保税仓、保税仓发货 和香港直邮的商家付款成功30分钟之后调整为备货中
 *
 * @author pengxuhui(pengxuhui@hichao.com)
 * @version 1.0
 */

require(dirname(__FILE__).DIRECTORY_SEPARATOR.'init.php');

function startProcess($db)
{
    $businessComfirmUrl = Yii::app()->params['orderUrl'] . "update/order/business-confirm-order";
    $time = time();
    $formatTime = date('Y-m-d H:i:s', $time);
    try {
        //查找订单
        $payTime = $time - 1800;
        $sql = "SELECT b.business_id,o.order_sn from ".get_tables('business_info')." as b left join ".get_tables('order_info').
                        " as o on b.business_id=o.business_id WHERE b.express_type in (2, 11, 12) and o.order_status=1 and o.express_type > 0  and o.pay_time <= ".$payTime. " LIMIT 100";
        $data = $db->createCommand($sql)->queryAll();

        if(!empty($data)) {
            foreach ($data as $k => $v) {
                $post = [
                    'business_id' => $v['business_id'],
                    'order_sn' => $v['order_sn'],
                    'user_id' => 1,
                    'user_name' => 'jiaoben',
                    'source' => 'autoStockUp',
                ];

                $return = curlPost($businessComfirmUrl, $post);

                if($return['code'] != 0) {
                    addLog("data:" . json_encode($return, JSON_UNESCAPED_UNICODE) . '|time:' . $formatTime);
                    echo $v['order_sn']."：fail"."\r\n";
                }else{
                    echo $v['order_sn']."：success"."\r\n";
                }
            }

            echo "data update:".count($data)."\r\n";
        }else{
            echo "no data \r\n";
        }

        echo 'finish';
    } catch (Exception $e) {
        $msg = "filename:autoStockUp|functionname:start_process|error:". $e->getMessage()."|time:".$formatTime;
        echo $msg."\r\n";
        addLog($msg);
    }
}

function addLog($msg) {
    $msg = $msg . "\r\n";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {//win环境下直接输出
        echo $msg;
    } else {//linux环境写入日志
        $dir = "/data/logs/autoStockUp/";
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        file_put_contents($dir . date("Y-m") . '.log', $msg, FILE_APPEND);
    }
}

/**
 * Send a POST requst using cURL
 * @param string $url to request
 * @param array $post values to send
 * @param array $options for cURL
 * @return string
 */
function curlPost($url, $post = null, array $options = array())
{
    $defaults = array(
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 40,
            CURLOPT_POSTFIELDS => $post
    );
    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));
    if(!$result = curl_exec($ch)) {
            trigger_error(curl_error($ch));
    }
    curl_close($ch);
    return json_decode($result, true);
}

$db = Yii::app()->sdb;
startProcess($db);
