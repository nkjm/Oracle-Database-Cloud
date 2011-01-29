<?php
class Parse {
    public $err_msg = array();

    public function id($input) {
        $pattern = '/^[\w_-]+$/';
        $trimmed_input = trim($input);
        if (!preg_match($pattern, $trimmed_input)) {
            array_push($this->err_msg, "許可されている文字列はアルファベット、数字、_(アンダースコア）,-(ハイフン)です。");
            return(FALSE);
        }
        return($trimmed_input);
    }

    public function password($input) {
        return(self::id($input));
    }
    
    public function select($input, $array_option) {
        $trimmed_input = trim($input);
        if (array_search($trimmed_input, $array_option) === FALSE) {
            array_push($this->err_msg, "不正な文字列が入力されました。");
            return(FALSE);
        }
        return($trimmed_input);
    }
}
?>
