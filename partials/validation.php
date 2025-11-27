<?php

function isUnique($table, $cell, $excepts = [])
{
    global $conn;

    if (empty($table) || empty($cell)) return false;
 
    $column = array_key_first($cell);
    $value  = $cell[$column];
 
    $sql = "SELECT COUNT(*) AS cnt FROM `$table` WHERE `$column` = ?";
 
    foreach ($excepts as $key => $val) {
        $sql .= " AND `$key` != ?";
    }
 
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
 
    $types = str_repeat("s", 1 + count($excepts));
 
    $params = [$types, $value];
    foreach ($excepts as $val) {
        $params[] = $val;
    }
 
    $stmt->bind_param(...refValues($params));
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    return ($row['cnt'] == 0);
}
 
function refValues($arr)
{
    $refs = [];
    foreach ($arr as $key => $value) {
        $refs[$key] = &$arr[$key];
    }
    return $refs;
}
