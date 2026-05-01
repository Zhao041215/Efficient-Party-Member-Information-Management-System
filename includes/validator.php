<?php
/**
 * 输入验证和清理类
 * 提供统一的输入验证和清理方法，防止XSS攻击和数据污染
 */

class Validator {

    /**
     * 清理字符串输入
     * 移除HTML标签，修剪空白，可选长度限制
     *
     * @param mixed $input 输入值
     * @param int|null $maxLength 最大长度限制
     * @return string 清理后的字符串
     */
    public static function cleanString($input, $maxLength = null) {
        if ($input === null) {
            return '';
        }

        // 转换为字符串
        $str = (string)$input;

        // 移除HTML和PHP标签
        $str = strip_tags($str);

        // 修剪空白
        $str = trim($str);

        // 长度限制
        if ($maxLength !== null && mb_strlen($str, 'UTF-8') > $maxLength) {
            $str = mb_substr($str, 0, $maxLength, 'UTF-8');
        }

        return $str;
    }

    /**
     * 验证并清理整数
     *
     * @param mixed $input 输入值
     * @param int|null $min 最小值
     * @param int|null $max 最大值
     * @return int|null 清理后的整数，验证失败返回null
     */
    public static function cleanInt($input, $min = null, $max = null) {
        if ($input === null || $input === '') {
            return null;
        }

        // 使用filter_var验证整数
        $value = filter_var($input, FILTER_VALIDATE_INT);

        if ($value === false) {
            return null;
        }

        // 检查范围
        if ($min !== null && $value < $min) {
            return null;
        }

        if ($max !== null && $value > $max) {
            return null;
        }

        return $value;
    }

    /**
     * 验证并清理浮点数
     *
     * @param mixed $input 输入值
     * @param float|null $min 最小值
     * @param float|null $max 最大值
     * @return float|null 清理后的浮点数，验证失败返回null
     */
    public static function cleanFloat($input, $min = null, $max = null) {
        if ($input === null || $input === '') {
            return null;
        }

        // 使用filter_var验证浮点数
        $value = filter_var($input, FILTER_VALIDATE_FLOAT);

        if ($value === false) {
            return null;
        }

        // 检查范围
        if ($min !== null && $value < $min) {
            return null;
        }

        if ($max !== null && $value > $max) {
            return null;
        }

        return $value;
    }

    /**
     * 验证邮箱格式
     *
     * @param string $email 邮箱地址
     * @return bool 是否有效
     */
    public static function validateEmail($email) {
        if (empty($email)) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 验证手机号格式（中国大陆）
     *
     * @param string $phone 手机号
     * @return bool 是否有效
     */
    public static function validatePhone($phone) {
        if (empty($phone)) {
            return false;
        }

        return preg_match('/^1[3-9]\d{9}$/', $phone) === 1;
    }

    /**
     * 验证身份证号格式
     *
     * @param string $idCard 身份证号
     * @return bool 是否有效
     */
    public static function validateIdCard($idCard) {
        if (strlen($idCard) !== 18) {
            return false;
        }

        // 加权因子
        $weight = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        // 校验码
        $checkCode = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            if (!is_numeric($idCard[$i])) {
                return false;
            }
            $sum += intval($idCard[$i]) * $weight[$i];
        }

        $mod = $sum % 11;
        return strtoupper($idCard[17]) === $checkCode[$mod];
    }

    /**
     * 验证日期格式
     *
     * @param string $date 日期字符串
     * @param string $format 日期格式，默认 'Y-m-d'
     * @return bool 是否有效
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        if (empty($date)) {
            return false;
        }

        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * 白名单验证
     * 检查值是否在允许的值列表中
     *
     * @param mixed $value 要验证的值
     * @param array $allowedValues 允许的值列表
     * @param bool $strict 是否严格比较（类型也要相同）
     * @return bool 是否在白名单中
     */
    public static function validateInArray($value, $allowedValues, $strict = false) {
        if (!is_array($allowedValues)) {
            return false;
        }

        return in_array($value, $allowedValues, $strict);
    }

    /**
     * 批量验证
     * 根据规则批量验证数据
     *
     * @param array $data 要验证的数据
     * @param array $rules 验证规则
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validateBatch($data, $rules) {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            // 必填验证
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = ($rule['label'] ?? $field) . '不能为空';
                continue;
            }

            // 如果值为空且非必填，跳过其他验证
            if (empty($value) && !isset($rule['required'])) {
                continue;
            }

            // 类型验证
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!self::validateEmail($value)) {
                            $errors[$field] = ($rule['label'] ?? $field) . '格式不正确';
                        }
                        break;

                    case 'phone':
                        if (!self::validatePhone($value)) {
                            $errors[$field] = ($rule['label'] ?? $field) . '格式不正确';
                        }
                        break;

                    case 'idcard':
                        if (!self::validateIdCard($value)) {
                            $errors[$field] = ($rule['label'] ?? $field) . '格式不正确';
                        }
                        break;

                    case 'date':
                        $format = $rule['format'] ?? 'Y-m-d';
                        if (!self::validateDate($value, $format)) {
                            $errors[$field] = ($rule['label'] ?? $field) . '格式不正确';
                        }
                        break;

                    case 'int':
                        $min = $rule['min'] ?? null;
                        $max = $rule['max'] ?? null;
                        if (self::cleanInt($value, $min, $max) === null) {
                            $errors[$field] = ($rule['label'] ?? $field) . '必须是有效的整数';
                        }
                        break;
                }
            }

            // 长度验证
            if (isset($rule['maxLength'])) {
                if (mb_strlen($value, 'UTF-8') > $rule['maxLength']) {
                    $errors[$field] = ($rule['label'] ?? $field) . '长度不能超过' . $rule['maxLength'] . '个字符';
                }
            }

            if (isset($rule['minLength'])) {
                if (mb_strlen($value, 'UTF-8') < $rule['minLength']) {
                    $errors[$field] = ($rule['label'] ?? $field) . '长度不能少于' . $rule['minLength'] . '个字符';
                }
            }

            // 白名单验证
            if (isset($rule['in'])) {
                if (!self::validateInArray($value, $rule['in'])) {
                    $errors[$field] = ($rule['label'] ?? $field) . '的值无效';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * 清理数组中的所有字符串值
     *
     * @param array $data 输入数组
     * @param int|null $maxLength 最大长度限制
     * @return array 清理后的数组
     */
    public static function cleanArray($data, $maxLength = null) {
        if (!is_array($data)) {
            return [];
        }

        $cleaned = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $cleaned[$key] = self::cleanArray($value, $maxLength);
            } else {
                $cleaned[$key] = self::cleanString($value, $maxLength);
            }
        }

        return $cleaned;
    }
}
