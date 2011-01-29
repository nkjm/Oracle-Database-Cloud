<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="ja">
<head profile="http://gmpg.org/xfn/11">
<title>cloud.jp.oracle.com</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="description" content="Oracle Database Cloud" />
<?php require_once './css.php';?>
</head>

<?php
require_once './Role.php';
require_once './Resource.php';
require_once './config.php';
require_once './tool.php';
require_once './Parse.php';
require_once './Drive.php';
require_once './Customer.php';
$parse = new Parse();

// Connect to Database
$conn_db = oci_connect(DB_USER, DB_PASSWORD, DB_SERVICE, '', OCI_SYSDBA);
if ($conn_db == FALSE) {
    array_push($parse->err_msg, "データベースインスタンスに接続できませんでした。");
}
err_chk($conn_db, $parse->err_msg);

// Fetch ALL ASM Nodes
$state_id = oci_parse($conn_db, 'SELECT inst_name FROM v$active_instances');
$result = oci_execute($state_id);
$array_asm_all_nodes = array();
while ($row = oci_fetch_array($state_id, OCI_BOTH)) {
    $array_inst_name = explode(':', $row['INST_NAME']);
    array_push($array_asm_all_nodes, $array_inst_name[0]);
}

// Connect to ASM Instance #1
$conn_asm = oci_connect(ASM_USER, ASM_PASSWORD, $array_asm_all_nodes[0] . '/' . ASM_SERVICE, '', OCI_SYSASM);
if ($conn_asm == FALSE) {
    array_push($parse->err_msg, "ASMインスタンスに接続できませんでした。");
}
err_chk($conn_asm, $parse->err_msg);

if (isset($_REQUEST["op"])) {
    $op = $_REQUEST["op"];
} else {
    $op = null;
}

//// Check if Database has been setup for Oracle Database Cloud.
$role = new Role();
$resource = new Resource();

// Check if Role has been created.
$flag_role_required = FALSE;
$result = $role->exist($conn_db, CLOUD_USER);
if ($result == FALSE) {
    $op = 'initialize';
    $flag_role_required = TRUE;
} 

// Check if Consumer Groups have been created.
$flag_consumer_group_required = FALSE;
foreach ($array_consumer_group as $consumer_group) {
    $result = $resource->exist_consumer_group($conn_db, $consumer_group);
    if ($result == FALSE) {
        $op = 'initialize';
        $flag_consumer_group_required = TRUE;
    }
}

// Check if Plan has been created.
$flag_resource_plan_required = FALSE;
$result = $resource->exist_resource_plan($conn_db, RESOURCE_PLAN);
if ($result == FALSE) {
    $op = 'initialize';
    $flag_resource_plan_required = TRUE;
}

// Check if Plan has been enabled.
$result = $resource->status_resource_plan($conn_db, RESOURCE_PLAN);
if ($result == 'DISABLED') {
    $op = 'initialize';
    $flag_resource_plan_disabled = TRUE;
}

// Sanitize
if (isset($_REQUEST["confirmed"])) {
    $confirmed = TRUE;
} else {
    $confirmed = FALSE;
}
if (isset($_REQUEST["customer_id"])) {
    $customer_id = $parse->id($_REQUEST["customer_id"]);
    err_chk($customer_id, $parse->err_msg);
}
if (isset($_REQUEST["customer_password"])) {
    $customer_password = $parse->password($_REQUEST["customer_password"]);
    err_chk($customer_password, $parse->err_msg);
}
if (isset($_REQUEST["consumer_group"])) {
    $consumer_group = $parse->select($_REQUEST["consumer_group"], $array_consumer_group);
    err_chk($consumer_group, $parse->err_msg);
}
if (isset($_REQUEST["disk_type"])) {
    $array_flipped_disk_type = array_flip($array_disk_type);
    $disk_type = $parse->select($_REQUEST["disk_type"], $array_flipped_disk_type);
    err_chk($disk_type, $parse->err_msg);
}


switch ($op) {
    case 'initialize':
        if ($confirmed == TRUE) {
            $role = new Role();
            $resource = new Resource();

            // Create Role.
            if ($flag_role_required == TRUE) {
                $result = $role->create($conn_db, CLOUD_USER);
                err_chk($result, $role->err_msg);
            }
             
            // Create Consumer Groups.
            if ($flag_consumer_group_required == TRUE) {
                foreach ($array_consumer_group as $consumer_group) {
                    $result = $resource->create_consumer_group($conn_db, $consumer_group);
                    err_chk($result, $resource->err_msg);
                }
            }
            
            // Create Resource Plan.
            if ($flag_resource_plan_required == TRUE) {
                $result = $resource->create_resource_plan($conn_db, RESOURCE_PLAN, $array_consumer_group, $array_cpu_utilization_limit);
                err_chk($result, $resource->err_msg);
            }

            // Enable Resource Plan.
            if ($flag_resource_plan_disabled == TRUE) {
                $result = $resource->enable_resource_plan($conn_db, RESOURCE_PLAN);
                err_chk($result, $resource->err_msg);
            }
        } else {
            echo "データベースはOracle Database Cloud用に初期化が必要です。初期化しますか？<br/>";
            echo "<a href='/'>いいえ</a><br/>\n";
            echo "<a href='?flag_initialize_required=TRUE&op=$op&confirmed=TRUE'>はい、どうぞ</a><br/>\n";
            exit;
        }
        break;
    case 'create_customer':
        $customer = new Customer();
        $customer_id = CUSTOMER_PREFIX . $customer_id;
        $result = $customer->create($conn_asm, $conn_db, $customer_id, $customer_password, $disk_type);
        err_chk($result, $customer->err_msg);
        break;
    case 'delete_customer':
        $customer = new Customer();
        if ($confirmed == TRUE) {
            $result = $customer->delete($conn_asm, $conn_db, $customer_id);
            err_chk($result, $customer->err_msg);
        } else {
            echo str_replace(CUSTOMER_PREFIX, "", $customer_id) . "を削除します。よろしいですか？<br/>\n";
            echo "<a href='/'>いいえ</a><br/>\n";
            echo "<a href='?customer_id=$customer_id&op=$op&confirmed=TRUE'>はい、どうぞ</a><br/>\n";
            exit;
        }
        break;
    case 'update_consumer_group':
        $customer = new Customer();
        $result = $customer->update_consumer_group($conn_db, $customer_id, $consumer_group);
        err_chk($result, $customer->err_msg);
        break;
    case 'migrate_disk':
        $customer = new Customer();
        $result = $customer->migrate_disk($conn_asm, $customer_id, $disk_type);
        err_chk($result, $customer->err_msg);
        break;
    default:
        break;
}
?>

<body>
<div id=head>
    <h1 style="margin: 10px 20px 10px 0px;"><a href="/"><img src='logo.png' style='border: solid 2px #dddddd; padding: 3px'/></a></h1>
</div>
<div id=middle>
    <div class=box>
        <h3>新しいカスタマーの作成</h3>
        <form action="/" method=post>
        <table style='text-align: left; margin: 0px auto 0px auto;'>
            <tr>
                <td><input type=text name=customer_id></input></td>
                <td>カスタマーID</td>
            </tr>
            <tr>
                <td><input type=password name=customer_password></input></td>
                <td>パスワード</td>
            </tr>
            <tr>
                <td style="text-align: right">
                    <select name=disk_type>
<?php
$drive = new Drive();
foreach ($array_disk_type as $disk_type => $needle) {
    $available_disk_num = $drive->fetch_available_disk_num($conn_asm, $disk_type);
    if ($available_disk_num == 0) {
        continue;
    }
    if ($disk_type == DEFAULT_DISK_TYPE) {
        echo "<option value=$disk_type selected>$disk_type (残" . $available_disk_num . ")</option>\n";
    } else {
        echo "<option value=$disk_type>$disk_type (残" . $available_disk_num . ")</option>\n";
    }
}
?>
                    </select>
                </td>
                <td>ディスクタイプ</td>
            </tr>
            <tr>
                <td style='text-align: right'><input type=submit value="作成"></input></td>
                <td>&nbsp;</td>
            </tr>
            <input type=hidden name=op value="create_customer"></input>
        </table>
        </form>
<?php
if ($op == 'create_customer') {
    echo "<div style='margin: 10px 0px 10px 0px'>\n";
    echo "新しいカスタマー\"" . strtoupper(str_replace(CUSTOMER_PREFIX, "", $customer_id)) . "\"が作成されました。<br/>\n";
    echo "接続先：<span style='color: #cc0000'>" . strtoupper($customer_id) . "/" . $customer_password . "@" . CUSTOMER_SERVICE . "</span><br/>\n";
    echo "</div>\n";
}
?>
    </div>
    <div class=box>
        <h3>既存カスタマー</h3>
<?php
$state_id = oci_parse($conn_db, "select USERNAME, INITIAL_RSRC_CONSUMER_GROUP from dba_users where username like '" . CUSTOMER_PREFIX . "%' order by username");
$result = oci_execute($state_id);
if ($result == FALSE) {
    echo "エラー: カスタマーリストを取得できませんでした。";
} else {
    echo "<table style='margin: 0px auto 0px auto;'>\n";
    echo "<tr>\n";
    echo "<th>カスタマー</th>";
    echo "<th style='padding: 0px 7px 0px 7px; border-bottom: solid 1px #ffffff;'>&nbsp;</th>\n";
    echo "<th colspan=3>CPUプラン</th>";
    echo "<th style='padding: 0px 7px 0px 7px; border-bottom: solid 1px #ffffff;'>&nbsp;</th>\n";
    echo "<th colspan=2>ディスクタイプ</th>";
    echo "<th style='padding: 0px 7px 0px 7px; border-bottom: solid 1px #ffffff;'>&nbsp;</th>\n";
    echo "<th>&nbsp;</th>\n";
    echo "</tr>\n";
    while ($row = oci_fetch_array($state_id, OCI_BOTH)) {
        echo "<tr>\n";

        echo "<td class=matrix_td style='text-align: right;'>" . str_replace(CUSTOMER_PREFIX, "", $row['USERNAME']) . "</td>\n";

        echo "<td>&nbsp;</td>\n";

        foreach ($array_consumer_group as $consumer_group) {
            echo "<td class=matrix_td>\n";
            if ($row['INITIAL_RSRC_CONSUMER_GROUP'] == $consumer_group) {
                echo "<span class=selected>$consumer_group</span>\n";
            } else {
                echo "<a href='?customer_id=" . $row['USERNAME'] . "&op=update_consumer_group&consumer_group=$consumer_group'>$consumer_group</a>\n";
            }
            echo "</td>\n";
        }

        echo "<td>&nbsp;</td>\n";

        $sql2 = "select v\$asm_disk.name from v\$asm_disk join v\$asm_diskgroup on v\$asm_disk.group_number = v\$asm_diskgroup.group_number where v\$asm_diskgroup.name = '" . $row['USERNAME'] . "'";
        $state_id2 = oci_parse($conn_asm, $sql2);
        $result2 = oci_execute($state_id2);
        $row2 = oci_fetch_array($state_id2, OCI_BOTH);
        $disk_name = $row2['NAME'];
        $disk_type = str_replace($row['USERNAME'] . "_", "", $disk_name);
        foreach ($array_disk_type as $k => $v) {
            echo "<td class=matrix_td>\n";
            if ($disk_type == $k) {
                echo "<span class=selected>$k</span>\n";
            } else {
                echo "<a href='?customer_id=" . $row['USERNAME'] . "&op=migrate_disk&disk_type=$k'>$k</a>\n";
            }
            echo "</td>\n";
        }

        echo "<td>&nbsp;</td>\n";

        echo "<td class=matrix_td><a href='?customer_id=" . $row['USERNAME'] . "&op=delete_customer'>削除</a></td>\n";

        echo "</tr>\n";
    }
    echo "</table>\n";
}
?>
    </div>
</div>
</body>
</html>

