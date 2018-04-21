<?php
namespace Zls\Util;
/**
 * 验证规则
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2017-06-19 15:21
 */
use Z;
class CheckRules
{
    public function getRules()
    {
        return [
            'myRule'          => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (empty($value)) {
                    $returnValue = $args[0];
                }
                return true;
            },
            'array'           => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data) || !is_array($value)) {
                    return false;
                }
                $minOkay = true;
                if (Z::arrayKeyExists(0, $args)) {
                    $minOkay = count($value) >= intval($args[0]);
                }
                $maxOkay = true;
                if (Z::arrayKeyExists(1, $args)) {
                    $minOkay = count($value) >= intval($args[1]);
                }
                return $minOkay && $maxOkay;
            },
            'notArray'        => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                return !is_array($value);
            },
            'default'         => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (is_array($value)) {
                    $i = 0;
                    foreach ($value as $k => $v) {
                        $returnValue[$k] = empty($v) ? (Z::arrayKeyExists($i, $args) ? $args[$i] : $args[0]) : $v;
                        $i++;
                    }
                } elseif (empty($value)) {
                    $returnValue = $args[0];
                }
                return true;
            },
            'optional'        => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                $break = empty($data[$key]);
                return true;
            },
            'required'        => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data) || empty($value)) {
                    return false;
                }
                $value = (array)$value;
                foreach ($value as $v) {
                    if (empty($v)) {
                        return false;
                    }
                }
                return true;
            },
            'requiredKey'     => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                $args[] = $key;
                $args = array_unique($args);
                foreach ($args as $k) {
                    if (!Z::arrayKeyExists($k, $data)) {
                        return false;
                    }
                }
                return true;
            },
            'functions'       => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return true;
                }
                $returnValue = $value;
                if (is_array($returnValue)) {
                    foreach ($returnValue as $k => $v) {
                        foreach ($args as $function) {
                            $returnValue[$k] = $function($v);
                        }
                    }
                } else {
                    foreach ($args as $function) {
                        $returnValue = $function($returnValue);
                    }
                }
                return true;
            },
            'xss'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return true;
                }
                $returnValue = Z::xssClean($value);
                return true;
            },
            'match'           => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data) || !Z::arrayKeyExists(0, $args) || !Z::arrayKeyExists($args[0],
                        $data
                    ) || $value != $data[$args[0]]
                ) {
                    return false;
                }
                return true;
            },
            'equal'           => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data) || !Z::arrayKeyExists(0, $args) || $value != $args[0]) {
                    return false;
                }
                return true;
            },
            'enum'            => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $value = (array)$value;
                foreach ($value as $v) {
                    if (!in_array($v, $args)) {
                        return false;
                    }
                }
                return true;
            },
            'unique'          => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #比如unique[user.name] , unique[user.name,id:1]
                if (!Z::arrayKeyExists($key, $data) || !$value || !count($args)) {
                    return false;
                }
                $_info = explode('.', $args[0]);
                if (count($_info) != 2) {
                    return false;
                }
                $table = $_info[0];
                $col = $_info[1];
                if (Z::arrayKeyExists(1, $args)) {
                    $_id_info = explode(':', $args[1]);
                    if (count($_id_info) != 2) {
                        return false;
                    }
                    $id_col = $_id_info[0];
                    $id = $_id_info[1];
                    $id = stripos($id, '#') === 0 ? Z::getPost(substr($id, 1)) : $id;
                    $where = [$col => $value, "$id_col <>" => $id];
                } else {
                    $where = [$col => $value];
                }
                return !$db->where($where)->from($table)->limit(0, 1)->execute()->total();
            },
            'exists'          => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #比如exists[user.name] , exists[user.name,type:1], exists[user.name,type:1,sex:#sex]
                if (!Z::arrayKeyExists($key, $data) || !$value || !count($args)) {
                    return false;
                }
                $_info = explode('.', $args[0]);
                if (count($_info) != 2) {
                    return false;
                }
                $table = $_info[0];
                $col = $_info[1];
                $where = [$col => $value];
                if (count($args) > 1) {
                    foreach (array_slice($args, 1) as $v) {
                        $_id_info = explode(':', $v);
                        if (count($_id_info) != 2) {
                            continue;
                        }
                        $id_col = $_id_info[0];
                        $id = $_id_info[1];
                        $id = stripos($id, '#') === 0 ? Z::getPost(substr($id, 1)) : $id;
                        $where[$id_col] = $id;
                    }
                }
                return $db->where($where)->from($table)->limit(0, 1)->execute()->total();
            },
            'min_len'         => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = Z::arrayKeyExists(0, $args) ? (mb_strlen($value, 'UTF-8') >= intval($args[0])) : false;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'max_len'         => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = Z::arrayKeyExists(0, $args) ? (mb_strlen($value, 'UTF-8') <= intval($args[0])) : false;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'range_len'       => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = count($args) == 2 ? (mb_strlen($value,
                                'UTF-8'
                            ) >= intval($args[0])) && (mb_strlen(
                                $value,
                                'UTF-8'
                            ) <= intval($args[1])) : false;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'len'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = Z::arrayKeyExists(0, $args) ? (mb_strlen($value, 'UTF-8') == intval($args[0])) : false;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'min'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = Z::arrayKeyExists(0, $args) && is_numeric($value) ? $value >= $args[0] : false;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'max'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = Z::arrayKeyExists(0, $args) && is_numeric($value) ? $value <= $args[0] : false;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'range'           => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = (count($args) == 2) && is_numeric($value) ? $value >= $args[0] && $value <= $args[1] : false;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'alpha'           => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                #纯字母
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !preg_match('/[^A-Za-z]+/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'alpha_num'       => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #纯字母和数字
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !preg_match('/[^A-Za-z0-9]+/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'alpha_dash'      => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #纯字母和数字和下划线和-
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !preg_match('/[^A-Za-z0-9_-]+/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'alpha_start'     => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #以字母开头
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = preg_match('/^[A-Za-z]+/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'num'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #纯数字
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !preg_match('/[^0-9]+/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'int'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #整数
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = preg_match('/^([-+]?[1-9]\d*|0)$/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'float'           => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #小数
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = preg_match('/^([1-9]\d*|0)\.\d+$/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'numeric'         => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #数字-1，1.2，+3，4e5
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = is_numeric($value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'natural'         => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #自然数0，1，2，3，12，333
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = preg_match('/^([1-9]\d*|0)$/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'natural_no_zero' => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                #自然数不包含0
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = preg_match('/^[1-9]\d*$/', $value);
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'email'           => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
                        $value
                    ) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'url'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^http[s]?:\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\':+!]*([^<>\"])*$/',
                        $value
                    ) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'qq'              => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^[1-9][0-9]{4,}$/', $value) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'phone'           => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^(?:\d{3}-?\d{8}|\d{4}-?\d{7})$/', $value) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'mobile'          => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^(((13[0-9]{1})|(17[0-9]{1})|(15[0-9]{1})|(18[0-9]{1})|(14[0-9]{1}))+\d{8})$/',
                        $value
                    ) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'zipcode'         => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^[1-9]\d{5}(?!\d)$/', $value) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'idcard'          => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^\d{14}(\d{4}|(\d{3}[xX])|\d{1})$/', $value) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'ip'              => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^((25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(25[0-5]|2[0-4]\d|[01]?\d\d?)$/',
                        $value
                    ) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'chs'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $count = implode(',', array_slice($args, 1, 2));
                $count = empty($count) ? '1,' : $count;
                $can_empty = Z::arrayKeyExists(0, $args) && $args[0] == 'true';
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^[\x{4e00}-\x{9fa5}]{' . $count . '}$/u',
                        $value
                    ) : $can_empty;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'date'            => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^[0-9]{4}-(((0[13578]|(10|12))-(0[1-9]|[1-2][0-9]|3[0-1]))|(02-(0[1-9]|[1-2][0-9]))|((0[469]|11)-(0[1-9]|[1-2][0-9]|30)))$/',
                        $value
                    ) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'time'            => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^(([0-1][0-9])|([2][0-3])):([0-5][0-9])(:([0-5][0-9]))$/',
                        $value
                    ) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'datetime'        => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $args[0] = Z::arrayKeyExists(0, $args) && $args[0] == 'true' ? true : false;
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($value) ? preg_match('/^[0-9]{4}-(((0[13578]|(10|12))-(0[1-9]|[1-2][0-9]|3[0-1]))|(02-(0[1-9]|[1-2][0-9]))|((0[469]|11)-(0[1-9]|[1-2][0-9]|30))) (([0-1][0-9])|([2][0-3])):([0-5][0-9])(:([0-5][0-9]))$/',
                        $value
                    ) : $args[0];
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
            'reg'             => function ($key, $value, $data, $args, &$returnValue, &$break, &$db) {
                if (!Z::arrayKeyExists($key, $data)) {
                    return false;
                }
                $v = (array)$value;
                foreach ($v as $value) {
                    $okay = !empty($args[0]) ? preg_match($args[0], $value) : false;
                    if (!$okay) {
                        return false;
                    }
                }
                return true;
            },
        ];
    }
}
