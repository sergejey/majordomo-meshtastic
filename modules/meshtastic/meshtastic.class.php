<?php
/**
 * Meshtastic
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 19:12:42 [Dec 14, 2025])
 */
//
//
class meshtastic extends module
{
    /**
     * meshtastic
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "meshtastic";
        $this->title = "Meshtastic";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (isset($this->id)) {
            $p["id"] = $this->id;
        }
        if (isset($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (isset($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (isset($this->data_source)) {
            $p["data_source"] = $this->data_source;
        }
        if (isset($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $data_source;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($data_source)) {
            $this->data_source = $data_source;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (isset($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (isset($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['DATA_SOURCE'] = $this->data_source;
        $out['TAB'] = $this->tab;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {

        $ok_msg = gr('ok_msg');
        if ($ok_msg != '') {
            $out['OK_MSG'] = $ok_msg;
        }

        $this->getConfig();
        $out['BASE_TOPIC'] = $this->config['BASE_TOPIC'];
        $out['MQTT_HOST'] = $this->config['MQTT_HOST'];
        if (!$out['MQTT_HOST']) {
            $out['MQTT_HOST'] = 'localhost';
        }
        $out['MQTT_PORT'] = $this->config['MQTT_PORT'];
        if (!$out['MQTT_PORT']) {
            $out['MQTT_PORT'] = 1883;
        }
        $out['MQTT_USERNAME'] = $this->config['MQTT_USERNAME'];
        $out['MQTT_PASSWORD'] = $this->config['MQTT_PASSWORD'];
        $out['MQTT_AUTH'] = $this->config['MQTT_AUTH'];
        if ($this->view_mode == 'update_settings') {
            $this->config['BASE_TOPIC'] = gr('base_topic');
            $this->config['MQTT_HOST'] = gr('mqtt_host');
            $this->config['MQTT_AUTH'] = gr('mqtt_auth', 'int');
            $this->config['MQTT_USERNAME'] = gr('mqtt_username');
            $this->config['MQTT_PASSWORD'] = gr('mqtt_password');
            $this->config['MQTT_PORT'] = gr('mqtt_port', 'int');
            $this->saveConfig();
            setGlobal('cycle_meshtastic', 'restart');
            $this->redirect("?");
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'mesh_devices' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_mesh_devices') {
                $this->search_mesh_devices($out);
            }
            if ($this->view_mode == 'edit_mesh_devices') {
                $this->edit_mesh_devices($out, $this->id);
            }
            if ($this->view_mode == 'delete_mesh_devices') {
                $this->delete_mesh_devices($this->id);
                $this->redirect("?data_source=mesh_devices");
            }
        }
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'mesh_properties') {
            if ($this->view_mode == '' || $this->view_mode == 'search_mesh_properties') {
                $this->search_mesh_properties($out);
            }
            if ($this->view_mode == 'edit_mesh_properties') {
                $this->edit_mesh_properties($out, $this->id);
            }
        }

        if ($this->view_mode == 'edit_mesh_channels') {
            $this->edit_mesh_channels($out, $this->id);
        }

    }

    function edit_mesh_channels(&$out, $id)
    {
        require(dirname(__FILE__) . '/mesh_channels_edit.inc.php');
    }

    function api($params)
    {
        //DebMes("API: " . json_encode($params), 'meshtastic_mqtt');
        $topic = $params['topic'];
        $msg = $params['msg'];
        $data = json_decode($msg, true);
        if (is_array($data) && isset($data['from'])) {
            $data['topic'] = $topic;
            $this->processDeviceMessage($data['from'], $data);
        }
        if (preg_match('/\!(\w+)$/', $topic, $m)) {
            $gateway_uid = $m[1];
            SQLExec("UPDATE mesh_devices SET IS_BASE=1 WHERE IS_BASE=0 AND UID='" . $gateway_uid . "'");
            SQLExec("UPDATE mesh_devices SET IS_BASE=0 WHERE IS_BASE=1 AND UID!='" . $gateway_uid . "'");
        }
    }

    function processDeviceMessage($device_uid, $data, $skip_adding = false)
    {
        $uid = dechex($device_uid);
        $device_rec = SQLSelectOne("SELECT * FROM mesh_devices WHERE UID='" . $uid . "'");

        if (!$skip_adding) {
            if (!$device_rec['ID']) {
                $device_rec['UID'] = $uid;
                $device_rec['TITLE'] = $device_rec['UID'];
                $device_rec['UPDATED'] = date('Y-m-d H:i:s');
                $device_rec['ID'] = SQLInsert('mesh_devices', $device_rec);
            } else {
                $device_rec['UPDATED'] = date('Y-m-d H:i:s');
                SQLUpdate('mesh_devices', $device_rec);
            }

            $msg = array('MESH_DEVICE_ID' => $device_rec['ID'],
                'MESSAGE_TYPE' => $data['type'],
                'PAYLOAD' => json_encode($data, JSON_PRETTY_PRINT),
                'ADDED' => date('Y-m-d H:i:s'));
            SQLInsert('mesh_messages', $msg);
        } elseif (!isset($device_rec['ID'])) {
            return false;
        }

        if (preg_match('/json\/(.+?)\//', $data['topic'], $m)) {
            $channel = $m[1];
            if ($channel != 'PKI') {
                $channel_rec = SQLSelectOne("SELECT * FROM mesh_channels WHERE CHANNEL_NAME='" . DBSafe($channel) . "'");
                if (isset($data['channel'])) {
                    $channel_rec['CHANNEL_NUM'] = (int)$data['channel'];
                }
                if (!isset($channel_rec['ID'])) {
                    $channel_rec['CHANNEL_NAME'] = $channel;
                    $channel_rec['UPDATED'] = date('Y-m-d H:i:s');
                    $channel_rec['ID'] = SQLInsert('mesh_channels', $channel_rec);
                } else {
                    $channel_rec['UPDATED'] = date('Y-m-d H:i:s');
                    SQLUpdate('mesh_channels', $channel_rec);
                }
            }
        }

        if ($data['type'] == 'position') {
            $device_rec['LATITUDE'] = $data['payload']['latitude_i'] / 10000000;
            $device_rec['LONGITUDE'] = $data['payload']['longitude_i'] / 10000000;
            SQLUpdate('mesh_devices', $device_rec);
            $this->processDeviceProperty($device_rec['ID'], 'latitude', $data['payload']['latitude_i'] / 10000000);
            $this->processDeviceProperty($device_rec['ID'], 'longitude', $data['payload']['longitude_i'] / 10000000);

            $additional_attributes = array('sats_in_view', 'altitude', 'ground_speed', 'ground_track');
            foreach ($additional_attributes as $additional_attribute) {
                if (isset($data['payload'][$additional_attribute])) {
                    $this->processDeviceProperty($device_rec['ID'], $additional_attribute, $data['payload'][$additional_attribute]);
                }
            }

            if ($device_rec['IS_GPS_TRACKER']) {
                $this->processGPSLocation($device_rec['UID'],
                    array(
                        'latitude' => $device_rec['LATITUDE'],
                        'longitude' => $device_rec['LONGITUDE']
                    )
                );
            }

        }
        if ($data['type'] == 'telemetry') {
            foreach ($data['payload'] as $k => $v) {
                $this->processDeviceProperty($device_rec['ID'], $k, $v);
            }
            if (isset($data['payload']['battery_level'])) {
                $device_rec['BATTERY_LEVEL'] = $data['payload']['battery_level'];
                SQLUpdate('mesh_devices', $device_rec);
            }
        }
        if ($data['type'] == 'nodeinfo') {
            if (isset($data['payload']['hardware'])) $device_rec['HARDWARE'] = (int)$data['payload']['hardware'];
            if (isset($data['payload']['role'])) $device_rec['ROLE'] = (int)$data['payload']['role'];
            if (isset($data['payload']['longname'])) $device_rec['LONG_NAME'] = $data['payload']['longname'];
            if (isset($data['payload']['shortname'])) $device_rec['SHORT_NAME'] = $data['payload']['shortname'];
            SQLUpdate('mesh_devices', $device_rec);
        }

        if ($data['type'] == 'text') {
            $msg = array();
            $msg['ADDED'] = date('Y-m-d H:i:s', $data['timestamp']);
            $msg['FROM_UID'] = dechex($data['from']);
            $msg['TO_UID'] = dechex($data['to']);
            $msg['MESSAGE'] = $data['payload']['text'];
            if (preg_match('/json\/(.+?)\//', $data['topic'], $m)) {
                $msg['CHANNEL'] = $m[1];
            }
            SQLExec("DELETE FROM mesh_text WHERE FROM_UID='" . $msg['FROM_UID'] . "' AND TO_UID='" . $msg['TO_UID'] . "' AND ADDED='" . $msg['ADDED'] . "'");
            SQLInsert('mesh_text', $msg);

            if ($device_rec['ALLOW_CONTROL']) {
                $base_rec = SQLSelectOne("SELECT * FROM mesh_devices WHERE IS_BASE=1");
                if (isset($base_rec['ID']) && $msg['TO_UID'] == $base_rec['UID']) {
                    $this->processCommandFromDevice($device_rec['UID'], $msg['MESSAGE']);
                }
            }

        }

    }

    function processDeviceProperty($device_id, $property, $value)
    {
        $property_rec = SQLSelectOne("SELECT * FROM mesh_properties WHERE MESH_DEVICE_ID=" . (int)$device_id . " AND TITLE='" . DBSafe($property) . "'");
        if (!$property_rec['ID']) {
            $property_rec['MESH_DEVICE_ID'] = $device_id;
            $property_rec['TITLE'] = $property;
            $property_rec['VALUE'] = $value;
            $property_rec['UPDATED'] = date('Y-m-d H:i:s');
            $property_rec['ID'] = SQLInsert('mesh_properties', $property_rec);
        } else {
            $property_rec['VALUE'] = $value;
            $property_rec['UPDATED'] = date('Y-m-d H:i:s');
            SQLUpdate('mesh_properties', $property_rec);
        }
        if ($property_rec['LINKED_OBJECT'] && $property_rec['LINKED_PROPERTY']) {
            setGlobal($property_rec['LINKED_OBJECT'] . '.' . $property_rec['LINKED_PROPERTY'], $value);
        }
        if ($property_rec['LINKED_OBJECT'] && $property_rec['LINKED_METHOD']) {
            callMethod($property_rec['LINKED_OBJECT'] . '.' . $property_rec['LINKED_METHOD'], array('NEW_VALUE' => $value));
        }
    }

    function processCommandFromDevice($uid, $command)
    {
        say($command, 0, 0, 'meshtastic_' . $uid);
    }

    function processGPSLocation($uid, $attributes)
    {
        if (!isset($attributes['latitude'])) return;
        if (!isset($attributes['longitude'])) return;

        $url = 'http://localhost/gps.php?deviceid=' . urlencode('meshtastic_' . $uid);
        foreach ($attributes as $k => $v) {
            $url .= '&' . $k . '=' . urlencode($v);
        }
        getURLBackground($url);
    }

    function prepareMessageToSend($message)
    {
        $message = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $message);
        $message = preg_replace("/\W/", ' ', $message);
        $message = trim($message);
        return $message;
    }

    function sendMessageToDevice($uid, $message)
    {
        if ($uid == '') return false;
        $base = SQLSelectOne("SELECT UID FROM mesh_devices WHERE IS_BASE=1");
        if (!isset($base['UID'])) return false;
        $message = $this->prepareMessageToSend($message);
        $payload = array(
            'from' => hexdec($base['UID']),
            'to' => hexdec($uid),
            'type' => 'sendtext',
            'payload' => $message
        );
        $base_topic = $this->config['BASE_TOPIC'];
        if ($base_topic == '') $base_topic = 'msh';
        $topic = $base_topic . '/2/json/mqtt/';
        addToOperationsQueue('meshtastic_queue', $topic, json_encode($payload));
    }

    function sendMessageToChannel($channel_num, $message)
    {
        if (!$channel_num) return;
        $base = SQLSelectOne("SELECT UID FROM mesh_devices WHERE IS_BASE=1");
        if (!isset($base['UID'])) return false;
        $message = $this->prepareMessageToSend($message);
        $payload = array(
            'from' => hexdec($base['UID']),
            'channel' => (int)$channel_num,
            'type' => 'sendtext',
            'payload' => $message
        );
        $base_topic = $this->config['BASE_TOPIC'];
        if ($base_topic == '') $base_topic = 'msh';
        $topic = $base_topic . '/2/json/mqtt/';
        addToOperationsQueue('meshtastic_queue', $topic, json_encode($payload));

    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }

    /**
     * mesh_devices search
     *
     * @access public
     */
    function search_mesh_devices(&$out)
    {
        require(dirname(__FILE__) . '/mesh_devices_search.inc.php');
    }

    /**
     * mesh_devices edit/add
     *
     * @access public
     */
    function edit_mesh_devices(&$out, $id)
    {
        require(dirname(__FILE__) . '/mesh_devices_edit.inc.php');
    }

    /**
     * mesh_devices delete record
     *
     * @access public
     */
    function delete_mesh_devices($id)
    {
        $rec = SQLSelectOne("SELECT * FROM mesh_devices WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM mesh_messages WHERE MESH_DEVICE_ID='" . $rec['ID'] . "'");
        SQLExec("DELETE FROM mesh_properties WHERE MESH_DEVICE_ID='" . $rec['ID'] . "'");
        SQLExec("DELETE FROM mesh_devices WHERE ID='" . $rec['ID'] . "'");

    }

    /**
     * mesh_properties search
     *
     * @access public
     */
    function search_mesh_properties(&$out)
    {
        require(dirname(__FILE__) . '/mesh_properties_search.inc.php');
    }

    /**
     * mesh_properties edit/add
     *
     * @access public
     */
    function edit_mesh_properties(&$out, $id)
    {
        require(dirname(__FILE__) . '/mesh_properties_edit.inc.php');
    }

    function propertySetHandle($object, $property, $value)
    {
        $this->getConfig();
        $table = 'mesh_properties';
        $properties = SQLSelect("SELECT ID FROM $table WHERE LINKED_OBJECT LIKE '" . DBSafe($object) . "' AND LINKED_PROPERTY LIKE '" . DBSafe($property) . "'");
        $total = count($properties);
        if ($total) {
            for ($i = 0; $i < $total; $i++) {
                //to-do
            }
        }
    }

    function processSubscription($event, $details = '')
    {
        $this->getConfig();
        if ($event == 'SAY') {
            $level = (int)$details['level'];
            $message = $details['message'];

            $devices = SQLSelect("SELECT * FROM mesh_devices WHERE SEND_SYSTEM_MESSAGES=1 AND MIN_MSG_LEVEL<=$level");
            $total = count($devices);
            if ($total) {
                for ($i = 0; $i < $total; $i++) {
                    $this->sendMessageToDevice($devices[$i]['UID'], $message);
                }
            }

            $channels = SQLSelect("SELECT * FROM mesh_channels WHERE SEND_SYSTEM_MESSAGES=1 AND MIN_MSG_LEVEL<=$level");
            $total = count($channels);
            if ($total) {
                for ($i = 0; $i < $total; $i++) {
                    $this->sendMessageToChannel($channels[$i]['CHANNEL_NUM'], $message);
                }
            }
        }
    }

    function processCycle()
    {
        $this->getConfig();
        //to-do
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        subscribeToEvent($this->name, 'SAY');
        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS mesh_devices');
        SQLExec('DROP TABLE IF EXISTS mesh_properties');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        mesh_devices -
        mesh_properties -
        */
        $data = <<<EOD
 
 mesh_devices: ID int(10) unsigned NOT NULL auto_increment
 mesh_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 mesh_devices: SHORT_NAME varchar(100) NOT NULL DEFAULT ''
 mesh_devices: LONG_NAME varchar(100) NOT NULL DEFAULT ''
 mesh_devices: UID varchar(255) NOT NULL DEFAULT ''
 mesh_devices: BATTERY_LEVEL tinyint(3) NOT NULL DEFAULT '0'
 mesh_devices: HARDWARE tinyint(3) NOT NULL DEFAULT '0'
 mesh_devices: ROLE tinyint(3) NOT NULL DEFAULT '0'
 mesh_devices: IS_BASE tinyint(3) NOT NULL DEFAULT '0'
 mesh_devices: IS_GPS_TRACKER tinyint(3) NOT NULL DEFAULT '0'
 mesh_devices: USER_ID int(10) NOT NULL DEFAULT '0'
 mesh_devices: ALLOW_CONTROL int(3) NOT NULL DEFAULT '0'
 mesh_devices: SEND_SYSTEM_MESSAGES int(3) NOT NULL DEFAULT '0'
 mesh_devices: MIN_MSG_LEVEL int(10) NOT NULL DEFAULT '2'
 mesh_devices: LATITUDE double NOT NULL DEFAULT '0'
 mesh_devices: LONGITUDE double NOT NULL DEFAULT '0'
 mesh_devices: UPDATED datetime
 
 mesh_messages: ID int(10) unsigned NOT NULL auto_increment
 mesh_messages: MESH_DEVICE_ID int(10) NOT NULL DEFAULT '0'
 mesh_messages: ADDED datetime
 mesh_messages: MESSAGE_TYPE varchar(100) NOT NULL DEFAULT ''
 mesh_messages: PAYLOAD text
 
 mesh_channels: ID int(10) unsigned NOT NULL auto_increment
 mesh_channels: CHANNEL_NAME varchar(255) NOT NULL DEFAULT ''
 mesh_channels: CHANNEL_NUM int(3) NOT NULL DEFAULT '0'
 mesh_channels: SEND_SYSTEM_MESSAGES int(3) NOT NULL DEFAULT '0'
 mesh_channels: MIN_MSG_LEVEL int(10) NOT NULL DEFAULT '2'
 mesh_channels: UPDATED datetime
 
 mesh_text: ID int(10) unsigned NOT NULL auto_increment
 mesh_text: FROM_UID varchar(255) NOT NULL DEFAULT ''
 mesh_text: TO_UID varchar(255) NOT NULL DEFAULT ''
 mesh_text: CHANNEL varchar(255) NOT NULL DEFAULT ''
 mesh_text: MESSAGE text
 mesh_text: ADDED datetime
 
 mesh_properties: ID int(10) unsigned NOT NULL auto_increment
 mesh_properties: MESH_DEVICE_ID int(10) NOT NULL DEFAULT '0'
 mesh_properties: TITLE varchar(100) NOT NULL DEFAULT ''
 mesh_properties: VALUE varchar(255) NOT NULL DEFAULT ''
 mesh_properties: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 mesh_properties: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 mesh_properties: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 mesh_properties: UPDATED datetime
 
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRGVjIDE0LCAyMDI1IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
