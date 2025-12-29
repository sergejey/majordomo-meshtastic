<?php
chdir(dirname(__FILE__) . '/../');
include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");
set_time_limit(0);
// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);
include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");
$ctl = new control_modules();

if (!file_exists(ROOT . "3rdparty/phpmqtt/phpMQTT.php")) {
    DebMes("MQTT library not found. Please make sure you have MQTT module installed.", 'meshtastic');
    exit;
}

include_once(ROOT . "3rdparty/phpmqtt/phpMQTT.php");
include_once(DIR_MODULES . 'meshtastic/meshtastic.class.php');


echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;

$meshtastic_module = new meshtastic();

while (1) {


    $meshtastic_module->getConfig();

    $host = $meshtastic_module->config['MQTT_HOST'];
    if (!$host) $host = 'localhost';

    if ($meshtastic_module->config['MQTT_PORT']) {
        $port = $meshtastic_module->config['MQTT_PORT'];
    } else {
        $port = 1883;
    }

    $username = $meshtastic_module->config['MQTT_USERNAME'];
    $password = $meshtastic_module->config['MQTT_PASSWORD'];

    if ($meshtastic_module->config['BASE_TOPIC']) {
        $query = $meshtastic_module->config['BASE_TOPIC'] . '/#';
        $query = str_replace('//', '/', $query);
    } else {
        $query = 'msh/#';
    }

    $client_name = "MJD_Receiver_" . time();
    $subscribed_topics = array();
    $mqtt_client = new Bluerhinos\phpMQTT($host, $port, $client_name);

    if ($meshtastic_module->config['MQTT_AUTH']) {
        DebMes("Connecting to $host:$port with username/password", 'meshtastic');
        $connect = $mqtt_client->connect(true, NULL, $username, $password);
    } else {
        DebMes("Connecting to $host:$port", 'meshtastic');
        $connect = $mqtt_client->connect();
    }
    if (!$connect) {
        DebMes("MQTT connection failed", 'meshtastic');
    } else {
        DebMes("MQTT connected OK", 'meshtastic');
        $latest_check = 0;
        $checkEvery = 5; // poll every 5 seconds
        $query_list = explode(',', $query);
        $total = count($query_list);
        DebMes("Topics to watch: $query (Total: $total)", 'meshtastic');
        echo date('H:i:s') . " Topics to watch: $query (Total: $total)\n";
        $topics = array();
        for ($i = 0; $i < $total; $i++) {
            $path = trim($query_list[$i]);
            echo date('H:i:s') . " Path: $path\n";
            $topics[$path] = array("qos" => 0, "function" => "proc_meshtastic_msg");
        }
        foreach ($topics as $k => $v) {
            echo date('H:i:s') . " Subscribing to: $k  \n";
            DebMes("Subscribing to: $k", 'meshtastic');
            $rec = array($k => $v);
            $mqtt_client->subscribe($rec, 0);
        }

        $previousMillis = 0;
        $check_subscriptions = 0;

        echo date('H:i:s') . " Entering processing loop\n";
        while ($mqtt_client->proc()) {

            $queue = checkOperationsQueue('meshtastic_queue');
            foreach ($queue as $mqtt_data) {
                $topic = $mqtt_data['DATANAME'];
                $data_value = $mqtt_data['DATAVALUE'];
                if ($topic != '' && $data_value != '') {
                    echo "Publishing to $topic : $data_value\n";
                    $result = $mqtt_client->publish($topic, $data_value);
                    if (!is_null($result) && !$result) {
                        DebMes("Error writing from queue '$value' to $topic", 'meshtatstic_error');
                    }
                }
            }

            $currentMillis = round(microtime(true) * 10000);
            if ($currentMillis - $previousMillis > 10000) {
                $previousMillis = $currentMillis;
                setGlobal((str_replace('.php', '', basename(__FILE__))) . 'Run', time(), 1);
                if (file_exists('./reboot') || isset($_GET['onetime'])) {
                    DebMes("Restart command received.", 'meshtastic');
                    $mqtt_client->close();
                    $db->Disconnect();
                    exit;
                }
            }
        }
    }

    sleep(10);
}

$mqtt_client->close();
DebMes("Unexpected close of cycle: " . basename(__FILE__));

function proc_meshtastic_msg($topic, $msg)
{
    //echo date('H:i:s') . " procmsg: $topic: $msg\n";
    global $meshtastic_module;
    global $latest_msg;

    $new_msg = time() . ' ' . $topic . ': ' . $msg;
    if ($latest_msg == $new_msg) return;

    if (function_exists('callAPI')) {
        callAPI('/api/module/meshtastic', 'POST', array('topic' => $topic, 'msg' => $msg));
    } else {
        $meshtastic_module->api(array('topic' => $topic, 'msg' => $msg));
    }
}
