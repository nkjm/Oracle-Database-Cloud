<?php
Class Customer {
    public $err_msg = array();

    public function create($conn_asm, $conn_db, $id, $password, $disk_type) {
        $dg = $id;
        $tablespace = $id;

        // Fetch Available Disk
        $drive = new Drive();
        $available_disk = $drive->fetch_available_disk($conn_asm, $disk_type);
        if ($available_disk == FALSE ) {
            $this->err_msg = array_merge($this->err_msg, $drive->err_msg);
            array_push($this->err_msg, "新たなディスクの確保に失敗しました。");
            return(FALSE);
        }
        if ($available_disk == NULL ) {
            array_push($this->err_msg, "利用可能なディスクがありません。");
            return(FALSE);
        }

        // Create Diskgroup
        $available_disk["name"] = $dg . "_" . $disk_type;
        $result = $drive->create_dg($conn_asm, $dg, $available_disk);
        if ($result == FALSE) {
            $this->err_msg = array_merge($this->err_msg, $drive->err_msg);
            array_push($this->err_msg, "新たなディスクグループを作成出来ませんでした。");
            return(FALSE);
        }

        // Mount Diskgroup
        $result = $drive->mount_dg($conn_asm, $dg);
        if ($result == FALSE) {
            $this->err_msg = array_merge($this->err_msg, $drive->err_msg);
            array_push($this->err_msg, "ディスクグループをマウント出来ませんでした。");
            return(FALSE);
        }

        // Create Tablespace
        $result = $drive->create_tablespace($conn_db, $tablespace, $dg);
        if ($result == FALSE) {
            $this->err_msg = array_merge($this->err_msg, $drive->err_msg);
            array_push($this->err_msg, "新たな表領域を作成出来ませんでした。");
            // Rollback
            $drive->delete_dg($conn_asm, $dg);
            return(FALSE);
        }
        
        // Create User
        $state_id = oci_parse($conn_db, "create user $id identified by $password default tablespace $tablespace quota unlimited on $tablespace");
        $result = oci_execute($state_id);
        if ($result == FALSE) {
            array_push($this->err_msg, "新たなユーザーを作成出来ませんでした。");
            // Rollback
            $drive->delete_tablespace($conn_db, $tablespace);
            $drive->delete_dg($conn_asm, $dg);
            return(FALSE);
        }

        // Grant Role to User
        $state_id = oci_parse($conn_db, "grant " . CLOUD_USER  . " to $id");
        $result = oci_execute($state_id);
        if ($result == FALSE) {
            array_push($this->err_msg, "ユーザーにロールを割り当て出来ませんでした。");
            // Rollback
            self::delete($conn_asm, $conn_db, $id);
            return(FALSE);
        }

        global $array_consumer_group;
        foreach ($array_consumer_group as $consumer_group) {
            $state_id = oci_parse($conn_db, "BEGIN dbms_resource_manager_privs.grant_switch_consumer_group(grantee_name => '$id',consumer_group => '$consumer_group',grant_option => FALSE);END;");
            $result = oci_execute($state_id);
            if ($result == FALSE) {
                array_push($this->err_msg, "ユーザーにコンシューマーグループオブジェクト権限を割り当て出来ませんでした。");
                // Rollback
                self::delete($conn_asm, $conn_db, $id);
                return(FALSE);
            }
        }

        $result = self::update_consumer_group($conn_db, $id, DEFAULT_CONSUMER_GROUP);
        if ($result == FALSE) {
            array_push($this->err_msg, "コンシューマーグループマッピングを作成出来ませんでした。");
            // Rollback
            self::delete($conn_asm, $conn_db, $id);
            return(FALSE);
        }
        return(TRUE);
    }

    public function delete($conn_asm, $conn_db, $id) {
        $tablespace = $id;
        $dg = $id;

        $drive = new Drive();
        $result = $drive->delete_tablespace($conn_db, $tablespace);
        if ($result == FALSE) {
            $this->err_msg = array_merge($this->err_msg, $drive->err_msg);
            array_push($this->err_msg, "表領域を削除出来ませんでした。");
        }
        $result = $drive->delete_dg($conn_asm, $dg);
        if ($result == FALSE) {
            $this->err_msg = array_merge($this->err_msg, $drive->err_msg);
            array_push($this->err_msg, "ディスクグループを削除出来ませんでした。");
        }
        $result = self::update_consumer_group($conn_db, $id, 'NULL');
        if ($result == FALSE) {
            array_push($this->err_msg, "コンシューマーグループマッピングを削除出来ませんでした。");
        }
        $state_id = oci_parse($conn_db, "drop user $id cascade");
        $result = oci_execute($state_id);
        if ($result == FALSE) {
            array_push($this->err_msg, "ユーザーを削除出来ませんでした。");
        }
        return($result);
    }

    public function update_consumer_group($conn_db, $id, $consumer_group) {
        if ($consumer_group != 'NULL') {
            $consumer_group = "'" . $consumer_group . "'";
        }
        $state_id = oci_parse($conn_db, "BEGIN DBMS_RESOURCE_MANAGER.CREATE_PENDING_AREA(); DBMS_RESOURCE_MANAGER.SET_CONSUMER_GROUP_MAPPING (DBMS_RESOURCE_MANAGER.ORACLE_USER, '$id', $consumer_group); DBMS_RESOURCE_MANAGER.VALIDATE_PENDING_AREA(); DBMS_RESOURCE_MANAGER.SUBMIT_PENDING_AREA(); END;");
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "コンシューマグループマッピングの変更に失敗しました。");
            return(FALSE);
        }
        return(TRUE);
    }

    public function migrate_disk($conn_asm, $id, $disk_type) {
        $dg = $id;
        $drive = new Drive();
        $old_disk = $drive->fetch_disk($conn_asm, $id);
        $new_disk = $drive->fetch_available_disk($conn_asm, $disk_type);
        $new_disk["name"] = $dg . "_" . $disk_type;
        $result = $drive->migrate_disk($conn_asm, $dg, $old_disk, $new_disk, $id);
        if ($result == FALSE) {
            $this->err_msg = array_merge($this->err_msg, $drive->err_msg);
            array_push($this->err_msg, "ディスクの移行に失敗しました。");
            return(FALSE);
        }
        return(TRUE);
    }
}
?>
