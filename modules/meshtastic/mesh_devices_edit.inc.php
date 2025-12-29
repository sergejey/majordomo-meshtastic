<?php
/*
* @version 0.1 (wizard)
*/
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$table_name = 'mesh_devices';
$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");
if ($this->mode == 'update') {
    $ok = 1;
    // step: default
    if ($this->tab == '') {
        //updating '<%LANG_TITLE%>' (varchar, required)
        $rec['TITLE'] = gr('title');
        if ($rec['TITLE'] == '') {
            $out['ERR_TITLE'] = 1;
            $ok = 0;
        }

        $rec['ALLOW_CONTROL'] = gr('allow_control', 'int');
        $rec['SEND_SYSTEM_MESSAGES'] = gr('send_system_messages', 'int');
        $rec['MIN_MSG_LEVEL'] = gr('min_msg_level', 'int');
        $rec['IS_GPS_TRACKER'] = gr('is_gps_tracker', 'int');


    }
    // step: data
    if ($this->tab == 'data') {
    }
    //UPDATING RECORD
    if ($ok) {
        if (isset($rec['ID'])) {
            SQLUpdate($table_name, $rec); // update
        } else {
            $new_rec = 1;
            $rec['ID'] = SQLInsert($table_name, $rec); // adding new record
        }
        $out['OK'] = 1;
    } else {
        $out['ERR'] = 1;
    }
}
// step: default
if ($this->tab == '') {
}
// step: data
if ($this->tab == 'data') {
}

if ($this->tab == 'text') {

    if ($this->mode == 'send') {
        $message = gr('message');
        if ($message != '') {
            $this->sendMessageToDevice($rec['UID'], $message);
            $this->redirect("?view_mode=" . $this->view_mode . "&data_source=" . $rec['DATA_SOURCE'] . "&id=" . $rec['ID'] . "&tab=" . $this->tab . "&ok_msg=" . urlencode('Message sent.'));
        }
    }

    $all_devices = SQLSelect("SELECT TITLE, UID FROM mesh_devices ORDER BY TITLE");
    foreach ($all_devices as $device) {
        $uid[$device['UID']] = $device['TITLE'];
    }

    $texts = SQLSelect("SELECT * FROM mesh_text WHERE (FROM_UID='" . $rec['UID'] . "' OR TO_UID='" . $rec['UID'] . "') ORDER BY ADDED DESC LIMIT 100");

    $total = count($texts);
    for ($i = 0; $i < $total; $i++) {
        if (isset($uid[$texts[$i]['FROM_UID']])) {
            $texts[$i]['FROM_UID'] = $texts[$i]['FROM_UID'] . ' (' . $uid[$texts[$i]['FROM_UID']] . ')';
        }
        if (isset($uid[$texts[$i]['TO_UID']])) {
            $texts[$i]['TO_UID'] = $texts[$i]['TO_UID'] . ' (' . $uid[$texts[$i]['TO_UID']] . ')';
        }
    }

    $out['TEXTS'] = $texts;
}

if ($this->tab == 'history') {

    $type = gr('type');
    $process = gr('process', 'int');
    if ($process) {
        $message = SQLSelectOne("SELECT * FROM mesh_messages WHERE ID='" . (int)$process . "'");
        $data = json_decode($message['PAYLOAD'], true);
        $this->processDeviceMessage(hexdec($rec['UID']), $data, true);
        $this->redirect("?view_mode=edit_mesh_devices&id=" . $rec['ID'] . "&tab=history&type=" . $type);
    }

    $qry = "1";
    if ($type != '') {
        if ($type == 'null') {
            $qry .= " AND MESSAGE_TYPE=''";
        } else {
            $qry .= " AND MESSAGE_TYPE='" . DBSafe($type) . "'";
        }
        $out['TYPE'] = $type;
    }
    $messages = SQLSelect("SELECT * FROM mesh_messages WHERE MESH_DEVICE_ID='" . $rec['ID'] . "' AND $qry ORDER BY ID DESC LIMIT 100");
    $out['MESSAGES'] = $messages;
    $types = SQLSelect("SELECT DISTINCT(MESSAGE_TYPE) FROM mesh_messages WHERE MESH_DEVICE_ID='" . $rec['ID'] . "' ORDER BY MESSAGE_TYPE");
    $total = count($types);
    for ($i = 0; $i < $total; $i++) {
        if ($types[$i]['MESSAGE_TYPE'] == '') $types[$i]['MESSAGE_TYPE'] = 'null';
    }
    $out['TYPES'] = $types;

}

if ($this->tab == 'data') {
    //dataset2
    $new_id = 0;
    global $delete_id;
    if ($delete_id) {
        SQLExec("DELETE FROM mesh_properties WHERE ID='" . (int)$delete_id . "'");
    }
    $properties = SQLSelect("SELECT * FROM mesh_properties WHERE MESH_DEVICE_ID='" . $rec['ID'] . "' ORDER BY ID");
    $total = count($properties);
    for ($i = 0; $i < $total; $i++) {
        $properties[$i]['UPDATED'] = getPassedText(strtotime($properties[$i]['UPDATED']));
    }
    $out['PROPERTIES'] = $properties;

    $property_id = gr('property_id', 'int');
    if ($property_id) {
        $property = SQLSelectOne("SELECT * FROM mesh_properties WHERE ID='" . (int)$property_id . "' AND MESH_DEVICE_ID='" . (int)$rec['ID'] . "'");
        if ($this->mode == 'update') {
            $property['LINKED_OBJECT'] = gr('linked_object');
            $property['LINKED_PROPERTY'] = gr('linked_property');
            $property['LINKED_METHOD'] = gr('linked_method');
            SQLUpdate('mesh_properties', $property);
            if ($property['LINKED_OBJECT'] && $property['LINKED_PROPERTY']) {
                addLinkedProperty($property['LINKED_OBJECT'], $property['LINKED_PROPERTY'], $this->name);
            }
            $this->redirect("?view_mode=edit_mesh_devices&id=" . $rec['ID'] . "&tab=data&property_id=" . $property_id."&ok_msg=".urlencode(LANG_DATA_SAVED));
        }
        foreach ($property as $k => $v) {
            $out['PROPERTY_' . $k] = htmlspecialchars($v);
        }
    }

}
if (is_array($rec)) {
    $rec['UID'] = $rec['UID'] . ' (' . hexdec($rec['UID']) . ')';
    foreach ($rec as $k => $v) {
        if (!is_array($v)) {
            $rec[$k] = htmlspecialchars($v);
        }
    }
}
outHash($rec, $out);
