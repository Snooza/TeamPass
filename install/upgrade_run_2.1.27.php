<?php
/**
 * @file          upgrade.ajax.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2016 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/*
** Upgrade script for release 2.1.27
*/
require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';

$_SESSION['settings']['loaded'] = "";

################
## Function permits to get the value from a line
################
function getSettingValue($val)
{
    $val = trim(strstr($val, "="));
    return trim(str_replace('"', '', substr($val, 1, strpos($val, ";")-1)));
}

################
## Function permits to check if a column exists, and if not to add it
################
function addColumnIfNotExist($db, $column, $columnAttr = "VARCHAR(255) NULL")
{
    global $dbTmp;
    $exists = false;
    $columns = mysqli_query($dbTmp, "show columns from $db");
    while ($c = mysqli_fetch_assoc( $columns)) {
        if ($c['Field'] == $column) {
            $exists = true;
            return true;
        }
    }
    if (!$exists) {
        return mysqli_query($dbTmp, "ALTER TABLE `$db` ADD `$column`  $columnAttr");
    } else {
        return false;
    }
}

function addIndexIfNotExist($table, $index, $sql ) {
    global $dbTmp;

    $mysqli_result = mysqli_query($dbTmp, "SHOW INDEX FROM $table WHERE key_name LIKE \"$index\"");
    $res = mysqli_fetch_row($mysqli_result);

    // if index does not exist, then add it
    if (!$res) {
        $res = mysqli_query($dbTmp, "ALTER TABLE `$table` " . $sql);
    }

    return $res;
}

function tableExists($tablename, $database = false)
{
    global $dbTmp;

    $res = mysqli_query($dbTmp,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '".$_SESSION['db_bdd']."'
        AND table_name = '$tablename'"
    );

    if ($res > 0) return true;
    else return false;
}

function cleanFields($txt) {
    $tmp = str_replace(",", ";", trim($txt));
    if (empty($tmp)) return $tmp;
    if ($tmp === ";") return "";
    if (strpos($tmp, ';') === 0) $tmp = substr($tmp, 1);
    if (substr($tmp, -1) !== ";") $tmp = $tmp.";";
    return $tmp;
}

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

$return_error = "";

// do initial upgrade

//include librairies
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';

//Build tree
$tree = new Tree\NestedTree\NestedTree(
    $_SESSION['tbl_prefix'].'nested_tree',
    'id',
    'parent_id',
    'title'
);

// dataBase
$res = "";

mysqli_connect(
    $_SESSION['db_host'],
    $_SESSION['db_login'],
    $_SESSION['db_pw'],
    $_SESSION['db_bdd'],
    $_SESSION['db_port']
);
$dbTmp = mysqli_connect(
    $_SESSION['db_host'],
    $_SESSION['db_login'],
    $_SESSION['db_pw'],
    $_SESSION['db_bdd'],
    $_SESSION['db_port']
);



// alter table Items
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."items` MODIFY pw_len INT(5) NOT NULL DEFAULT '0'");

// alter table misc to add an index
mysqli_query(
    $dbTmp,
    "ALTER TABLE `".$_SESSION['tbl_prefix']."misc` ADD `increment_id` INT(12) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`increment_id`)"
);

// alter table misc to add an index
mysqli_query(
    $dbTmp,
    "ALTER TABLE `".$_SESSION['tbl_prefix']."log_items` ADD `increment_id` INT(12) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`increment_id`)"
);

// add field agses-usercardid to Users table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."users",
    "agses-usercardid",
    "VARCHAR(12) NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field agses-usercardid to table Users! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}


// add field encrypted_data to Categories table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."categories",
    "encrypted_data",
    "TINYINT(1) NOT NULL DEFAULT '1'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encrypted_data to table categories! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}


// alter table USERS - user_language
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."users` MODIFY user_language VARCHAR(50) NOT NULL DEFAULT '0'");

// alter table USERS - just ensure correct naming of IsAdministratedByRole
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."users` CHANGE IsAdministratedByRole isAdministratedByRole tinyint(5) NOT NULL DEFAULT '0'");

// alter table OTV
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['tbl_prefix']."otv` CHANGE originator originator int(12) NOT NULL DEFAULT '0'");

// do clean of users table
$fieldsToUpdate = ['groupes_visibles', 'fonction_id', 'groupes_interdits'];
$result = mysqli_query($dbTmp, "SELECT id, groupes_visibles, fonction_id, groupes_interdits FROM `".$_SESSION['tbl_prefix']."users`");
while($row = mysqli_fetch_assoc($result)) {
    // check if field contains , instead of ;
    foreach($fieldsToUpdate as $field) {
        $tmp = cleanFields($row[$field]);
        if ($tmp !== $row[$field]) {
            mysqli_query($dbTmp,
                "UPDATE `".$_SESSION['tbl_prefix']."users`
                SET `".$field."` = '".$tmp."'
                WHERE id = '".$row['id']."'"
            );
        }
    }

}
mysqli_free_result($result);



// add field encrypted_data to CATEGORIES table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."categories",
    "encrypted_data",
    "TINYINT(1) NOT NULL DEFAULT '1'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encrypted_data to table CATEGORIES! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}

mysqli_query($dbTmp,
    "UPDATE `".$_SESSION['tbl_prefix']."misc`
    SET `valeur` = 'maintenance_mode'
    WHERE type = 'admin' AND intitule = '".$_POST['no_maintenance_mode']."'"
);


// add field encryption_type to ITEMS table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."items",
    "encryption_type",
    "VARCHAR(20) NOT NULL DEFAULT 'not_set'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encryption_type to table ITEMS! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}


// add field encryption_type to categories_items table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."categories_items",
    "encryption_type",
    "VARCHAR(20) NOT NULL DEFAULT 'not_set'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encryption_type to table categories_items! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}


// add field encryption_type to LOG_ITEMS table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."log_items",
    "encryption_type",
    "VARCHAR(20) NOT NULL DEFAULT 'not_set'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encryption_type to table LOG_ITEMS! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}


//-- generate new DEFUSE key
$filename = "../includes/config/settings.php";
$settingsFile = file($filename);
while (list($key,$val) = each($settingsFile)) {
    if (substr_count($val, 'require_once "')>0 && substr_count($val, 'sk.php')>0) {
        $_SESSION['sk_file'] = substr($val, 14, strpos($val, '";')-14);
    }
}

copy(
    SECUREPATH."/teampass-seckey.txt",
    SECUREPATH."/teampass-seckey.txt".'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y')))
);
$new_salt = defuse_generate_key();
file_put_contents(
    SECUREPATH."/teampass-seckey.txt",
    $new_salt
);
$_SESSION['new_salt'] = $new_salt;

// update sk.php file
copy(
    $_SESSION['sk_file'],
    $_SESSION['sk_file'].'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y')))
);
$data = file($_SESSION['sk_file']); // reads an array of lines
function replace_a_line($data) {
    global $new_salt;
    if (stristr($data, "@define('SALT'")) {
        return "";
    }
    return $data;
}
$data = array_map('replace_a_line', $data);
file_put_contents($_SESSION['sk_file'], implode('', $data));
//--


// add field encryption_type to LOG_ITEMS table
mysqli_query($dbTmp,
    "INSERT INTO `".$_SESSION['tbl_prefix']."misc`
    VALUES ('admin', 'encryption_type', 'not_set')"
);

//-- users need to perform re-encryption of their personal pwds
$result = mysqli_query(
    $dbTmp,
    "SELECT valeur FROM `".$_SESSION['tbl_prefix']."misc` WHERE type='admin' AND intitule='encryption_type'"
);
$row = mysqli_fetch_assoc($result);
if ($row['valeur'] !== "defuse") {
    $result = mysqli_query(
        $dbTmp,
        "SELECT id FROM `".$_SESSION['tbl_prefix']."users`"
    );
    while($row_user = mysqli_fetch_assoc($result)) {
        $result_items = mysqli_query(
            $dbTmp,
            "SELECT i.id AS item_id
            FROM `".$_SESSION['tbl_prefix']."nested_tree` AS n
            INNER JOIN `".$_SESSION['tbl_prefix']."items` AS i ON (i.id_tree = n.id)
            WHERE n.title = ".$row_user['id']
        );
        if (mysqli_num_rows($result_items) > 0) {
            mysqli_query($dbTmp,
                "UPDATE `".$_SESSION['tbl_prefix']."users`
                SET `upgrade_needed` = '1'
                WHERE id = ".$row_user['id']
            );
        } else {
            mysqli_query($dbTmp,
                "UPDATE `".$_SESSION['tbl_prefix']."users`
                SET `upgrade_needed` = '0'
                WHERE id = ".$row_user['id']
            );
        }
    }

    mysqli_query($dbTmp,
        "UPDATE `".$_SESSION['tbl_prefix']."misc`
        SET `valeur` = 'defuse'
        WHERE `type`='admin' AND `initule`='encryption_type'"
    );
}


// add field encrypted_psk to Users table
$res = addColumnIfNotExist(
    $_SESSION['tbl_prefix']."users",
    "encrypted_psk",
    "TEXT NOT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encrypted_psk to table Users! '.mysqli_error($dbTmp).'!"}]';
    mysqli_close($dbTmp);
    exit();
}


// add new admin setting "manager_move_item"
$tmp = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT COUNT(*) FROM `".$_SESSION['tbl_prefix']."misc` WHERE type = 'admin' AND intitule = 'manager_move_item'"));
if ($tmp[0] == 0 || empty($tmp[0])) {
    mysqli_query($dbTmp,
        "INSERT INTO `".$_SESSION['tbl_prefix']."misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'manager_move_item', '0')"
    );
}


// alter table USERS to add a new field "ga_temporary_code"
mysqli_query(
    $dbTmp,
    "ALTER TABLE `".$_SESSION['tbl_prefix']."users` ADD `ga_temporary_code` VARCHAR(20) NOT NULL DEFAULT 'none' AFTER `ga`;"
);



// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';