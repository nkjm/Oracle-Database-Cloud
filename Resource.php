<?php
Class Resource {
    public $err_msg = array();

    public function create_consumer_group($conn_db, $consumer_group) {
        $result = self::exist_consumer_group($conn_db, $consumer_group);
        if ($result == TRUE) {
            return(TRUE);
        }
        $state_id = oci_parse($conn_db, "BEGIN DBMS_RESOURCE_MANAGER.CREATE_PENDING_AREA(); DBMS_RESOURCE_MANAGER.CREATE_CONSUMER_GROUP (CONSUMER_GROUP => '$consumer_group', COMMENT => ''); DBMS_RESOURCE_MANAGER.VALIDATE_PENDING_AREA(); DBMS_RESOURCE_MANAGER.SUBMIT_PENDING_AREA(); END;");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "コンシューマグループの作成に失敗しました。");
            return(FALSE);
        }
        return(TRUE);
    }

    public function delete_consumer_group($conn_db, $consumer_group) {
        $state_id = oci_parse($conn_db, "BEGIN DBMS_RESOURCE_MANAGER.CREATE_PENDING_AREA(); DBMS_RESOURCE_MANAGER.DELETE_CONSUMER_GROUP (CONSUMER_GROUP => '$consumer_group'); DBMS_RESOURCE_MANAGER.VALIDATE_PENDING_AREA(); DBMS_RESOURCE_MANAGER.SUBMIT_PENDING_AREA(); END;");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "コンシューマグループの削除に失敗しました。");
            return(FALSE);
        }
        return(TRUE);
    }

    public function create_resource_plan($conn_db, $resource_plan, $array_consumer_group, $array_cpu_utilization_limit) {
        $result = self::exist_resource_plan($conn_db, $resource_plan);
        if ($result == TRUE) {
            array_push($this->err_msg, "リソースプランは存在しています。");
            return(FALSE);
        }
        $create_plan_directive = "DBMS_RESOURCE_MANAGER.CREATE_PLAN_DIRECTIVE (PLAN => '$resource_plan', GROUP_OR_SUBPLAN => 'OTHER_GROUPS', COMMENT => '');";
        foreach ($array_consumer_group as $consumer_group) {
            $create_plan_directive .= "DBMS_RESOURCE_MANAGER.CREATE_PLAN_DIRECTIVE (PLAN => '$resource_plan', GROUP_OR_SUBPLAN => '$consumer_group', COMMENT => '', MAX_UTILIZATION_LIMIT => '$array_cpu_utilization_limit[$consumer_group]');";
        }
        $sql = "
BEGIN DBMS_RESOURCE_MANAGER.CREATE_PENDING_AREA(); 
DBMS_RESOURCE_MANAGER.CREATE_PLAN (PLAN => '$resource_plan', COMMENT => ''); 
" . $create_plan_directive . "
DBMS_RESOURCE_MANAGER.VALIDATE_PENDING_AREA(); 
DBMS_RESOURCE_MANAGER.SUBMIT_PENDING_AREA(); 
END;";
        $state_id = oci_parse($conn_db, $sql);
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "リソースプランの作成に失敗しました。");
            return(FALSE);
        }
        return(TRUE);
    }

    public function delete_resource_plan($conn_db, $resource_plan) {
        $state_id = oci_parse($conn_db, "BEGIN DBMS_RESOURCE_MANAGER.CREATE_PENDING_AREA(); DBMS_RESOURCE_MANAGER.DELETE_PLAN (PLAN => '$resource_plan'); DBMS_RESOURCE_MANAGER.VALIDATE_PENDING_AREA(); DBMS_RESOURCE_MANAGER.SUBMIT_PENDING_AREA(); END;");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "リソースプランの削除に失敗しました。");
            return(FALSE);
        }
        return(TRUE);
    }

    public function enable_resource_plan($conn_db, $resource_plan) {
        $state_id = oci_parse($conn_db, "ALTER SYSTEM SET RESOURCE_MANAGER_PLAN = '$resource_plan' SCOPE = BOTH");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "リソースプランの有効化に失敗しました。");
            return(FALSE);
        }
        return(TRUE);
    }

    public function status_resource_plan($conn_db, $resource_plan) {
        $state_id = oci_parse($conn_db, "SELECT value FROM v\$parameter WHERE name = 'resource_manager_plan'");
        $result = oci_execute($state_id);
        $row = oci_fetch_array($state_id, OCI_BOTH);
        if (!array_key_exists('VALUE', $row)) {
            return('DISABLED');
        }
        if ($row['VALUE'] == $resource_plan) {
            return('ENABLED');
        } else {
            return('DISABLED');
        }
    }

    public function exist_consumer_group($conn_db, $consumer_group) {
        $state_id = oci_parse($conn_db, "SELECT count(*) FROM dba_rsrc_consumer_groups WHERE consumer_group = '$consumer_group'");
        $result = oci_execute($state_id);
        $row = oci_fetch_array($state_id, OCI_BOTH);
        if ($row[0] == '1') {
            return(TRUE);
        } else {
            return(FALSE);
        }
    }

    public function exist_resource_plan($conn_db, $resource_plan) {
        $state_id = oci_parse($conn_db, "SELECT count(*) FROM dba_rsrc_plans WHERE plan = '" . RESOURCE_PLAN . "'");
        $result = oci_execute($state_id);
        $row = oci_fetch_array($state_id, OCI_BOTH);
        if ($row[0] == '1') {
            return(TRUE);
        } else {
            return(FALSE);
        }
    }
}
?>
