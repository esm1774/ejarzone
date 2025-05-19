<?php

class Validator {
    private $errors = [];
    private $data = [];
    private $rules = [];
    private $messages = [];
    private $labels = [];
    private static $instance = null;

    private function __construct() {}

    // الحصول على نسخة وحيدة من الكلاس
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // تعيين البيانات للتحقق منها
    public function setData(array $data) {
        $this->data = $data;
        return $this;
    }

    // تعيين قواعد التحقق
    public function setRules(array $rules) {
        $this->rules = $rules;
        return $this;
    }

    // تعيين الرسائل المخصصة
    public function setMessages(array $messages) {
        $this->messages = $messages;
        return $this;
    }

    // تعيين تسميات الحقول
    public function setLabels(array $labels) {
        $this->labels = $labels;
        return $this;
    }

    // الحصول على الأخطاء
    public function getErrors() {
        return $this->errors;
    }

    // التحقق من وجود أخطاء
    public function hasErrors() {
        return !empty($this->errors);
    }

    // الحصول على اسم الحقل المعروض
    private function getFieldLabel($field) {
        return $this->labels[$field] ?? $field;
    }

    // الحصول على رسالة الخطأ المخصصة
    private function getMessage($field, $rule) {
        $key = "{$field}.{$rule}";
        return $this->messages[$key] ?? null;
    }

    // إضافة خطأ
    private function addError($field, $rule, $params = []) {
        $message = $this->getMessage($field, $rule);
        if (!$message) {
            $label = $this->getFieldLabel($field);
            switch ($rule) {
                case 'required':
                    $message = "حقل {$label} مطلوب";
                    break;
                case 'email':
                    $message = "حقل {$label} يجب أن يكون بريد إلكتروني صحيح";
                    break;
                case 'min':
                    $message = "حقل {$label} يجب أن يكون {$params['min']} حروف على الأقل";
                    break;
                case 'max':
                    $message = "حقل {$label} يجب أن لا يتجاوز {$params['max']} حرف";
                    break;
                case 'matches':
                    $matchLabel = $this->getFieldLabel($params['field']);
                    $message = "حقل {$label} يجب أن يطابق حقل {$matchLabel}";
                    break;
                case 'password':
                    $message = "كلمة المرور يجب أن تحتوي على 8 حروف على الأقل، وتتضمن حروف كبيرة وصغيرة، وأرقام، ورموز";
                    break;
                default:
                    $message = "حقل {$label} غير صالح";
            }
        }
        $this->errors[$field] = $message;
    }

    // تنفيذ التحقق
    public function validate() {
        foreach ($this->rules as $field => $rules) {
            if (!isset($this->data[$field])) {
                $this->data[$field] = '';
            }

            $value = $this->data[$field];
            
            foreach ($rules as $rule) {
                if (is_string($rule)) {
                    $ruleName = $rule;
                    $params = [];
                } else {
                    $ruleName = $rule[0];
                    $params = $rule[1];
                }

                switch ($ruleName) {
                    case 'required':
                        if (empty(trim($value))) {
                            $this->addError($field, 'required');
                        }
                        break;

                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($field, 'email');
                        }
                        break;

                    case 'min':
                        if (!empty($value) && mb_strlen($value) < $params['length']) {
                            $this->addError($field, 'min', ['min' => $params['length']]);
                        }
                        break;

                    case 'max':
                        if (!empty($value) && mb_strlen($value) > $params['length']) {
                            $this->addError($field, 'max', ['max' => $params['length']]);
                        }
                        break;

                    case 'matches':
                        if ($value !== $this->data[$params['field']]) {
                            $this->addError($field, 'matches', ['field' => $params['field']]);
                        }
                        break;

                    case 'password':
                        if (!empty($value) && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $value)) {
                            $this->addError($field, 'password');
                        }
                        break;
                }

                // إذا كان هناك خطأ، نتوقف عن التحقق من باقي القواعد لهذا الحقل
                if (isset($this->errors[$field])) {
                    break;
                }
            }
        }

        return !$this->hasErrors();
    }

    // منع نسخ الكائن
    private function __clone() {}

    // منع إعادة إنشاء الكائن من خلال unserialization
    public function __wakeup() {}
}
