<?php
// منع عرض أخطاء PHP للمستخدم
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/Logger.php';
require_once 'includes/Validator.php';

class RegisterHandler {
    private $pdo;
    private $errors = [];
    private $data = [];
    private $logger;
    private $validator;
    private $transaction_active = false;
    
    // تهيئة الكلاس
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            $this->logger = Logger::getInstance();
            $this->validator = Validator::getInstance();
            $this->initializeDatabaseConnection();
        } catch (Exception $e) {
            $this->handleFatalError($e);
        }
    }

    // معالجة الأخطاء القاتلة
    private function handleFatalError($e) {
        $this->logger->error('خطأ قاتل: {message}', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // تهيئة اتصال قاعدة البيانات
    private function initializeDatabaseConnection() {
        try {
            if ($this->pdo === null) {
                $this->pdo = getDatabaseConnection();
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->logger->info('تم الاتصال بقاعدة البيانات بنجاح');
            }
        } catch (PDOException $e) {
            $this->handleDatabaseError('connection', $e);
            throw new Exception('فشل الاتصال بقاعدة البيانات');
        }
    }

    // معالجة أخطاء قاعدة البيانات
    private function handleDatabaseError($operation, PDOException $e) {
        $error_message = '';
        switch ($operation) {
            case 'connection':
                $error_message = 'خطأ في الاتصال بقاعدة البيانات';
                break;
            case 'transaction':
                $error_message = 'خطأ في معالجة العملية';
                break;
            default:
                $error_message = 'خطأ في قاعدة البيانات';
        }

        $this->logger->error("{$error_message}: {message}", [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        $this->errors['database'] = $error_message;
        
        if ($this->transaction_active) {
            try {
                $this->pdo->rollBack();
                $this->transaction_active = false;
                $this->logger->info('تم التراجع عن العملية بنجاح');
            } catch (PDOException $rollback_error) {
                $this->logger->error('فشل التراجع عن العملية: {message}', [
                    'message' => $rollback_error->getMessage()
                ]);
            }
        }
    }

    // بدء معاملة جديدة
    private function beginTransaction() {
        try {
            $this->pdo->beginTransaction();
            $this->transaction_active = true;
            $this->logger->info('تم بدء معاملة جديدة');
            return true;
        } catch (PDOException $e) {
            $this->handleDatabaseError('transaction', $e);
            return false;
        }
    }

    // تأكيد المعاملة
    private function commitTransaction() {
        try {
            if ($this->transaction_active) {
                $this->pdo->commit();
                $this->transaction_active = false;
                $this->logger->info('تم تأكيد المعاملة بنجاح');
            }
            return true;
        } catch (PDOException $e) {
            $this->handleDatabaseError('transaction', $e);
            return false;
        }
    }

    // التراجع عن المعاملة
    private function rollbackTransaction() {
        try {
            if ($this->transaction_active) {
                $this->pdo->rollBack();
                $this->transaction_active = false;
                $this->logger->info('تم التراجع عن المعاملة');
            }
            return true;
        } catch (PDOException $e) {
            $this->handleDatabaseError('transaction', $e);
            return false;
        }
    }

    // إعداد قواعد التحقق
    private function setupValidationRules() {
        $this->validator
            ->setData($this->data)
            ->setLabels([
                'username' => 'اسم المستخدم',
                'password' => 'كلمة المرور',
                'confirm_password' => 'تأكيد كلمة المرور',
                'full_name' => 'الاسم الكامل',
                'email' => 'البريد الإلكتروني'
            ])
            ->setRules([
                'username' => ['required', ['min', ['length' => 3]], ['max', ['length' => 20]]],
                'password' => ['required', 'password'],
                'confirm_password' => ['required', ['matches', ['field' => 'password']]],
                'full_name' => ['required', ['min', ['length' => 2]], ['max', ['length' => 50]]],
                'email' => ['required', 'email']
            ]);
    }

    // دالة لتنظيف المدخلات
    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    // التحقق من CSRF token
    private function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            $this->errors['csrf'] = 'خطأ في التحقق من صحة النموذج';
            $this->logger->warning('محاولة تسجيل مع CSRF token غير صالح');
            return false;
        }
        return true;
    }

    // التحقق من تكرار اسم المستخدم
    private function checkUsernameExists($username) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM users 
                WHERE username = :username
            ");
            
            $stmt->execute(['username' => $username]);
            
            if ($stmt->fetchColumn() > 0) {
                $this->errors['username'] = 'اسم المستخدم موجود بالفعل';
                $this->logger->warning('محاولة تسجيل باسم مستخدم موجود: {username}', ['username' => $username]);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            $this->handleDatabaseError('query', $e);
            return true;
        }
    }

    // التحقق من تكرار البريد الإلكتروني
    private function checkEmailExists($email) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM users 
                WHERE email = :email
            ");
            
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetchColumn() > 0) {
                $this->errors['email'] = 'البريد الإلكتروني موجود بالفعل';
                $this->logger->warning('محاولة تسجيل ببريد إلكتروني موجود: {email}', ['email' => $email]);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            $this->handleDatabaseError('query', $e);
            return true;
        }
    }

    // التحقق من وجود الدور
    private function checkRoleExists($role_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM roles 
                WHERE role_id = :role_id
            ");
            
            $stmt->execute(['role_id' => $role_id]);
            
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $this->handleDatabaseError('query', $e);
            return false;
        }
    }

    // تسجيل المستخدم في قاعدة البيانات
    private function registerUser() {
        if (!$this->beginTransaction()) {
            $this->errors['database'] = 'فشل في بدء المعاملة';
            return false;
        }

        try {
            // التحقق من وجود الدور
            if (!$this->checkRoleExists(4)) {
                $this->errors['role'] = 'الدور غير موجود في النظام';
                $this->logger->error('محاولة تسجيل مستخدم بدور غير موجود: {role_id}', ['role_id' => 4]);
                $this->rollbackTransaction();
                return false;
            }

            $sql = "
                INSERT INTO users (
                    username, 
                    password, 
                    full_name, 
                    email, 
                    created_at,
                    status,
                    role_id,
                    phone,
                    avatar
                ) VALUES (
                    :username,
                    :password,
                    :full_name,
                    :email,
                    NOW(),
                    'نشط',
                    4,
                    NULL,
                    NULL
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            
            $hashed_password = password_hash($this->data['password'], PASSWORD_DEFAULT);
            
            $params = [
                'username' => $this->data['username'],
                'password' => $hashed_password,
                'full_name' => $this->data['full_name'],
                'email' => $this->data['email']
            ];

            // Log the SQL and parameters (without password)
            $logParams = $params;
            $logParams['password'] = '********';
            $this->logger->info('محاولة إدراج مستخدم جديد', [
                'sql' => $sql,
                'params' => $logParams
            ]);
            
            if (!$stmt->execute($params)) {
                $error = $stmt->errorInfo();
                $this->errors['database'] = 'فشل في إدراج المستخدم: ' . ($error[2] ?? 'خطأ غير معروف');
                $this->logger->error('فشل في إدراج المستخدم: {error}', ['error' => $error[2] ?? 'خطأ غير معروف']);
                $this->rollbackTransaction();
                return false;
            }

            if (!$this->commitTransaction()) {
                $this->errors['database'] = 'فشل في تأكيد المعاملة';
                return false;
            }

            $this->logger->info('تم تسجيل المستخدم بنجاح: {username}', ['username' => $this->data['username']]);
            return true;
        } catch (PDOException $e) {
            $this->handleDatabaseError('query', $e);
            $this->errors['database'] = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
            return false;
        }
    }

    // معالجة طلب التسجيل
    public function handleRegistration($post_data) {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $this->logger->info('بدء معالجة طلب تسجيل جديد', ['data' => array_merge(
                $post_data,
                ['password' => '********', 'confirm_password' => '********']
            )]);
            
            // تنظيف وتخزين البيانات
            $this->data = [
                'username' => $this->sanitizeInput($post_data['username'] ?? ''),
                'password' => $post_data['password'] ?? '',
                'confirm_password' => $post_data['confirm_password'] ?? '',
                'full_name' => $this->sanitizeInput($post_data['full_name'] ?? ''),
                'email' => filter_var($post_data['email'] ?? '', FILTER_SANITIZE_EMAIL)
            ];

            // التحقق من CSRF token
            if (!$this->validateCSRFToken($post_data['csrf_token'] ?? '')) {
                $this->logger->warning('فشل التحقق من CSRF token');
                echo json_encode([
                    'success' => false,
                    'message' => 'فشل التحقق من CSRF token'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // إعداد وتنفيذ التحقق من البيانات
            $this->setupValidationRules();
            if (!$this->validator->validate()) {
                $this->errors = array_merge($this->errors, $this->validator->getErrors());
                $this->logger->warning('فشل التحقق من صحة البيانات', ['errors' => $this->errors]);
                echo json_encode([
                    'success' => false,
                    'errors' => $this->errors
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // التحقق من تكرار اسم المستخدم والبريد الإلكتروني
            if ($this->checkUsernameExists($this->data['username'])) {
                $this->logger->warning('اسم المستخدم موجود بالفعل', ['username' => $this->data['username']]);
                echo json_encode([
                    'success' => false,
                    'errors' => ['username' => 'اسم المستخدم موجود بالفعل']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if ($this->checkEmailExists($this->data['email'])) {
                $this->logger->warning('البريد الإلكتروني موجود بالفعل', ['email' => $this->data['email']]);
                echo json_encode([
                    'success' => false,
                    'errors' => ['email' => 'البريد الإلكتروني موجود بالفعل']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // تسجيل المستخدم
            if ($this->registerUser()) {
                $response = [
                    'success' => true,
                    'message' => 'تم التسجيل بنجاح! سيتم تحويلك إلى صفحة تسجيل الدخول...',
                    'redirect' => 'login.php'
                ];
                $this->logger->info('تم إكمال عملية التسجيل بنجاح للمستخدم: {username}', ['username' => $this->data['username']]);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }

            $this->logger->error('فشل في تسجيل المستخدم', ['errors' => $this->errors]);
            echo json_encode([
                'success' => false,
                'errors' => $this->errors
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $e) {
            $this->logger->error('خطأ غير متوقع: {message}', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            echo json_encode([
                'success' => false,
                'message' => 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى لاحقاً.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // الحصول على استجابة الخطأ
    private function getErrorResponse() {
        return [
            'success' => false,
            'errors' => $this->errors
        ];
    }

    // تنظيف الموارد
    public function __destruct() {
        try {
            // إغلاق المعاملة إذا كانت مفتوحة
            if ($this->transaction_active) {
                $this->rollbackTransaction();
            }
        } catch (Exception $e) {
            $this->logger->error('خطأ أثناء إغلاق المعاملة: {message}', ['message' => $e->getMessage()]);
        }

        // لا حاجة لإغلاق اتصال PDO يدوياً، سيتم إغلاقه تلقائياً
        $this->pdo = null;
    }
}

// معالجة الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $handler = new RegisterHandler();
        $handler->handleRegistration($_POST);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى لاحقاً.'
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير صحيحة'
    ], JSON_UNESCAPED_UNICODE);
}
