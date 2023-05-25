<?php

class DB {
    private $connection;
    private $db;
    private $prefix;
    private $lang;

    public function __construct($var) {
        global $lang;

        $this->db = $var;
        $this->lang = $lang;
        $this->prefix = $var['db_prefix'];
        $this->connection = $this->mbm_connect();
        $this->mbm_select_db();
    }

    public function mbm_connect() {
        $connectionString = "(DESCRIPTION=(ADDRESS_LIST=(ADDRESS=(PROTOCOL=TCP)(HOST={$this->db['db_host']})(PORT={$this->db['db_port']})))(CONNECT_DATA=(SERVICE_NAME={$this->db['db_name']})))";
        $connection = oci_connect($this->db['db_user'], $this->db['db_pass'], $connectionString);
        if (!$connection) {
            $error = oci_error();
            die("Could not connect to database: " . $error['message']);
        }
        return $this->connection = $connection;
    }

    public function mbm_select_db() {
        // Oracle does not require selecting a specific database after connecting
    }

    public function mbm_query($query) {
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $result = 0;
        }
        return $result;
    }

    public function mbm_result($result, $row_id, $field_name = null) {
        global $censored_words, $BBCODE;

        if ($field_name === null) {
            oci_fetch_all($result, $rows, null, null, OCI_FETCHSTATEMENT_BY_ROW);
            $value = $rows[$row_id];
        } else {
            $value = oci_result($result, $row_id, $field_name);
        }
        $value = stripslashes($value);
        $value = str_replace($censored_words[0], $censored_words[1], $value);
        $value = $BBCODE->parse_bbcode($value);

        return $value;
    }

    public function mbm_close() {
        oci_close($this->connection);
    }

    public function mbm_error() {
        $error = oci_error($this->connection);
        if ($error) {
            return $error['message'];
        } else {
            return 0;
        }
    }

    public function mbm_num_rows($result) {
        $rows = oci_fetch_all($result, $data, null, null, OCI_FETCHSTATEMENT_BY_ROW);
        return $rows;
    }

    public function mbm_total_rows($table = 'menus') {
        $query = "SELECT COUNT(*) FROM " . $this->prefix . $table;
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $error = oci_error($statement);
            die("Error in SQL: " . $error['message']);
        }
        $row = oci_fetch_array($statement);
        return $row[0];
    }

    public function mbm_insert_row($data, $tbl) {
        $keys = implode(", ", array_map(function ($key) {
            return "\"{$key}\"";
        }, array_keys($data)));

        $values = implode(", ", array_map(function ($value) {
            return "'" . $this->mbm_sql_quote($value) . "'";
        }, $data));

        $query = "INSERT INTO " . $this->prefix . $tbl . " (" . $keys . ") VALUES (" . $values . ")";
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $return_result = 0;
        } else {
            $return_result = 1;
        }
        return $return_result;
    }

    public function mbm_update_row($data, $tbl, $id, $field = 'id') {
        $setClause = implode(", ", array_map(function ($key, $value) {
            return "\"{$key}\" = '" . $this->mbm_sql_quote($value) . "'";
        }, array_keys($data), $data));

        $query = "UPDATE " . $this->prefix . $tbl . " SET " . $setClause . " WHERE \"{$field}\" = '" . $id . "'";
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $return_result = 0;
        } else {
            $return_result = 1;
        }
        return $return_result;
    }

    public function mbm_delete_row($id, $tbl) {
        $query = "DELETE FROM " . $this->prefix . $tbl . " WHERE id = '" . $id . "'";
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $result = 0;
        }
        return $result;
    }

    public function mbm_fetch_row($result) {
        oci_fetch_all($result, $rows, null, null, OCI_FETCHSTATEMENT_BY_ROW);
        return ($rows) ? $rows[0] : null;
    }

    public function mbm_get_field($field_value, $field_name, $to_get_fieldname, $tbl) {
        $query = "SELECT \"{$to_get_fieldname}\" FROM \"" . $this->prefix . $tbl . "\" WHERE \"{$field_name}\" = '" . $field_value . "'";
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $error = oci_error($statement);
            die("Error in SQL: " . $error['message']);
        }
        $row = oci_fetch_array($statement);
        if (!$row) {
            $field = 0;
        } else {
            $field = $row[0];
        }
        return $field;
    }

    public function mbm_insert_id() {
        // Oracle does not have a direct equivalent to the auto-increment ID concept,
        // so this method might not be applicable in all cases.
        return null;
    }

    public function mbm_field_name($result, $i = 0) {
        $column_name = oci_field_name($result, $i + 1);
        return $column_name;
    }

    public function mbm_num_fields($result) {
        $num_fields = oci_num_fields($result);
        return $num_fields;
    }

    public function mbm_check_field($fieldname = 'username', $fieldvalue = 'user', $tbl = 'users') {
        $query = "SELECT * FROM \"" . $this->prefix . $tbl . "\" WHERE \"{$fieldname}\" = '" . $fieldvalue . "'";
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $error = oci_error($statement);
            die("Error in SQL: " . $error['message']);
        }
        $num_rows = oci_fetch_all($statement, $data);
        return ($num_rows > 0);
    }

    public function mbm_show_select_options($tbl_name, $field = "name", $value = 0) {
        $query = "SELECT * FROM " . $this->prefix . $tbl_name . " ORDER BY " . $field;
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $error = oci_error($statement);
            die("Error in SQL: " . $error['message']);
        }
        $options = '';
        while ($row = oci_fetch_array($statement)) {
            $option_value = $row['ID'];
            $option_label = $row[$field];
            $selected = ($option_value == $value) ? 'selected' : '';
            $options .= "<option value=\"{$option_value}\" {$selected}>{$option_label}</option>";
        }
        return $options;
    }

    public function mbm_get_max_field($value, $value_field, $max_field, $table) {
        $query = "SELECT MAX(\"{$max_field}\") FROM \"" . $this->prefix . $table . "\" WHERE \"" . strtolower($value_field) . "\" = '{$value}'";
        $statement = oci_parse($this->connection, $query);
        if (!$statement) {
            $error = oci_error($this->connection);
            die("Error in SQL: " . $error['message']);
        }
        $result = oci_execute($statement);
        if (!$result) {
            $error = oci_error($statement);
            die("Error in SQL: " . $error['message']);
        }
        $row = oci_fetch_array($statement);
        return $row[0];
    }

    public function mbm_sql_quote($str) {
        return str_replace("'", "''", $str);
    }
}
