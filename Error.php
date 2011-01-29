<?php
class Error
{
    public static $rc;
    public static $msg;
    public static $a;
    public static $eform = array();

    public function add_eform($new_form) {
        array_push(Error::$eform, $new_form);
    }

    public function go_error() {
        Template::$menu = 'Error';
        Template::$page = 'error';
        Template::flush($this);
    }

    public function redirect($uri) {
        switch ($_SERVER['SERVER_PORT'])
        {
            case 80:
                $protocol = 'http';
                break;
            case 443:
                $protocol = 'https';
                break;
        }
        $url = "$protocol" . '://' . BAR_URL . '/' . "$uri";
        header("Location: $url");
        ob_end_flush();
        exit();
    }
}
?>
