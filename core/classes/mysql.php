<?php
class DB {
    private $link;
    private $db;
    private $prefix;
    private $lang;

    public function __construct($var) {
        global $lang;

        $this->db = $var;
        $this->lang = $lang;
        $this->prefix = $var['db_prefix'];
        $this->link = $this->mbm_connect();
        $this->mbm_select_db();
    }

    public function mbm_connect() {
        $link = @mysqli_connect($this->db["db_host"], $this->db["db_user"], $this->db["db_pass"]);
        if (!$link) {
            die("Could not connect to database server" . substr($this->db["db_user"], 5));
        }
        return $this->link = $link;
    }

    public function mbm_select_db() {
        $result = @mysqli_select_db($this->link, $this->db["db_name"]);
        if (!$result) {
            die("Could not connect to database" . $this->db["db_user"]);
        }
        return $result;
    }

    public function mbm_query($query) {
        $result = @mysqli_query($this->link, $query);
        if (!$result) {
            $result = 0;
        }
        return $result;
    }

    public function mbm_result($result, $row_id, $field_name = null) {
        global $censored_words, $BBCODE;

        if ($field_name == null) {
            $value = @mysqli_result($result, $row_id);
        } else {
            $value = @mysqli_result($result, $row_id, $field_name);
        }
        $value = stripslashes($value);
        $value = str_replace($censored_words[0], $censored_words[1], $value);
        $value = $BBCODE->parse_bbcode($value);

        return $value;
    }

    public function mbm_close() {
        $result = @mysqli_close($this->link);
        if (!$result) {
            $result = 0;
        }
        return $result;
    }

    public function mbm_error() {
        $result = @mysqli_error($this->link);
        if (!$result) {
            $result = 0;
        }
        return $result;
    }

    public function mbm_num_rows($result) {
        $total_rows = @mysqli_num_rows($result);
        return $total_rows;
    }

    public function mbm_total_rows($table = 'menus') {
        $q = "SELECT COUNT(*) FROM " . $this->prefix . $table;
        $r = $this->mbm_query($q);
        return $this->mbm_result($r, 0);
    }

    public function mbm_insert_row($data, $tbl) {
        global $lang;

        $query = "INSERT INTO " . $this->prefix . $tbl . " ";
        $keys = "(";
        $values = "(";
        foreach ($data as $key => $value) {
            $keys .= "`" . $key . "`,";
            $values .= "'" . $this->mbm_sql_quote($value) . "',";
        }
        $keys = rtrim($keys, ',') . ") ";
        $values = rtrim($values, ',') . ")";
        $query .= $keys . " VALUES " . $values . ";";
        // Check if the record already exists in the database
        $total_field = count($data);
        $q_check_inserted = "SELECT * FROM `" . $this->prefix . $tbl . "` WHERE ";
        $n = 0;
        foreach ($data as $k => $v) {
            if ($k != 'date_added') {
                $q_check_inserted .= "`" . $k . "`='" . $v . "'";
                if ($n < ($total_field - 1)) {
                    $q_check_inserted .= " AND ";
                }
            }
        }

        $r_check_inserted = $this->mbm_query($q_check_inserted);
        // Check if the record has already been inserted

        if ($this->mbm_num_rows($r_check_inserted) > 0) {
            $return_result = 2;
        } else {
            if (!$result = $this->mbm_query($query)) {
                $return_result = 0;
            } else {
                $return_result = 1;
            }
        }
        return $return_result;
    }

    public function mbm_update_row($data, $tbl, $id, $field = 'id') {
        $query = "UPDATE " . $this->prefix . $tbl . " SET ";
        $values = "";
        foreach ($data as $key => $value) {
            $values .= "`" . $key . "`='" . $this->mbm_sql_quote($value) . "',";
        }
        $values = rtrim($values, ',');
        $query .= $values . " WHERE `" . $field . "`='" . $id . "'";
        if (!$result = $this->mbm_query($query)) {
            $return_result = 0;
        } else {
            $return_result = 1;
        }
        return $return_result;
    }

    public function mbm_delete_row($id, $tbl) {
        $query = "LOCK TABLES " . $this->prefix . $tbl . " WRITE; ";
        foreach ($id as $key => $value) {
            $query .= "DELETE FROM " . $this->prefix . $tbl . " WHERE id='" . $value . "';";
        }
        $query .= "UNLOCK TABLES;";
        if (!$result = $this->mbm_query($query)) {
            $result = 0;
        }
        return $result;
    }

    public function mbm_fetch_row($result) {
        // Not implemented in this version
    }

    public function mbm_get_field($field_value, $field_name, $to_get_fieldname, $tbl) {
        $q = "SELECT " . $to_get_fieldname . " FROM " . $this->prefix . $tbl . " WHERE " . $field_name . "='" . $field_value . "'";
        $result = $this->mbm_query($q);
        if (!$this->mbm_result($result, 0)) {
            $field = 0;
        } else {
            $field = $this->mbm_result($result, 0);
        }
        return $field;
    }

    public function mbm_insert_id() {
        return mysqli_insert_id($this->link);
    }

    public function mbm_field_name($result, $i = 0) {
        $field_info = mysqli_fetch_field_direct($result, $i);
        return $field_info->name;
    }

    public function mbm_num_fields($result) {
        return mysqli_num_fields($result);
    }

    public function mbm_check_field($fieldname = 'username', $fieldvalue = 'user', $tbl = 'users') {
        $q = "SELECT * FROM " . $this->prefix . $tbl . " WHERE `" . $fieldname . "`='" . $fieldvalue . "'";
        $result = $this->mbm_query($q);
        if ($this->mbm_num_rows($result) > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function mbm_sql_quote($str) {
        if (get_magic_quotes_gpc()) {
            $str = stripslashes($str);
        }
        if (!is_numeric($str)) {
            $str = "'" . mysqli_real_escape_string($this->link, $str) . "'";
        }
        return $str;
    }
}
