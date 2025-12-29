<?php
/*
* @version 0.1 (wizard)
*/

$go_linked_object = gr('go_linked_object');
$go_linked_property = gr('go_linked_property');
if ($go_linked_object && $go_linked_property) {
    $tmp = SQLSelectOne("SELECT ID, MESH_DEVICE_ID FROM mesh_properties WHERE LINKED_OBJECT = '" . DBSafe($go_linked_object) . "' AND LINKED_PROPERTY='" . DBSafe($go_linked_property) . "'");
    if ($tmp['ID']) {
        $this->redirect("?id=" . $tmp['ID'] . "&view_mode=edit_mesh_devices&id=" . $tmp['MESH_DEVICE_ID'] . "&tab=data&property_id=" . $tmp['ID']);
    }
}

global $session;
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$qry = "1";
// search filters
// QUERY READY
global $save_qry;
if ($save_qry) {
    $qry = $session->data['mesh_devices_qry'];
} else {
    $session->data['mesh_devices_qry'] = $qry;
}
if (!$qry) $qry = "1";
$sortby_mesh_devices = "UPDATED DESC";
$out['SORTBY'] = $sortby_mesh_devices;
// SEARCH RESULTS
$res = SQLSelect("SELECT * FROM mesh_devices WHERE $qry ORDER BY " . $sortby_mesh_devices);
if ($res[0]['ID']) {
    //paging($res, 100, $out); // search result paging
    $total = count($res);
    for ($i = 0; $i < $total; $i++) {
        // some action for every record if required
        $res[$i]['UPDATED']=getPassedText(strtotime($res[$i]['UPDATED']));
    }
    $out['RESULT'] = $res;
}


$channels = SQLSelect("SELECT * FROM mesh_channels ORDER BY CHANNEL_NUM");
if (isset($channels[0]['ID'])) {
    $out['CHANNELS'] = $channels;
}
