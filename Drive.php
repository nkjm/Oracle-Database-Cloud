<?php
class Drive {
    public $err_msg = array();

    public function fetch_available_disk($conn_asm, $type) {
        global $array_disk_type;
        $sql = "SELECT GROUP_NUMBER, HEADER_STATUS, PATH, OS_MB FROM V\$ASM_DISK WHERE PATH like '$array_disk_type[$type]' AND (header_status = 'CANDIDATE' or header_status = 'FORMER')";
        $state_id = oci_parse($conn_asm, $sql);
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        $row = oci_fetch_array($state_id, OCI_BOTH);
        if (!isset($row["PATH"])) {
            return(NULL);
        }
        $available_disk = array("group_number" => $row["GROUP_NUMBER"], "path" => $row["PATH"], "size" => $row["OS_MB"]);
        return($available_disk);
    }

    public function fetch_available_disk_num($conn_asm, $type) {
        global $array_disk_type;
        $sql = "SELECT count(*) FROM V\$ASM_DISK WHERE PATH like '$array_disk_type[$type]' AND (header_status = 'CANDIDATE' or header_status = 'FORMER')";
        $state_id = oci_parse($conn_asm, $sql);
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        $row = oci_fetch_array($state_id, OCI_BOTH);
        $available_disk_num = $row[0];
        return($available_disk_num);
    }

    public function fetch_disk($conn_asm, $customer_id) {
        $sql = "select v\$asm_disk.group_number, v\$asm_disk.name, v\$asm_disk.path from v\$asm_disk join v\$asm_diskgroup on v\$asm_disk.group_number = v\$asm_diskgroup.group_number where v\$asm_diskgroup.name = '$customer_id'";
        $state_id = oci_parse($conn_asm, $sql);
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        $row = oci_fetch_array($state_id, OCI_BOTH);
        if (!isset($row["PATH"])) {
            return(NULL);
        }
        $disk = array("group_number" => $row["GROUP_NUMBER"], "name" => $row["NAME"], "path" => $row["PATH"]);
        return($disk);
    }

    public function create_dg($conn_asm, $dg, $disk) {
        $sql = "CREATE DISKGROUP $dg EXTERNAL REDUNDANCY DISK '" . $disk["path"] . "' NAME " . $disk["name"] . " SIZE " . $disk["size"] . " M ATTRIBUTE 'compatible.asm' = '11.2', 'compatible.rdbms' = '11.2', 'compatible.advm' = '11.2'"; 
        $state_id = oci_parse($conn_asm, $sql); 
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        return(TRUE);
    }

    public function mount_dg($conn_asm, $dg) {
        global $array_asm_all_nodes;
        $sql = "alter diskgroup $dg mount"; 
        foreach ($array_asm_all_nodes as $key => $asm_node) {
            if ($key == 0) {
                continue;
            }
            $conn_asm_1node = oci_connect(ASM_USER, ASM_PASSWORD, $asm_node . '/' . ASM_SERVICE, '', OCI_SYSASM);
            $state_id = oci_parse($conn_asm_1node, $sql); 
            $result = oci_execute($state_id);
            if ($result != TRUE) {
                array_push($this->err_msg, "Failed SQL = '$sql'");
                return(FALSE);
            }
        }
        return(TRUE);
    }

    public function umount_dg($conn_asm, $dg) {
        global $array_asm_all_nodes;
        $sql = "alter diskgroup $dg dismount"; 
        foreach ($array_asm_all_nodes as $key => $asm_node) {
            if ($key == 0) {
                continue;
            }
            $conn_asm_1node = oci_connect(ASM_USER, ASM_PASSWORD, $asm_node . '/' . ASM_SERVICE, '', OCI_SYSASM);
            $state_id = oci_parse($conn_asm_1node, $sql); 
            $result = oci_execute($state_id);
            if ($result != TRUE) {
                array_push($this->err_msg, "Failed SQL = '$sql'");
                return(FALSE);
            }
        }
        return(TRUE);
    }

    public function delete_dg($conn_asm, $dg) {
        // Umount Diskgroup
        self::umount_dg($conn_asm, $dg);

        // Drop Diskgroup
        $sql = "DROP DISKGROUP $dg INCLUDING CONTENTS";
        $state_id = oci_parse($conn_asm, $sql);
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        return(TRUE);
    }

    public function add_disk($conn_asm, $dg, $disk) {
        $sql = "ALTER DISKGROUP $dg ADD DISK '" . $disk["path"] . "' NAME " . $disk["name"] . " REBALANCE POWER 0";
        $state_id = oci_parse($conn_asm, $sql);
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        return(TRUE);
    }

    public function delete_disk($conn_asm, $dg, $disk) {
        $sql = "ALTER DISKGROUP $dg DROP DISK " . $disk["name"];
        $state_id = oci_parse($conn_asm, $sql);
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        return(TRUE);
    }

    public function migrate_disk($conn_asm, $dg, $old_disk, $new_disk, $customer_id) {
        // Add disk with rebalance power 0
        $result = self::add_disk($conn_asm, $dg, $new_disk);
        if ($result != TRUE) {
            array_push($this->err_msg, "ディスクの追加に失敗しました。");
            return(FALSE);
        }

        // Drop disk and start rebalance
        $result = self::delete_disk($conn_asm, $dg, $old_disk);
        if ($result != TRUE) {
            array_push($this->err_msg, "ディスクの削除に失敗しました。");
            return(FALSE);
        }
        
        // Wait for rebalance complete
        $old_header_status = "";
        while ($old_header_status != 'FORMER') {
            sleep(2);
            $sql = "SELECT HEADER_STATUS FROM V\$ASM_DISK WHERE PATH = '" . $old_disk["path"] . "'";
            $state_id = oci_parse($conn_asm, $sql);
            $result = oci_execute($state_id);
            $row = oci_fetch_array($state_id, OCI_BOTH);
            $old_header_status = $row["HEADER_STATUS"];
        }
        $number_of_disk = '2';
        while ($number_of_disk != '1') {
            sleep(2);
            $sql = "select count(*) from v\$asm_disk join v\$asm_diskgroup on v\$asm_disk.group_number = v\$asm_diskgroup.group_number where v\$asm_diskgroup.name = '" . $customer_id . "'";
            $state_id = oci_parse($conn_asm, $sql);
            $result = oci_execute($state_id);
            $row = oci_fetch_array($state_id, OCI_BOTH);
            $number_of_disk = $row[0];
        }

        return(TRUE);
    }

    public function create_tablespace($conn_db, $tablespace, $dg) {
        $sql = "CREATE BIGFILE TABLESPACE $tablespace DATAFILE '+$dg' SIZE 1M AUTOEXTEND ON NEXT 5M MAXSIZE UNLIMITED LOGGING EXTENT MANAGEMENT LOCAL SEGMENT SPACE MANAGEMENT AUTO DEFAULT COMPRESS FOR OLTP"; 
        $state_id = oci_parse($conn_db, $sql); 
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        return(TRUE);
    }

    public function delete_tablespace($conn_db, $tablespace) {
        $sql = "DROP TABLESPACE $tablespace INCLUDING CONTENTS";
        $state_id = oci_parse($conn_db, $sql);
        $result = oci_execute($state_id);
        if ($result != TRUE) {
            array_push($this->err_msg, "Failed SQL = '$sql'");
            return(FALSE);
        }
        return(TRUE);
    }
}
?>
