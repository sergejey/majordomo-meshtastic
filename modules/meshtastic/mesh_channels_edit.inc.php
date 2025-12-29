<?php

$rec = SQLSelectOne("SELECT * FROM mesh_channels WHERE ID='" . (int)$id . "'");

if ($this->mode == 'send') {
    $message = gr('message');
    if ($message!='') {
        $this->sendMessageToChannel($rec['CHANNEL_NUM'], $message);
        $this->redirect("?view_mode=edit_mesh_channels&data_source=" . $rec['DATA_SOURCE'] . "&id=" . $rec['ID'] . "&ok_msg=" . urlencode('Message sent.'));
    }
}

if ($this->mode == 'update') {
    $rec['SEND_SYSTEM_MESSAGES'] = gr('send_system_messages', 'int');
    $rec['MIN_MSG_LEVEL'] = gr('min_msg_level', 'int');
    SQLUpdate('mesh_channels', $rec);
    $this->redirect("?view_mode=edit_mesh_channels&data_source=" . $rec['DATA_SOURCE'] . "&id=" . $rec['ID'] . "&ok_msg=" . urlencode(LANG_DATA_SAVED));
}

foreach ($rec as $key => $value) {
    $out[$key] = htmlspecialchars($value);
}

$messages = SQLSelect("SELECT mesh_text.*, mesh_devices.ID as DEVICE_ID, mesh_devices.TITLE as DEVICE_TITLE FROM mesh_text LEFT JOIN mesh_devices ON mesh_text.FROM_UID=mesh_devices.UID WHERE mesh_text.CHANNEL='" . DBsafe($rec['CHANNEL_NAME']) . "' ORDER BY mesh_text.ID DESC LIMIT 100");
if (isset($messages[0])) {
    $out['MESSAGES'] = $messages;
}

