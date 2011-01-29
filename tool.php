<?php
function err_chk($result, $array_err_msg) {
    if ($result == FALSE) {
        foreach ($array_err_msg as $err_msg) {
            echo $err_msg . "<br/>\n";
        }
        exit;
    }
}
?>
