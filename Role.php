<?php
Class Role {
    public $err_msg = array();

    public function create($conn_db, $name) {
        $result = self::exist($conn_db, $name);
        if ($result == TRUE) {
            array_push($this->err_msg, "ロールはすでに存在しています。");
            return(FALSE);
        }
        $state_id = oci_parse($conn_db, "CREATE ROLE $name");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "ロールの作成に失敗しました。");
            return(FALSE);
        }
        $state_id = oci_parse($conn_db, "GRANT connect to $name");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "ロールへconnectロールを割り当てられませんでした。");
            return(FALSE);
        }
        $state_id = oci_parse($conn_db, "GRANT resource to $name");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "ロールへresourceロールを割り当てられませんでした。");
            return(FALSE);
        }
        return(TRUE);
    }

    public function delete($conn_db, $name) {
        $state_id = oci_parse($conn_db, "DROP ROLE '$name'");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "ロールの削除に失敗しました。");
            return(FALSE);
        }
    }

    public function exist($conn_db, $name) {
        $state_id = oci_parse($conn_db, "SELECT count(*) FROM user$ WHERE type# = 0 and name = '" . CLOUD_USER . "'");
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
